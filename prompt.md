Tu es un ingénieur full‑stack senior Symfony (Symfony 6.4 LTS ou Symfony 7). Construis une application web Symfony servant de socle solide pour AgriLink — Module 1 (Users & Auth). Le but est une base cohérente, maintenable, avec une UI moderne (Tailwind), sans implémenter les features avancées (JWT, 2FA, Twilio, OAuth, face recognition…).

Exigences clés
UI 100% FR : textes, boutons, labels, titres, erreurs validation, flash messages.
Routes (URLs) en anglais : /login, /register, /dashboard, /profile, /admin/users, etc.
DB existante MySQL via MAMP :
database: agrilink
host: 127.0.0.1
port: 8889
Uploads dans MAMP htdocs :
fichiers de profil stockés sous : htdocs/agrilink/uploads/profiles
l’app Symfony ne stocke pas les fichiers dans /public du projet.
Inscription publique activée.
Email modifiable par l’utilisateur (avec validation + unicité).
Pas d’intégrations externes ni workflows sécurité avancés pour l’instant.
1) Configuration MAMP (obligatoire)
1.1 DATABASE_URL (MAMP:8889)
Fournir un .env.example compatible MAMP, par ex :

DATABASE_URL="mysql://root:root@127.0.0.1:8889/agrilink?serverVersion=8.0&charset=utf8mb4"
Remarque: sur MAMP, user/pass MySQL sont souvent root/root (à documenter et rendre configurable).

1.2 Support DB existante (mode recommandé)
L’app doit fonctionner sur la base agrilink existante.

Si un fichier .sql est fourni par l’utilisateur, documenter le processus d’import (phpMyAdmin MAMP ou CLI).
Doctrine doit être capable de mapper les tables existantes sans les casser.
Si la table user n’existe pas, fournir migrations pour la créer (mode fallback).
2) Stockage fichiers dans MAMP htdocs (obligatoire)
2.1 Variables d’environnement
Ajouter :

UPLOADS_BASE_DIR="/Applications/MAMP/htdocs/agrilink/uploads"
UPLOADS_BASE_URL="/agrilink/uploads"
et utiliser :

profiles => .../uploads/profiles
Si l’utilisateur a MAMP installé ailleurs, ce chemin doit être modifiable via .env.

2.2 Règles upload profil
Stocker la photo de profil dans :
UPLOADS_BASE_DIR/profiles
En DB, stocker un chemin relatif du type :
profiles/<filename>
En Twig, reconstruire l’URL d’accès :
UPLOADS_BASE_URL ~ '/' ~ user.profilePhotoPath
2.3 Service FileUploader
Créer un service réutilisable qui :

valide (jpg/png/webp, max 2Mo)
génère un nom unique
écrit dans le répertoire MAMP htdocs
retourne le chemin relatif à sauvegarder
supprime l’ancienne photo (optionnel mais propre)
3) Routes EN / UI FR (contract)
3.1 Routes (EN)
Public:
GET / (Landing FR)
GET|POST /register
GET|POST /login
POST /logout
Authenticated:
GET /dashboard
GET /profile
GET|POST /profile/edit
POST /profile/photo (ou intégré dans edit)
Admin:
GET /admin/users
GET /admin/users/{id}
POST /admin/users/{id}/toggle-active
POST /admin/users/{id}/set-role
GET|POST /admin/users/{id}/reset-password
3.2 UI (FR)
Les pages correspondantes affichent :

/login => “Connexion”
/register => “Créer un compte”
/dashboard => “Tableau de bord”
/profile => “Mon profil”
/admin/users => “Administration — Utilisateurs” Messages d’erreurs/flash en FR uniquement.
4) Fonctionnalités à livrer (base)
4.1 Auth session (simple et propre)
login email + mot de passe
remember-me
logout
empêcher connexion si isActive=false (UserChecker)
4.2 Inscription publique
formulaire (email, mdp, confirmation mdp, nom complet optionnel)
rôle par défaut à l’inscription: ROLE_AGRICULTEUR (documenter)
redirection après inscription : auto-login + /dashboard (recommandé)
4.3 Profil (email modifiable)
/profile affichage
/profile/edit modif:
fullName, phoneNumber, email
email : validation format + unicité
pas de vérification email maintenant (documenter “à venir”)
4.4 Photo de profil (MAMP htdocs)
upload depuis page profil
affichage avatar + fallback initiales
stocker path relatif en DB
4.5 Admin users (base)
liste + recherche + filtres + pagination
actions:
activer/désactiver
changer rôle
reset mdp (admin définit un nouveau mot de passe via un formulaire sécurisé CSRF)
5) UI/UX Tailwind (exigence forte)
Tailwind + layout pro (navbar + sidebar)
composants Twig réutilisables :
boutons, inputs, flash, cards, badges rôle, pagination
responsive mobile
états vides, confirmations, formulaires agréables
aucune traduction i18n requise : on écrit directement les textes en FR (ou on prépare translations/ mais FR only)
6) Entité User (base)
Champs minimum :

email unique, password hash, roles json
fullName, phoneNumber
isActive
profilePhotoPath
createdAt/updatedAt
Rôles supportés:

ADMIN, AGRICULTEUR, AGRIPLUS, FOURNISSEUR_PERSONNE, FOURNISSEUR_SOCIETE
7) README (FR) + instructions MAMP
Le README doit contenir :

configuration MAMP DB (port 8889, user/pass)
configuration uploads MAMP (UPLOADS_BASE_DIR/URL)
comment créer uploads/profiles et permissions macOS si nécessaire
comment importer .sql si fourni
comptes fixtures