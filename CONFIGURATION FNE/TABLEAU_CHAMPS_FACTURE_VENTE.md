# CHAMPS FNE FACTURE DE VENTE

| Paramètre | Format | Description | Obligatoire (O = Oui / N = Non) |
|---|---|---|---|
| **invoiceType** | string | Type de facture (vente, bordereau d'achat) | O |
| **paymentMethod** | string | Méthode de paiement | O |
| **template** | string | Type de facturation (B2C, B2G, B2B, B2F) | O |
| **isRne** | boolean | Est-ce que la facture est reliée à un reçu (true or false) | O |
| **rne** | string | Numéro du reçu pour lequel la facture est émise | O si isRne est vrai |
| **clientNcc** | string | NCC du client | Obligatoire si Template est B2B |
| **clientCompanyName** | string | Nom du client | O |
| **clientPhone** | int | Numéro de téléphone du client | O |
| **clientEmail** | string | E-mail du client | O |
| **clientSellerName** | string | Nom du vendeur | N |
| **pointOfSale** | string | Nom du point de vente | O |
| **establishment** | string | Nom de l'établissement | O |
| **commercialMessage** | string | Message commercial | N |
| **footer** | string | Message personnel | N |
| **foreignCurrency** | string | Monnaie étrangère | N |
| **foreignCurrencyRate** | number | Taux de la monnaie étrangère | O si foreignCurrency n'est pas vide, O si foreignCurrency est null |
| **items** | Array | Liste des articles | O |
| **taxes** | string | Type de TVA (TVA, TVAB, TVAC, TVAD) | O |
| **customTaxes** | Array | Autres taxes | N |
| **name** | string | Nom de l'autre taxe | O si customTaxes n'est pas vide |
| **amount** | number | Taux de l'autre taxe | O si customTaxes n'est pas vide |
| **reference** | string | Référence de l'article | N |
| **description** | string | Désignation de l'article | O |
| **quantity** | number | Quantité | O |
| **amount** | number | Prix unitaire HT | O |
| **discount** | number | Remise sur article | N |
| **measurementUnit** | string | Unité de mesure des articles | N |
| **discount** | number | Remise sur le total HT | N |

---

# REPONSE FNE FACTURE DE VENTE

| Paramètre | Format | Description | Obligatoire |
|---|---|---|---|
| **ncc** | string | Identifiant contribuable | N/A |
| **reference** | string | Numéro de la facture | N/A |
| **token** | string | Code de vérification à convertir en QR code | N/A |
| **warning** | string | Alerte sur le stock de sticker | N/A |
| **balance_sticker** | int | Balance sticker facture | N/A |
| **invoice** | object | Informations de la facture générée | N/A |
