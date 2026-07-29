#!/bin/bash
# ============================================================================
# SCRIPT DE DÉPLOIEMENT SELFLOW — Production : selflow.dc-knowing.com
# Exécuter sur le serveur depuis la racine du projet Selflow
# ============================================================================

set -e
echo "🚀 Déploiement Selflow — Production"

# 1. Copier le .env de production
echo "📋 Configuration de l'environnement..."
cp .env.production .env

# 2. Installer les dépendances
echo "📦 Installation des dépendances..."
composer install --no-dev --optimize-autoloader
npm ci --production

# 3. Migrations
echo "🗄️  Migrations base de données..."
php artisan migrate --force

# 4. Seed des données initiales
echo "🌱 Seeding des données..."
php artisan db:seed --class=SelflowCompleteSeeder --force

# 5. Optimisations
echo "⚡ Optimisations production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Permissions
echo "🔐 Permissions fichiers..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo ""
echo "✅ Déploiement terminé !"
echo ""
echo "+------------------+----------------------+----------------+"
echo "| Rôle             | Email                | Mot de passe   |"
echo "+------------------+----------------------+----------------+"
echo "| SuperAdmin       | superadmin@gmail.com | 12345678SUPER@ |"
echo "| Admin DC-KNOWING | dcknowing@gmail.com  | ADMIN@@@###123 |"
echo "| Admin B-HOME     | bhome@gmail.com      | ADMIN@@@###123 |"
echo "+------------------+----------------------+----------------+"
echo ""
echo "⚠️  PENSEZ À :"
echo "  1. Modifier DB_PASSWORD dans .env"
echo "  2. Générer une nouvelle APP_KEY : php artisan key:generate"
echo "  3. Configurer le redirect Google OAuth sur Google Console :"
echo "     → Ajouter : https://selflow.dc-knowing.com/auth/callback"
echo "     → JavaScript origins : https://selflow.dc-knowing.com"
