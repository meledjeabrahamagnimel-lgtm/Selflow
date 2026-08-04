# CHAMPS FNE FACTURE D'AVOIR

| Paramètre | Format | Description | Obligatoire |
|---|---|---|---|
| **id** | string | L'identifiant de la facture d'origine doit être récupéré dans la réponse de la requête de certification, puis transmis dans l'appel à l'endpoint correspondant. | Y |
| **items** | Array | Liste des articles | Y |
| **id** | string | id de l'article sur lequel on veut faire un avoir | Y |
| **quantity** | number | La quantité de l'article à retourner | Y |

---

# REPONSE FNE FACTURE D'AVOIR

| Paramètre | Format | Description | Obligatoire |
|---|---|---|---|
| **ncc** | string | Identifiant contribuable | N/A |
| **reference** | string | Numéro de la facture | N/A |
| **token** | string | Code de vérification à convertir en QR code | N/A |
| **warning** | string | Alerte sur le stock de sticker | N/A |
| **balance sticker** | int | Balance sticker facture | N/A |
