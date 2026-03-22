# 🚀 DÉMARRAGE RAPIDE - AgriLink

## 📋 Vous êtes nouveau ? Lisez ceci en premier !

### 1. Documentation disponible

- **README_AGRILINK.md** → Guide complet d'installation et configuration
- **.copilot/session-state/.../IMPLEMENTATION_SUMMARY.md** → Ce qui a été fait et ce qui reste
- **.copilot/session-state/.../STARTUP_CHECKLIST.md** → Checklist de démarrage pas-à-pas

### 2. Installation rapide (5 minutes)

```bash
# 1. Installer les dépendances
composer install
npm install

# 2. Compiler Tailwind CSS
npm run build

# 3. Configurer l'environnement
cp .env.example .env.local
# Puis éditer .env.local avec vos paramètres MAMP

# 4. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Créer le dossier uploads
mkdir -p /Applications/MAMP/htdocs/agrilink/uploads/profiles
chmod -R 755 /Applications/MAMP/htdocs/agrilink/uploads

# 6. Démarrer l'application
symfony server:start
# Ou : php -S 127.0.0.1:8000 -t public/
```

### 3. Premier test

1. Ouvrir http://127.0.0.1:8000
2. Cliquer "Créer un compte"
3. S'inscrire et tester l'application

### 4. Besoin d'aide ?

Consultez **README_AGRILINK.md** section "Dépannage"

---

**Prêt à commencer ?** Suivez la **STARTUP_CHECKLIST.md** pour un guide détaillé ! ✅
