#!/bin/bash
# =============================================================================
# deploy-seed.sh — Script de peuplement massif Selflow sur serveur distant
# =============================================================================
# USAGE :
#   1. Mettre ce fichier à la racine du projet Selflow sur le serveur
#   2. Lancer : bash deploy-seed.sh
#
# PREREQUIS :
#   - PHP 8.2+ installé sur le serveur
#   - Connexion à la base MySQL configurée dans .env
#   - Artisan accessible (projet Laravel)
# =============================================================================

set -e  # Stopper en cas d'erreur

echo ""
echo "======================================================"
echo "  🚀 SELFLOW — Déploiement Seed Massif Entreprises"
echo "======================================================"
echo ""

# --- Vérifications préliminaires ---
if [ ! -f "artisan" ]; then
    echo "❌ ERREUR : Ce script doit être lancé depuis la racine du projet Selflow."
    exit 1
fi

if [ ! -f ".env" ]; then
    echo "❌ ERREUR : Fichier .env introuvable. Veuillez le configurer d'abord."
    exit 1
fi

echo "✅ Projet Selflow détecté."
echo ""

# --- Confirmation obligatoire ---
echo "⚠️  ATTENTION : Cette opération va SUPPRIMER TOUTES LES DONNÉES existantes"
echo "    et recréer un jeu de données complet pour les deux entreprises."
echo ""
read -p "    Tapez 'OUI' pour continuer : " CONFIRMATION

if [ "$CONFIRMATION" != "OUI" ]; then
    echo ""
    echo "⛔ Opération annulée."
    exit 0
fi

echo ""
echo "📦 Étape 1/4 — Mise en maintenance de l'application..."
php artisan down --render="errors::503" || true

echo ""
echo "🔄 Étape 2/4 — Nettoyage des caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo ""
echo "📊 Étape 3/4 — Exécution des migrations (si nécessaire)..."
php artisan migrate --force

echo ""
echo "🌱 Étape 4/4 — Peuplement massif de la base de données..."
echo "    (Cela peut prendre 2 à 5 minutes selon la performance du serveur...)"
echo ""
php artisan selflow:seed-massive

echo ""
echo "🔁 Remise en service de l'application..."
php artisan up

echo ""
echo "🎉 ==========================================================="
echo "   Peuplement terminé avec succès !"
echo ""
echo "   Deux entreprises disponibles :"
echo "   - Maison Dupont SARL   → admin@gmail.com / 12345678"
echo "   - B2B Agro Fournitures → admin3@gmail.com / 12345678"
echo ""
echo "   Super Administrateur   → superadmin@gmail.com / 12345678SUPER"
echo ""
echo "   Données générées par entreprise :"
echo "   ✔  2 Entreprises avec logos"
echo "   ✔  4 Points de Vente chacune"
echo "   ✔  Utilisateurs (Admin + Responsable + Caissier par PDV)"
echo "   ✔  Plan Comptable SYSCOHADA (16 comptes)"
echo "   ✔  10 Codes Journaux (VTE, ACH, OD, CAI, BNI, SGBCI, OM, MTN, MOOV, WAVE)"
echo "   ✔  100 Clients et 100 Fournisseurs (avec NCC, RCCM, régime fiscal)"
echo "   ✔  100 Catégories et 100 produits par catégorie (= 10 000 articles)"
echo "   ✔  100 Ventes (Devis, BC, BL, Facture — caisse, crédit, banque, mobile)"
echo "   ✔  100 Achats (mêmes types)"
echo "   ✔  5 Avoirs Client et 5 Avoirs Fournisseur"
echo "   ✔  100 Fiches Techniques (Recettes) + 100 Ordres de Production"
echo "   ✔  Stocks physiques affectés (avec ruptures aléatoires)"
echo "   ✔  50 Transferts Internes entre PDV"
echo "   ✔  Écritures Comptables SYSCOHADA (411/701/443 et 401/601/445)"
echo "   ✔  Journal de Trésorerie complet"
echo "==============================================================="
echo ""
