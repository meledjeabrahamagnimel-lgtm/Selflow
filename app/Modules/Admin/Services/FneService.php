<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Vente;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FneService
{
    /**
     * Normaliser la facture de vente auprès de la DGI (API FNE).
     *
     * @param Vente $vente
     * @param bool $estRne
     * @return array
     */
    public static function normaliserFacture(Vente $vente, bool $estRne = false): array
    {
        $entreprise = $vente->pointDeVente->entreprise;

        // Clé API FNE propre à CETTE entreprise (gérée par le superadmin)
        $credential = $entreprise->fneCredential;
        $apiKey = $credential?->cleActive();
        $apiBaseUrl = $credential && $credential->statut === 'validee'
            ? rtrim(config('selflow.fne_api_url_production', 'https://fne.dgi.gouv.ci'), '/')
            : rtrim(config('selflow.fne_api_url_sandbox', 'https://fne-sandbox.dgi.gouv.ci'), '/');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'La normalisation DGI a échoué : aucune clé API FNE active n\'est configurée pour cette entreprise.',
                'errors'  => ['api_key' => 'Missing FNE API key'],
            ];
        }

        $parentInvoiceId = $vente->parent?->fne_invoice_id;
        $isAvoirRefund = $vente->type_facture === 'avoir' && !empty($parentInvoiceId);

        if ($vente->type_facture === 'avoir' && empty($parentInvoiceId)) {
            return [
                'success' => false,
                'message' => 'Impossible de normaliser un avoir : la facture d\'origine n\'a pas d\'identifiant FNE UUID.',
                'errors'  => ['parent_invoice_id' => 'Missing fne_invoice_id on parent facture'],
            ];
        }

        $apiUrl = $isAvoirRefund
            ? $apiBaseUrl . '/external/invoices/' . $parentInvoiceId . '/refund'
            : $apiBaseUrl . '/external/invoices/sign';

        // Préparation des articles originaux en cas de remboursement/avoir
        $parentDetails = $vente->parent?->details()->with('produit')->get() ?? collect();
        $originalInvoiceItems = $parentDetails
            ->map(function ($detail) {
                return [
                    'id'          => $detail->fne_invoice_item_id,
                    'reference'   => $detail->produit?->reference ?? ($detail->reference ?? null),
                    'description' => $detail->produit?->nom ?? $detail->libelle_virtuel ?? null,
                    'quantity'    => intval($detail->quantite),
                    'amount'      => floatval($detail->prix_unitaire),
                ];
            })
            ->filter(fn ($item) => !empty($item['id']))
            ->values()
            ->all();

        if ($isAvoirRefund) {
            $detailsHaveIds = $parentDetails->every(fn ($detail) => !empty($detail->fne_invoice_item_id));
            if (!$detailsHaveIds || empty($originalInvoiceItems)) {
                return [
                    'success' => false,
                    'message' => 'Impossible de normaliser cet avoir : tous les articles du remboursement doivent avoir un identifiant FNE d’article persistant sur la facture d’origine. Recréez ou re-normalisez la facture originale pour récupérer ces identifiants.',
                    'errors'  => ['line_item_ids' => 'Missing persisted fne_invoice_item_id on original invoice details'],
                ];
            }
        }

        $items = $vente->details->map(function ($d) use ($isAvoirRefund, $originalInvoiceItems) {
            if ($isAvoirRefund) {
                return self::buildRefundItem($d, $originalInvoiceItems);
            }

            $taxeCode = self::devinerCodeTaxe($d->produit?->taux_tva ?? 0.0);
            return [
                'id'             => $d->produit?->reference ?? 'item-' . $d->id,
                'reference'      => $d->produit?->reference ?? ($d->reference ?? ''),
                'description'    => $d->produit ? $d->produit->nom : $d->libelle_virtuel,
                'quantity'       => intval($d->quantite),
                'amount'         => floatval($d->prix_unitaire),
                'discount'       => floatval($d->discount ?? 0),
                'measurementUnit'=> $d->unite_de_mesure ?? ($d->unite ?? 'pcs'),
                'taxes'          => [$taxeCode],
                'customTaxes'    => [],
            ];
        })->toArray();

        if ($isAvoirRefund) {
            $payload = [
                'items' => $items,
            ];
        } else {
            $template = strtoupper($vente->client?->type_facturation ?? 'B2B');
            if (!in_array($template, ['B2B', 'B2C', 'B2G', 'B2F'])) {
                $template = 'B2B';
            }

            $paymentMethod = strtolower(trim($vente->mode_paiement ?? ''));
            if ($paymentMethod === 'banque' && !empty($vente->moyen_bancaire)) {
                // Si le mode = Banque, c'est le moyen_bancaire (carte/virement/cheque) qui prime
                $paymentMethod = strtolower(trim($vente->moyen_bancaire));
            } elseif ($paymentMethod === 'mobile money' || $paymentMethod === 'mobile-money') {
                // Mobile Money : toujours mobile-money pour la DGI
                $paymentMethod = 'mobile-money';
            }

            $pointOfSaleValue = trim($vente->pointDeVente?->code_fne ?: $vente->pointDeVente?->nom ?? 'Siège');

            $payload = [
                'invoiceType'         => 'sale',
                'paymentMethod'       => self::mapperModePaiement($paymentMethod),
                'template'            => $template,
                'isRne'               => $estRne,
                'rne'                 => $estRne ? ($vente->parent?->numero_fne ?? '') : '',
                'clientNcc'           => $vente->client ? preg_replace('/[^0-9A-Z]/', '', strtoupper($vente->client->ncc ?? '')) : '',
                'clientCompanyName'   => $vente->client ? $vente->client->nom : 'Client de passage',
                'clientPhone'         => $vente->client ? preg_replace('/[^0-9]/', '', $vente->client->telephone ?? '') : '',
                'clientEmail'         => $vente->client?->email ?? '',
                'clientSellerName'    => $vente->pointDeVente?->responsable ?? '',
                'pointOfSale'         => $pointOfSaleValue,
                'establishment'       => $entreprise->nom,
                'commercialMessage'   => $entreprise->facture_autres_mentions ?? '',
                'footer'              => $entreprise->pied_de_page_facture ?? '',
                'foreignCurrency'     => !empty($vente->devise) && $vente->devise !== 'XOF' ? $vente->devise : '',
                'foreignCurrencyRate' => !empty($vente->taux_change) && $vente->devise !== 'XOF' ? floatval($vente->taux_change) : 0,
                'customTaxes'         => [],
                'discount'            => floatval($vente->remise ?? 0),
                'items'               => $items,
            ];
        }

        try {
            Log::info("FNE API Call - Normalisation de la facture: " . $vente->numero_facture);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->withOptions(['verify' => true])
            ->post($apiUrl, $payload);

            if (!$response->successful()) {
                Log::error("FNE API Error - Code: " . $response->status() . " Body: " . $response->body());
                return [
                    'success' => false,
                    'message' => 'La normalisation DGI a échoué (HTTP ' . $response->status() . ') : ' . ($response->json('message') ?? $response->body()),
                    'errors'  => ['api_error' => $response->body()],
                ];
            }

            $data = $response->json();
            Log::info("FNE API Response body: " . json_encode($data));
            $invoiceId = self::extraireInvoiceId($data) ?: (string) Str::uuid();
            $fneItemIds = [];

            if (!$isAvoirRefund) {
                $invoiceItems = self::extraireInvoiceItems($data);
                $mappedItemIds = !empty($invoiceItems)
                    ? self::mapperFneItemIdsToDetails($invoiceItems, $vente->details)
                    : [];

                if (!empty($mappedItemIds)) {
                    $fneItemIds = $mappedItemIds;
                } else {
                    foreach ($vente->details as $detail) {
                        $fneItemIds[$detail->id] = (string) Str::uuid();
                    }
                }
            }

            $balanceStickers = null;
            if (isset($data['balance_sticker'])) {
                $balanceStickers = intval($data['balance_sticker']);
            } elseif (isset($data['balance_funds'])) {
                $balanceStickers = intval(intval($data['balance_funds']) / 20);
            }

            if ($balanceStickers !== null) {
                $entreprise->update(['fne_sticker_balance' => $balanceStickers]);
            }

            $numeroRecu = $data['reference'] ?? $data['numero_fne'] ?? null;
            $tokenData = $data['token'] ?? ($data['invoice']['token'] ?? null) ?? null;

            if (empty($numeroRecu) || empty($tokenData)) {
                return [
                    'success' => false,
                    'message' => 'La normalisation DGI a échoué : la réponse de l\'API est incomplète (référence ou token manquant).',
                    'body' => $data,
                ];
            }

            return [
                'success'        => true,
                'numero_recu'    => $numeroRecu,
                'signature'      => $tokenData,
                'qr_code_data'   => $tokenData,
                'pdf_url'        => $data['document_url'] ?? $data['pdf_url'] ?? $data['fichier_pdf'] ?? $tokenData,
                'invoice_id'     => $invoiceId,
                'fne_item_ids'   => $fneItemIds,
            ];
        } catch (\Exception $e) {
            Log::error("FNE API Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception lors de l\'appel API FNE : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Normaliser un achat de type BAPA (Bordereau d'Achat de Produits Agricoles).
     *
     * @param Achat $achat
     * @return array
     */
    public static function normaliserAchatBapa(Achat $achat): array
    {
        $pointDeVente = $achat->pointDeVente;
        $entreprise = $pointDeVente->entreprise;

        $credential = $entreprise->fneCredential;
        $apiKey = $credential?->cleActive();
        $apiBaseUrl = $credential && $credential->statut === 'validee'
            ? rtrim(config('selflow.fne_api_url_production', 'https://fne.dgi.gouv.ci'), '/')
            : rtrim(config('selflow.fne_api_url_sandbox', 'https://fne-sandbox.dgi.gouv.ci'), '/');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'La normalisation DGI du BAPA a échoué : aucune clé API FNE active n\'est configurée pour cette entreprise.',
                'errors'  => ['api_key' => 'Missing FNE API key'],
            ];
        }

        $apiUrl = $apiBaseUrl . '/external/invoices/sign';

        $paymentMethod = strtolower(trim($achat->mode_paiement ?? ''));
        $pointOfSaleValue = trim($achat->pointDeVente?->code_fne ?: $achat->pointDeVente?->nom ?? 'Siège');

        $items = $achat->details->map(function ($d) {
            return [
                'reference'      => $d->produit?->reference ?? ($d->reference ?? ''),
                'description'    => $d->produit ? $d->produit->nom : $d->libelle_virtuel,
                'quantity'       => intval($d->quantite),
                'amount'         => floatval($d->prix_unitaire),
                'discount'       => 0,
                'measurementUnit'=> $d->unite ?? 'pcs',
            ];
        })->toArray();

        $payload = [
            'invoiceType'         => 'purchase',
            'paymentMethod'       => self::mapperModePaiement($paymentMethod),
            'template'            => 'B2B',
            'clientNcc'           => preg_replace('/[^0-9A-Z]/', '', strtoupper($entreprise->ncc ?? '')),
            'clientCompanyName'   => $entreprise->nom,
            'clientPhone'         => preg_replace('/[^0-9]/', '', $entreprise->telephone ?? ''),
            'clientEmail'         => $entreprise->email ?? '',
            'clientSellerName'    => $achat->fournisseur?->nom ?? '',
            'pointOfSale'         => $pointOfSaleValue,
            'establishment'       => $entreprise->nom,
            'commercialMessage'   => $entreprise->facture_autres_mentions ?? '',
            'footer'              => $entreprise->pied_de_page_facture ?? '',
            'foreignCurrency'     => !empty($achat->devise) && $achat->devise !== 'XOF' ? $achat->devise : '',
            'foreignCurrencyRate' => !empty($achat->taux_change) && $achat->devise !== 'XOF' ? floatval($achat->taux_change) : 0,
            'items'               => $items,
            'discount'            => 0,
        ];

        try {
            Log::info("FNE API Call - Normalisation BAPA de l'achat: " . $achat->numero_facture);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10)
            ->withOptions(['verify' => true])
            ->post($apiUrl, $payload);

            if (!$response->successful()) {
                Log::error("FNE BAPA API Error - Code: " . $response->status() . " Body: " . $response->body());
                return [
                    'success' => false,
                    'message' => 'La normalisation DGI du BAPA a échoué (HTTP ' . $response->status() . ') : ' . ($response->json('message') ?? $response->body()),
                    'errors'  => ['api_error' => $response->body()],
                ];
            }

            $data = $response->json();
            Log::info("FNE BAPA API Response body: " . json_encode($data));
            $balanceStickers = null;
            if (isset($data['balance_sticker'])) {
                $balanceStickers = intval($data['balance_sticker']);
            } elseif (isset($data['balance_funds'])) {
                $balanceStickers = intval(intval($data['balance_funds']) / 20);
            }

            if ($balanceStickers !== null) {
                $entreprise->update(['fne_sticker_balance' => $balanceStickers]);
            }

            $numeroRecu = $data['reference'] ?? $data['numero_fne'] ?? null;
            $tokenData = $data['token'] ?? ($data['invoice']['token'] ?? null) ?? null;

            if (empty($numeroRecu) || empty($tokenData)) {
                return [
                    'success' => false,
                    'message' => 'La normalisation DGI du BAPA a échoué : la réponse de l\'API est incomplète (référence ou token manquant).',
                    'body' => $data,
                ];
            }

            return [
                'success'      => true,
                'numero_recu'  => $numeroRecu,
                'signature'    => $tokenData,
                'qr_code_data' => $tokenData,
                'pdf_url'      => $data['document_url'] ?? $data['pdf_url'] ?? $data['fichier_pdf'] ?? $tokenData,
            ];
        } catch (\Exception $e) {
            Log::error("FNE BAPA API Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception lors de l\'appel API FNE BAPA : ' . $e->getMessage(),
            ];
        }
    }

    private static function mapperModePaiement(string $modePaiement): string
    {
        return match (strtolower(trim($modePaiement))) {
            // CAISSE
            'caisse', 'cash', 'espèces', 'especes', 'espece', 'espèce' => 'cash',
            // MOBILE MONEY (tous opérateurs → même valeur DGI)
            'mobile money', 'mobile-money', 'mobilemoney', 'momo',
            'mtn', 'moov', 'orange', 'wave' => 'mobile-money',
            // BANQUE — Carte bancaire
            'carte', 'card', 'visa', 'mastercard' => 'card',
            // BANQUE — Chèque
            'chèque', 'cheque', 'chq', 'check' => 'check',
            // BANQUE — Virement
            'virement', 'transfer', 'transfert', 'bank transfer' => 'transfer',
            // CRÉDIT / A TERME
            'crédit', 'credit', 'deferred', 'à terme', 'a terme', 'terme' => 'deferred',
            // Par défaut (sécurité)
            default => 'cash',
        };
    }

    private static function devinerCodeTaxe(float $taux): string
    {
        return match (round($taux, 2)) {
            18.0, 18 => 'TVA',
            0.0, 0 => 'TVAC',
            10.0, 10 => 'TVAB',
            20.0, 20 => 'TVAD',
            default => 'TVA',
        };
    }

    private static function extraireInvoiceId(array $data): ?string
    {
        return $data['invoice']['id'] ?? $data['invoiceId'] ?? $data['id'] ?? null;
    }

    private static function extraireInvoiceItems(array $data): array
    {
        $items = $data['invoice']['items'] ?? $data['items'] ?? [];

        if (!is_array($items)) {
            return [];
        }

        return array_values($items);
    }

    private static function extraireInvoiceItemId(array $item): ?string
    {
        return $item['invoiceItemId'] ?? $item['id'] ?? null;
    }

    private static function mapperFneItemIdsToDetails(array $invoiceItems, $details): array
    {
        $mapping = [];
        $remaining = array_values($invoiceItems);

        foreach ($details as $detail) {
            $reference = $detail->produit?->reference ?? ($detail->reference ?? null);
            $description = $detail->produit?->nom ?? $detail->libelle_virtuel ?? null;
            $quantity = intval($detail->quantite);
            $amount = floatval($detail->prix_unitaire);
            $matchedIndex = null;

            foreach ($remaining as $index => $item) {
                if (!empty($reference) && isset($item['reference']) && $item['reference'] === $reference) {
                    $matchedIndex = $index;
                    break;
                }
            }

            if ($matchedIndex === null) {
                foreach ($remaining as $index => $item) {
                    if (!empty($description) && isset($item['description']) && $item['description'] === $description) {
                        $matchedIndex = $index;
                        break;
                    }
                }
            }

            if ($matchedIndex === null) {
                foreach ($remaining as $index => $item) {
                    $itemQuantity = isset($item['quantity']) ? intval($item['quantity']) : null;
                    $itemAmount = isset($item['amount']) ? floatval($item['amount']) : null;
                    if ($itemQuantity === $quantity && $itemAmount === $amount) {
                        $matchedIndex = $index;
                        break;
                    }
                }
            }

            if ($matchedIndex !== null) {
                $item = $remaining[$matchedIndex];
                $itemId = self::extraireInvoiceItemId($item);
                if ($itemId) {
                    $mapping[$detail->id] = $itemId;
                }
                array_splice($remaining, $matchedIndex, 1);
            }
        }

        return $mapping;
    }

    private static function buildRefundItem($detail, array $originalInvoiceItems = []): array
    {
        $existingFneItemId = $detail->fne_invoice_item_id ?? null;
        if (!empty($existingFneItemId)) {
            return [
                'id'       => $existingFneItemId,
                'quantity' => intval($detail->quantite),
            ];
        }

        if (empty($originalInvoiceItems)) {
            throw new \RuntimeException('Impossible de faire un avoir FNE : aucun identifiant d\'article FNE disponible et la facture d\'origine n\'a pas pu être récupérée.');
        }

        $reference = $detail->produit?->reference ?? ($detail->reference ?? null);
        $description = $detail->produit?->nom ?? $detail->libelle_virtuel ?? null;
        $quantity = intval($detail->quantite);
        $amount = floatval($detail->prix_unitaire ?? 0);

        $match = null;
        foreach ($originalInvoiceItems as $item) {
            if (!empty($reference) && isset($item['reference']) && $item['reference'] === $reference) {
                $match = $item;
                break;
            }

            if (!empty($description) && isset($item['description']) && $item['description'] === $description) {
                $match = $item;
                break;
            }
        }

        if (!$match) {
            foreach ($originalInvoiceItems as $item) {
                $itemQuantity = isset($item['quantity']) ? intval($item['quantity']) : null;
                $itemAmount = isset($item['amount']) ? floatval($item['amount']) : null;
                if ($itemQuantity === $quantity && $itemAmount === $amount) {
                    $match = $item;
                    break;
                }
            }
        }

        if (!$match) {
            $candidates = array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? $item['invoiceItemId'] ?? null,
                    'reference' => $item['reference'] ?? null,
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'amount' => $item['amount'] ?? null,
                ];
            }, $originalInvoiceItems);

            throw new \RuntimeException('Impossible de faire correspondre la ligne d\'avoir avec un article de la facture FNE d\'origine. Candidates: ' . json_encode($candidates, JSON_UNESCAPED_UNICODE));
        }

        return [
            'id'       => self::extraireInvoiceItemId($match) ?? $detail->produit?->reference ?? 'item-' . $detail->id,
            'quantity' => $quantity,
        ];
    }
}
