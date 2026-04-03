# AgriLink - Structure des Modules

## Vue d'ensemble

Ce projet est organisé en **6 modules** indépendants. Chaque membre de l'équipe est responsable d'un module spécifique.

---

## Module 1: Gestion des Utilisateurs (USER MANAGEMENT) ✅
**Responsable:** [Votre nom]
**Statut:** Implémenté

### Entités
- `User` - Utilisateurs (tous les rôles)
- `SecurityEvent` - Événements de sécurité
- `UserSession` - Sessions utilisateur

### Fonctionnalités
- Authentification (login/logout)
- Inscription multi-rôles (Agriculteur, Fournisseur, AgriPlus)
- Vérification email
- Réinitialisation mot de passe
- Authentification 2FA (OTP)
- Gestion de profil
- Upload photo de profil
- Administration des utilisateurs
- Tableau de bord sécurité

### Dossiers
```
src/
├── Controller/
│   ├── SecurityController.php
│   ├── RegistrationController.php
│   ├── ProfileController.php
│   ├── DashboardController.php
│   ├── EmailVerificationController.php
│   ├── PasswordResetController.php
│   ├── TwoFactorController.php
│   └── Admin/
│       ├── DashboardController.php
│       ├── UserController.php
│       └── SecurityController.php
├── Entity/
│   ├── User.php
│   ├── SecurityEvent.php
│   └── UserSession.php
├── Form/
│   ├── RegistrationFormType.php
│   ├── RegistrationAgriculteurType.php
│   ├── RegistrationFournisseurType.php
│   ├── RegistrationAgriPlusType.php
│   ├── ProfileEditFormType.php
│   ├── ChangePasswordType.php
│   ├── ForgotPasswordType.php
│   ├── ResetPasswordType.php
│   └── AdminUserType.php
├── Repository/
│   ├── UserRepository.php
│   ├── SecurityEventRepository.php
│   └── UserSessionRepository.php
├── Security/
│   └── UserChecker.php
├── Service/
│   ├── EmailVerificationService.php
│   ├── PasswordResetService.php
│   ├── SecurityEventService.php
│   └── TwoFactorService.php
templates/
├── security/
├── profile/
├── dashboard/
└── admin/
    ├── users/
    └── security/
```

---

## Module 2: Gestion des Exploitations Agricoles (FARM MANAGEMENT)
**Responsable:** [Nom du coéquipier]
**Statut:** À implémenter

### Entités
- `Exploitation` - Exploitations agricoles
- `Parcelle` - Parcelles de terrain
- `Culture` - Cultures
- `Activite` - Activités agricoles

### Fonctionnalités à implémenter
- CRUD Exploitation
- Gestion des parcelles
- Suivi des cultures
- Calendrier des activités
- Statistiques de production
- Dashboard agriculteur

### Dossiers à créer
```
src/
├── Controller/
│   └── Exploitation/
│       ├── ExploitationController.php
│       ├── ParcelleController.php
│       ├── CultureController.php
│       └── ActiviteController.php
├── Form/
│   └── Exploitation/
│       ├── ExploitationType.php
│       ├── ParcelleType.php
│       ├── CultureType.php
│       └── ActiviteType.php
templates/
└── exploitation/
    ├── index.html.twig
    ├── parcelle/
    ├── culture/
    └── activite/
```

---

## Module 3: Marketplace & Produits (MARKETPLACE)
**Responsable:** [Nom du coéquipier]
**Statut:** À implémenter

### Entités
- `Produits` - Produits à vendre
- `Annonce` - Annonces
- `Panier` - Panier d'achat
- `Commandes` - Commandes
- `LigneCommande` - Lignes de commande
- `CancellationRequests` - Demandes d'annulation
- `MarketplaceBans` - Bannissements
- `MarketplaceWarnings` - Avertissements
- `MarketplaceCooldowns` - Cooldowns

### Fonctionnalités à implémenter
- CRUD Produits
- Gestion des annonces
- Panier d'achat
- Processus de commande
- Historique des commandes
- Système de modération
- Dashboard fournisseur

### Dossiers à créer
```
src/
├── Controller/
│   └── Marketplace/
│       ├── ProduitController.php
│       ├── AnnonceController.php
│       ├── PanierController.php
│       ├── CommandeController.php
│       └── ModerationController.php
├── Form/
│   └── Marketplace/
│       ├── ProduitType.php
│       ├── AnnonceType.php
│       └── CommandeType.php
templates/
└── marketplace/
    ├── produit/
    ├── annonce/
    ├── panier/
    └── commande/
```

