# AgriLink - Module 1 : Users & Auth

> Application Symfony 8.0 complète pour la gestion d'utilisateurs et l'authentification avec interface Tailwind CSS

## 📋 Table des matières

- [Vue d'ensemble](#vue-densemble)
- [Pré-requis](#pré-requis)
- [Installation](#installation)
- [Configuration MAMP](#configuration-mamp)
- [Configuration des uploads](#configuration-des-uploads)
- [Base de données](#base-de-données)
- [Lancement de l'application](#lancement-de-lapplication)
- [Fonctionnalités](#fonctionnalités)
- [Comptes de test](#comptes-de-test)
- [Structure du projet](#structure-du-projet)
- [Technologies utilisées](#technologies-utilisées)

## 🎯 Vue d'ensemble

AgriLink Module 1 est une application web Symfony professionnelle qui fournit :

- ✅ **Authentification complète** : Login, Register, Logout avec "Se souvenir de moi"
- ✅ **Gestion de profil** : Modification des informations personnelles et photo de profil
- ✅ **Administration** : Interface admin pour gérer les utilisateurs
- ✅ **UI moderne** : Interface responsive avec Tailwind CSS
- ✅ **Sécurité** : Blocage des comptes inactifs, validation des données
- ✅ **Uploads MAMP** : Stockage des fichiers dans htdocs pour développement local

### Caractéristiques techniques

- **UI 100% en français** avec routes en anglais
- **Base de données MySQL** via MAMP (port 8889)
- **Uploads** stockés dans `/Applications/MAMP/htdocs/agrilink/uploads`
- **5 rôles utilisateur** : Admin, Agriculteur, AgriPlus, Fournisseur Personne, Fournisseur Société
- **Email modifiable** avec validation d'unicité

## 🛠 Pré-requis

- **PHP** >= 8.4
- **Composer** >= 2.0
- **Node.js** >= 18 (pour Tailwind CSS)
- **MAMP** (ou équivalent avec MySQL)
- **Symfony CLI** (optionnel mais recommandé)

## 📦 Installation

### 1. Cloner le projet

```bash
git clone <votre-repo>
cd PI_dev_symfony
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Installer les dépendances Node.js

```bash
npm install
```

### 4. Compiler Tailwind CSS

```bash
# Production (fichier minifié)
npm run build

# Développement (mode watch)
npm run dev
```

## 🗄 Configuration MAMP

### 1. Démarrer MAMP

- Lancez MAMP
- Démarrez les serveurs Apache et MySQL
- Vérifiez que MySQL tourne sur le port **8889** (par défaut MAMP)

### 2. Configuration de la base de données

Créez le fichier `.env.local` à la racine du projet avec :

```env
# Configuration MAMP MySQL (port 8889, credentials root/root)
DATABASE_URL="mysql://root:root@127.0.0.1:8889/agrilink?serverVersion=8.0&charset=utf8mb4"

# Chemin uploads MAMP (adaptez selon votre installation)
UPLOADS_BASE_DIR="/Applications/MAMP/htdocs/agrilink/uploads"
UPLOADS_BASE_URL="/agrilink/uploads"

# Secret pour Symfony (générez-en un unique)
APP_SECRET=your-secret-key-change-me-in-production
```

> ⚠️ **Note** : Sur MAMP, les identifiants MySQL par défaut sont souvent `root/root`. Ajustez si nécessaire.

### 3. Créer la base de données

```bash
# Créer la base si elle n'existe pas
php bin/console doctrine:database:create

# Ou via phpMyAdmin MAMP
# Accédez à http://localhost:8888/phpMyAdmin/
# Créez une base nommée "agrilink"
```

### 4. Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate
```

> ⚠️ **Important** : Si vous avez déjà une base `agrilink` existante, les migrations peuvent modifier les tables. Faites une sauvegarde avant !

## 📁 Configuration des uploads

### 1. Créer le dossier uploads dans MAMP htdocs

```bash
# Créer les dossiers nécessaires
mkdir -p /Applications/MAMP/htdocs/agrilink/uploads/profiles

# Donner les permissions (macOS)
chmod -R 755 /Applications/MAMP/htdocs/agrilink/uploads
```

### 2. Vérifier les permissions

Sur macOS, assurez-vous que l'utilisateur PHP/Apache a les droits d'écriture :

```bash
# Vérifier le propriétaire
ls -la /Applications/MAMP/htdocs/agrilink/uploads

# Si nécessaire, changer le propriétaire (remplacez $USER par votre utilisateur)
sudo chown -R $USER:staff /Applications/MAMP/htdocs/agrilink/uploads
```

### 3. Configuration alternative (si MAMP est ailleurs)

Si MAMP est installé à un autre emplacement, modifiez `.env.local` :

```env
# Exemple : MAMP installé ailleurs
UPLOADS_BASE_DIR="/Users/votre-nom/MAMP/htdocs/agrilink/uploads"
UPLOADS_BASE_URL="/agrilink/uploads"
```

## 💾 Base de données

### Option 1 : Base existante

Si vous avez déjà une base `agrilink` avec des données, les migrations Doctrine vont synchroniser le schéma. **Faites une sauvegarde avant !**

```bash
# Sauvegarder via mysqldump
/Applications/MAMP/Library/bin/mysqldump -uroot -proot agrilink > backup_agrilink.sql
```

### Option 2 : Import d'un fichier SQL

Si vous avez reçu un fichier `.sql` :

**Via phpMyAdmin (recommandé) :**
1. Accédez à http://localhost:8888/phpMyAdmin/
2. Sélectionnez la base `agrilink`
3. Onglet "Importer"
4. Choisissez votre fichier `.sql`
5. Cliquez sur "Exécuter"

**Via ligne de commande :**
```bash
/Applications/MAMP/Library/bin/mysql -uroot -proot agrilink < votre_fichier.sql
```

### Option 3 : Nouvelle base (fresh install)

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## 🚀 Lancement de l'application

### 1. Avec Symfony CLI (recommandé)

```bash
symfony server:start
```

Accédez à : **http://127.0.0.1:8000**

### 2. Avec PHP built-in server

```bash
php -S 127.0.0.1:8000 -t public/
```

### 3. Mode watch Tailwind (dans un terminal séparé)

Pour le développement, gardez Tailwind en mode watch :

```bash
npm run dev
```

Cela recompilera automatiquement le CSS à chaque modification.

## ✨ Fonctionnalités

### 🔐 Authentification

- **Inscription publique** (`/register`)
  - Email, mot de passe, nom complet (optionnel)
  - Rôle par défaut : `ROLE_AGRICULTEUR`
  - Auto-login après inscription
  - Redirection vers `/dashboard`

- **Connexion** (`/login`)
  - Email + mot de passe
  - "Se souvenir de moi" (Remember Me)
  - Messages d'erreur en français
  - Protection CSRF

- **Déconnexion** (`/logout`)
  - Un seul clic pour se déconnecter

### 👤 Profil utilisateur

- **Affichage du profil** (`/profile`)
  - Avatar avec photo ou initiales
  - Informations personnelles
  - Badge de rôle
  - Date de création du compte

- **Modification du profil** (`/profile/edit`)
  - Nom complet
  - Email (avec validation d'unicité)
  - Numéro de téléphone
  - Upload de photo de profil (JPG, PNG, WEBP, max 2 Mo)

### 👥 Administration (ROLE_ADMIN uniquement)

L'interface admin sera disponible à `/admin/users` pour :

- Liste des utilisateurs avec recherche et filtres
- Voir les détails d'un utilisateur
- Activer/Désactiver un compte
- Modifier le rôle d'un utilisateur
- Réinitialiser le mot de passe

> 📝 **Note** : Les fonctionnalités admin seront implémentées dans la phase 2.

### 🎨 Interface utilisateur

- **Layout professionnel** avec navbar + sidebar
- **Composants Twig réutilisables**
  - Boutons
  - Flash messages
  - Cartes (cards)
  - Badges de rôle
  - Avatars
- **Responsive** : Fonctionne sur mobile, tablette et desktop
- **Tailwind CSS** : Design moderne et cohérent

## 🔑 Comptes de test

Pour tester l'application, vous pouvez créer des comptes manuellement via l'inscription, ou importer des fixtures (à venir).

### Créer un compte admin manuellement

```bash
php bin/console doctrine:query:sql "UPDATE user SET roles = '[\"ROLE_ADMIN\"]' WHERE email = 'votre@email.com'"
```

### Exemples de rôles

- `ROLE_ADMIN` : Administrateur (accès complet)
- `ROLE_AGRICULTEUR` : Agriculteur (rôle par défaut)
- `ROLE_AGRIPLUS` : Utilisateur AgriPlus
- `ROLE_FOURNISSEUR_PERSONNE` : Fournisseur individuel
- `ROLE_FOURNISSEUR_SOCIETE` : Fournisseur société

## 📂 Structure du projet

```
PI_dev_symfony/
├── assets/                    # Assets frontend
│   ├── styles/
│   │   └── app.css           # Tailwind CSS source
│   └── app.js                # JavaScript
├── config/                    # Configuration Symfony
│   ├── packages/
│   │   └── security.yaml     # Configuration sécurité
│   ├── services.yaml          
│   └── services_custom.yaml  # Service FileUploader
├── migrations/                # Migrations Doctrine
├── public/                    # Document root
│   ├── build/
│   │   └── app.css           # Tailwind CSS compilé
│   └── index.php
├── src/
│   ├── Controller/
│   │   ├── DashboardController.php
│   │   ├── ProfileController.php
│   │   ├── RegistrationController.php
│   │   └── SecurityController.php
│   ├── Entity/
│   │   └── User.php          # Entité utilisateur
│   ├── Form/
│   │   ├── ProfileEditFormType.php
│   │   └── RegistrationFormType.php
│   ├── Repository/
│   │   └── UserRepository.php
│   ├── Security/
│   │   ├── UserAuthenticator.php
│   │   └── UserChecker.php   # Bloque utilisateurs inactifs
│   └── Service/
│       └── FileUploader.php  # Service upload fichiers
├── templates/
│   ├── base.html.twig         # Layout de base
│   ├── layouts/
│   │   └── app.html.twig      # Layout authentifié
│   ├── components/            # Composants réutilisables
│   │   ├── _flash.html.twig
│   │   ├── _button.html.twig
│   │   ├── _avatar.html.twig
│   │   ├── _card.html.twig
│   │   └── _role_badge.html.twig
│   ├── dashboard/
│   │   └── index.html.twig
│   ├── home/
│   │   └── landing.html.twig  # Page d'accueil
│   ├── profile/
│   │   ├── index.html.twig
│   │   └── edit.html.twig
│   └── security/
│       ├── login.html.twig
│       └── register.html.twig
├── .env                       # Configuration par défaut
├── .env.example               # Exemple de configuration
├── composer.json
├── package.json
├── tailwind.config.js         # Configuration Tailwind
└── README.md
```

## 🛠 Technologies utilisées

### Backend
- **Symfony 8.0** LTS - Framework PHP
- **Doctrine ORM** - Mapping objet-relationnel
- **Security Component** - Authentification et autorisation
- **Form Component** - Formulaires avec validation
- **Twig** - Moteur de templates

### Frontend
- **Tailwind CSS 3.4** - Framework CSS utility-first
- **Stimulus** - JavaScript framework léger
- **Turbo** - Navigation rapide sans rechargement complet

### Base de données
- **MySQL 8.0** via MAMP

### Outils de développement
- **Symfony CLI** - Serveur de développement
- **npm** - Gestion des dépendances JS
- **Composer** - Gestion des dépendances PHP

## 📝 Notes importantes

### Sécurité

- ✅ Protection CSRF sur tous les formulaires
- ✅ Passwords hashés avec bcrypt
- ✅ Validation des uploads (type MIME, taille)
- ✅ Email unique enforced au niveau DB et validation
- ✅ UserChecker bloque les comptes inactifs

### Limitations actuelles (Module 1)

Cette version est le **module de base**. Les fonctionnalités suivantes ne sont **PAS** incluses :

- ❌ JWT authentication
- ❌ Two-Factor Authentication (2FA)
- ❌ OAuth (Google, Facebook, etc.)
- ❌ Face recognition
- ❌ Email verification
- ❌ Password reset par email
- ❌ Twilio SMS
- ❌ API REST

Ces fonctionnalités seront ajoutées dans les modules suivants.

### Environnements

- **dev** : Mode développement avec debug activé
- **prod** : Production (nécessite configuration supplémentaire)
- **test** : Tests automatisés (PHPUnit)

## 🐛 Dépannage

### Le CSS Tailwind ne se charge pas

```bash
# Recompiler Tailwind
npm run build

# Vider le cache Symfony
php bin/console cache:clear
```

### Erreur de connexion à la base de données

- Vérifiez que MAMP est démarré
- Vérifiez le port MySQL (8889 par défaut)
- Vérifiez les identifiants dans `.env.local`

### Les uploads ne fonctionnent pas

```bash
# Vérifier les permissions
ls -la /Applications/MAMP/htdocs/agrilink/uploads

# Recréer le dossier si nécessaire
mkdir -p /Applications/MAMP/htdocs/agrilink/uploads/profiles
chmod -R 755 /Applications/MAMP/htdocs/agrilink/uploads
```

### Erreur 500 après login

- Vérifiez que la table `user` existe
- Vérifiez les migrations : `php bin/console doctrine:migrations:status`

## 📧 Support

Pour toute question ou problème, consultez :

- [Documentation Symfony](https://symfony.com/doc/current/index.html)
- [Documentation Tailwind CSS](https://tailwindcss.com/docs)
- [Documentation Doctrine](https://www.doctrine-project.org/)

---

**Version** : 1.0.0 - Module 1 (Users & Auth)  
**Date** : Mars 2026  
**Framework** : Symfony 8.0  
**Auteur** : AgriLink Development Team
