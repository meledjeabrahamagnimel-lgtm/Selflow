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
     * @param bool  $emissionRecuDePassage Conservé pour compatibilité avec les
     *        appels existants. La nature du document (facture ou reçu) est
     *        portée par `ventes.type_piece`, choisie à la saisie. Elle n'a rien
     *        à voir avec le champ `isRne` de la DGI, qui signifie « cette
     *        facture est rattachée à un reçu normalisé déjà émis » et provient
     *        de la case cochée à la saisie (`ventes.est_rne`).
     * @return array
     */
    public static function normaliserFacture(Vente $vente, bool $emissionRecuDePassage = false): array
    {
        $entreprise = $vente->pointDeVente->entreprise;

        // Chargement anticipé : le payload lit le produit, ses taxes
        // personnalisées et celles de la facture pour chaque ligne.
        $vente->loadMissing(['details.produit', 'details.taxes', 'taxesPersonnalisees']);

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

        // La certification d'un reçu normalisé électronique (RNE) suppose des
        // champs de mappage que la DGI n'a pas encore communiqués : mieux vaut
        // le dire que d'envoyer un payload de facture pour un reçu.
        if ($vente->estRecu()) {
            return [
                'success' => false,
                'message' => 'La normalisation des reçus est en attente : la FNE n\'a pas encore fourni les champs de mappage du reçu normalisé électronique (RNE). Le reçu reste enregistré et pourra être normalisé rétroactivement.',
                'errors'  => ['rne_mapping' => 'En attente des champs de mappage RNE'],
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

        $regimeImposition = $entreprise->regime_imposition ?? null;

        $items = $vente->details->map(function ($d) use ($isAvoirRefund, $originalInvoiceItems, $regimeImposition) {
            if ($isAvoirRefund) {
                return self::buildRefundItem($d, $originalInvoiceItems);
            }

            // Le champ `id` n'est PAS attendu par /external/invoices/sign : il
            // n'apparaît que dans le payload de remboursement. Il n'est donc
            // plus envoyé ici.
            return [
                'reference'      => $d->produit?->reference ?? ($d->reference ?? ''),
                'description'    => $d->produit ? $d->produit->nom : $d->libelle_virtuel,
                'quantity'       => intval($d->quantite),
                'amount'         => floatval($d->prix_unitaire),
                'discount'       => self::normaliserTaux($d->remise_taux ?? 0),
                'measurementUnit'=> $d->unite ?? 'pcs',
                'taxes'          => [self::codeTaxeLigne($d, $regimeImposition)],
                'customTaxes'    => self::formaterTaxesPersonnalisees($d->taxes ?? null),
            ];
        })->toArray();

        if ($isAvoirRefund) {
            $payload = [
                'items' => $items,
            ];
        } else {
            $clientNcc = $vente->client
                ? preg_replace('/[^0-9A-Z]/', '', strtoupper($vente->client->ncc ?? ''))
                : '';

            $template = strtoupper($vente->client?->type_facturation ?? '');
            if (!in_array($template, ['B2B', 'B2C', 'B2G', 'B2F'])) {
                $template = $clientNcc !== '' ? 'B2B' : 'B2C';
            }

            // Le NCC du client est obligatoire en B2B : sans lui, la FNE rejette
            // la facture (HTTP 400). Un client de passage relève du B2C.
            if ($template === 'B2B' && $clientNcc === '') {
                $template = 'B2C';
            }

            $paymentMethod = strtolower(trim($vente->mode_paiement ?? ''));
            if ($paymentMethod === 'banque' && !empty($vente->moyen_bancaire)) {
                // Si le mode = Banque, c'est le moyen_bancaire (carte/virement/cheque) qui prime
                $paymentMethod = strtolower(trim($vente->moyen_bancaire));
            } elseif ($paymentMethod === 'mobile money' || $paymentMethod === 'mobile-money') {
                // Mobile Money : toujours mobile-money pour la DGI
                $paymentMethod = 'mobile-money';
            }

            // `pointOfSale` est décrit par la DGI comme « Nom du point de vente » :
            // c'est bien le nom, tel que déclaré dans l'espace FNE de
            // l'entreprise, qui doit être transmis.
            $pointOfSaleValue = trim($vente->pointDeVente?->nom ?: 'Siège');

            // `isRne` indique que la facture est rattachée à un reçu normalisé
            // déjà émis. La seule source valable est la case cochée à la saisie :
            // l'ancienne déduction « pas de client ⇒ isRne = true » envoyait un
            // rattachement inexistant à la DGI pour toute vente au comptant.
            $factureRattacheeAUnRecu = (bool) ($vente->est_rne ?? false);
            $numeroRne = trim((string) ($vente->numero_rne ?? ''));

            $payload = [
                'invoiceType'         => 'sale',
                'paymentMethod'       => self::mapperModePaiement($paymentMethod),
                'template'            => $template,
                'isRne'               => $factureRattacheeAUnRecu,
                'rne'                 => $factureRattacheeAUnRecu ? $numeroRne : '',
                'clientNcc'           => $clientNcc,
                'clientCompanyName'   => $vente->client ? $vente->client->nom : 'Client de passage',
                'clientPhone'         => $vente->client ? preg_replace('/[^0-9]/', '', $vente->client->telephone ?? '') : '',
                'clientEmail'         => $vente->client?->email ?? '',
                'clientSellerName'    => $vente->pointDeVente?->responsable ?? '',
                'pointOfSale'         => $pointOfSaleValue,
                'establishment'       => $entreprise->nom,
                'commercialMessage'   => self::texteMention($vente->autres_mentions, $entreprise->facture_autres_mentions),
                'footer'              => self::texteMention($vente->pied_de_page, $entreprise->pied_de_page_facture),
                'foreignCurrency'     => !empty($vente->devise) && $vente->devise !== 'XOF' ? $vente->devise : '',
                'foreignCurrencyRate' => !empty($vente->taux_change) && $vente->devise !== 'XOF' ? floatval($vente->taux_change) : 0,
                'customTaxes'         => self::formaterTaxesPersonnalisees($vente->taxesPersonnalisees ?? null),
                // La FNE attend un POURCENTAGE, pas un montant : `ventes.remise`
                // (en francs) ne peut pas être envoyé tel quel.
                'discount'            => self::normaliserTaux($vente->remise_taux ?? 0),
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
                'retours_fne'    => self::extraireRetoursFne($data),
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

        $achat->loadMissing(['details.produit', 'fournisseur']);

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
        $pointOfSaleValue = trim($achat->pointDeVente?->nom ?: 'Siège');

        $items = $achat->details->map(function ($d) {
            return [
                'reference'      => $d->produit?->reference ?? ($d->reference ?? ''),
                'description'    => $d->produit ? $d->produit->nom : $d->libelle_virtuel,
                'quantity'       => intval($d->quantite),
                'amount'         => floatval($d->prix_unitaire),
                'discount'       => self::normaliserTaux($d->remise_taux ?? 0),
                'measurementUnit'=> $d->unite ?? 'pcs',
            ];
        })->toArray();

        // Sur un bordereau d'achat, le « client » au sens de la FNE est le
        // FOURNISSEUR (le producteur agricole) : c'est nous qui émettons le
        // bordereau. Cf. API #3 — « clientCompanyName : Nom du fournisseur ».
        $fournisseur = $achat->fournisseur;

        $bordereauRattacheAUnRecu = (bool) ($achat->est_rne ?? false);

        $payload = [
            'invoiceType'         => 'purchase',
            'paymentMethod'       => self::mapperModePaiement($paymentMethod),
            'template'            => 'B2B',
            'isRne'               => $bordereauRattacheAUnRecu,
            'rne'                 => $bordereauRattacheAUnRecu ? trim((string) ($achat->numero_rne ?? '')) : '',
            'clientCompanyName'   => $fournisseur?->nom ?? '',
            'clientPhone'         => preg_replace('/[^0-9]/', '', $fournisseur?->telephone ?? ''),
            'clientEmail'         => $fournisseur?->email ?? '',
            'clientSellerName'    => $achat->pointDeVente?->responsable ?? '',
            'pointOfSale'         => $pointOfSaleValue,
            'establishment'       => $entreprise->nom,
            'commercialMessage'   => self::texteMention($achat->autres_mentions, $entreprise->facture_autres_mentions),
            'footer'              => self::texteMention($achat->pied_de_page, $entreprise->pied_de_page_facture),
            'foreignCurrency'     => !empty($achat->devise) && $achat->devise !== 'XOF' ? $achat->devise : '',
            'foreignCurrencyRate' => !empty($achat->taux_change) && $achat->devise !== 'XOF' ? floatval($achat->taux_change) : 0,
            'items'               => $items,
            'discount'            => self::normaliserTaux($achat->remise_taux ?? 0),
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
                'retours_fne'  => self::extraireRetoursFne($data),
            ];
        } catch (\Exception $e) {
            Log::error("FNE BAPA API Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception lors de l\'appel API FNE BAPA : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Donnees de la reponse de certification que Selflow conserve pour le
     * suivi : alerte de stock de stickers, montants retenus par la DGI, timbre
     * fiscal applique et horodatage de la certification.
     */
    /**
     * Colonnes a enregistrer sur la piece a partir des retours de la
     * plateforme, pretes a etre passees a un update().
     */
    public static function colonnesRetoursFne(array $resultat): array
    {
        $retours = $resultat['retours_fne'] ?? [];

        if (empty($retours)) {
            return [];
        }

        return array_filter([
            'fne_alerte_stickers' => $retours['alerte_stickers'] ?? false,
            'fne_montant_ttc'     => $retours['montant_ttc'] ?? null,
            'fne_montant_tva'     => $retours['montant_tva'] ?? null,
            'fne_timbre_fiscal'   => $retours['timbre_fiscal'] ?? null,
            'fne_certifie_at'     => $retours['certifie_at'] ?? null,
        ], fn ($valeur) => $valeur !== null);
    }

    private static function extraireRetoursFne(array $data): array
    {
        $facture = $data['invoice'] ?? [];

        return [
            'alerte_stickers' => !empty($data['warning']),
            'montant_ttc'     => isset($facture['amount']) ? floatval($facture['amount']) : null,
            'montant_tva'     => isset($facture['vatAmount']) ? floatval($facture['vatAmount']) : null,
            'timbre_fiscal'   => isset($facture['fiscalStamp']) ? floatval($facture['fiscalStamp']) : null,
            'certifie_at'     => $facture['date'] ?? $facture['createdAt'] ?? null,
        ];
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

    /**
     * Longueur maximale acceptée pour les mentions libres transmises à la FNE
     * (`footer` et `commercialMessage`).
     */
    public const LONGUEUR_MAX_MENTION = 248;

    /**
     * Code TVA DGI d'une ligne de facture.
     *
     * Le code vient du produit (choix manuel ou déduction automatique tenant
     * compte du régime d'imposition). Une ligne de saisie libre, sans produit
     * rattaché, retombe sur la déduction depuis son propre taux de TVA.
     */
    private static function codeTaxeLigne($detail, ?string $regimeImposition): string
    {
        $produit = $detail->produit ?? null;
        $tauxLigne = self::tauxTvaDeLaLigne($detail);

        // Un choix manuel sur la fiche produit fait foi, mais seulement tant que
        // la ligne applique bien le taux du catalogue : une vente peut relever
        // d'un régime différent, auquel cas le code suit le taux réellement
        // appliqué.
        if ($produit && $produit->code_tva_manuel && abs($tauxLigne - (float) $produit->taux_tva) < 0.01) {
            return $produit->codeTvaFne($regimeImposition);
        }

        return \App\Modules\Admin\Modeles\Produit::deduireCodeTva($tauxLigne, $regimeImposition);
    }

    /**
     * Taux de TVA effectif d'une ligne sans produit rattaché, reconstitué
     * depuis les montants enregistrés.
     */
    private static function tauxTvaDeLaLigne($detail): float
    {
        $remiseLigne = (float) ($detail->remise_taux ?? 0);
        $ht = floatval($detail->quantite ?? 0) * floatval($detail->prix_unitaire ?? 0) * (1 - $remiseLigne / 100);
        if ($ht <= 0) {
            return 0.0;
        }

        return round(floatval($detail->montant_tva ?? 0) / $ht * 100, 2);
    }

    /**
     * Table de correspondance taux → code DGI, conservée pour compatibilité.
     *
     * @deprecated Utiliser Produit::deduireCodeTva(), qui distingue TVAC
     *             (exonération conventionnelle) de TVAD (exonération légale
     *             TEE / RNE) — les deux valant 0 %.
     */
    private static function devinerCodeTaxe(float $taux, ?string $regime = null): string
    {
        return \App\Modules\Admin\Modeles\Produit::deduireCodeTva($taux, $regime);
    }

    /**
     * Met un taux (remise ou taxe) au format attendu par la FNE : un nombre
     * compris entre 0 et 100, exprimé en pourcentage.
     */
    private static function normaliserTaux($taux): float
    {
        $valeur = round(floatval($taux), 2);

        return max(0.0, min(100.0, $valeur));
    }

    /**
     * Mention libre transmise à la FNE : la valeur saisie sur la pièce prime
     * sur celle des paramètres de l'entreprise, et la longueur est bornée.
     */
    private static function texteMention(?string $valeurPiece, ?string $valeurEntreprise): string
    {
        $texte = trim((string) $valeurPiece);
        if ($texte === '') {
            $texte = trim((string) $valeurEntreprise);
        }

        return mb_substr($texte, 0, self::LONGUEUR_MAX_MENTION);
    }

    /**
     * Formate une collection de taxes personnalisées au format FNE :
     * [{ "name": "GRA", "amount": 5 }, ...]
     *
     * Les taxes hors bornes (taux nul ou négatif, > 100 %) ou sans nom sont
     * écartées : la DGI exige `name` et `amount` dès que `customTaxes` est
     * renseigné.
     */
    private static function formaterTaxesPersonnalisees($taxes): array
    {
        if (empty($taxes)) {
            return [];
        }

        return collect($taxes)
            ->map(function ($taxe) {
                $nom  = trim((string) ($taxe->nom ?? $taxe['nom'] ?? ''));
                $taux = self::normaliserTaux($taxe->taux ?? $taxe['taux'] ?? 0);

                return ['name' => $nom, 'amount' => $taux];
            })
            ->filter(fn ($taxe) => $taxe['name'] !== '' && $taxe['amount'] > 0)
            ->values()
            ->all();
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