---

## Module 4: Gestion des Équipements & Maintenance (EQUIPMENT)
**Responsable:** [Nom du coéquipier]
**Statut:** À implémenter

### Entités
- `Equipement` - Équipements agricoles
- `Maintenance` - Opérations de maintenance

### Fonctionnalités à implémenter
- CRUD Équipements
- Planification maintenance
- Historique des interventions
- Alertes de maintenance
- Gestion des coûts
- Rapports d'utilisation

### Dossiers à créer
```
src/
├── Controller/
│   └── Equipment/
│       ├── EquipementController.php
│       └── MaintenanceController.php
├── Form/
│   └── Equipment/
│       ├── EquipementType.php
│       └── MaintenanceType.php
templates/
└── equipment/
    ├── equipement/
    └── maintenance/
```

---

## Module 5: Forum & Communication (COMMUNITY)
**Responsable:** [Nom du coéquipier]
**Statut:** À implémenter

### Entités
- `Forum` - Sujets de forum
- `Message` - Messages
- `Notifications` - Notifications
- `NotificationRead` - Statut de lecture

### Fonctionnalités à implémenter
- Forum de discussion
- Messagerie privée
- Système de notifications
- Notifications push (optionnel)
- Recherche dans les discussions

### Dossiers à créer
```
src/
├── Controller/
│   └── Community/
│       ├── ForumController.php
│       ├── MessageController.php
│       └── NotificationController.php
├── Form/
│   └── Community/
│       ├── ForumType.php
│       └── MessageType.php
templates/
└── community/
    ├── forum/
    ├── message/
    └── notification/
```

---

## Module 6: Événements & Calendrier (EVENTS)
**Responsable:** [Nom du coéquipier]
**Statut:** À implémenter

### Entités
- `Evenement` - Événements

### Fonctionnalités à implémenter
- CRUD Événements
- Calendrier interactif
- Inscriptions aux événements
- Rappels automatiques
- Filtrage par catégorie/date

### Dossiers à créer
```
src/
├── Controller/
│   └── Event/
│       └── EvenementController.php
├── Form/
│   └── Event/
│       └── EvenementType.php
templates/
└── event/
    ├── index.html.twig
    ├── show.html.twig
    ├── calendar.html.twig
    └── _form.html.twig
```

---

## Instructions pour les coéquipiers

### 1. Cloner le projet
```bash
git clone [URL_DU_REPO]
cd PI_dev_symfony
```

### 2. Installer les dépendances
```bash
composer install
npm install
```

### 3. Configurer l'environnement
```bash
cp .env .env.local
# Éditer .env.local avec vos paramètres de base de données
```

### 4. Créer votre branche de feature
```bash
git checkout dev
git pull origin dev
git checkout -b features/module[X]  # X = numéro de votre module
```

### 5. Développer votre module
- Créez vos contrôleurs dans le dossier approprié
- Utilisez les entités existantes (déjà mappées à la BD)
- Suivez les conventions du Module 1 comme exemple
- Utilisez les layouts existants (`dashboard_layout.html.twig`)

### 6. Pousser vos changements
```bash
git add .
git commit -m "feat(moduleX): description"
git push origin features/module[X]
```

### 7. Créer une Pull Request vers `dev`

---

## Conventions de code

### Nommage
- Controllers: `NomController.php`
- Forms: `NomType.php`
- Templates: `snake_case.html.twig`
- Routes: `app_module_action` (ex: `app_marketplace_produit_list`)

### Structure des commits
```
feat(module): nouvelle fonctionnalité
fix(module): correction de bug
docs(module): documentation
style(module): formatage
refactor(module): refactoring
```

### Templates
- Utiliser `{% extends 'layouts/dashboard_layout.html.twig' %}` pour les pages connectées
- Utiliser `{% extends 'base.html.twig' %}` pour les pages publiques
- Utiliser les composants existants dans `templates/components/`

---

## Base de données

La base de données est déjà créée avec toutes les tables nécessaires. Les entités sont générées et prêtes à l'emploi.

**Ne pas modifier les entités existantes sans coordination avec l'équipe.**

### Relations importantes
- `User` est la clé centrale, liée à plusieurs entités
- Toujours utiliser les relations Doctrine existantes
- Consulter les entités dans `src/Entity/` pour comprendre les relations

---

## Support

Pour toute question sur le Module 1 (User Management), contactez [Votre nom].

Pour l'architecture générale du projet, référez-vous à ce document.
