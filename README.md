# AgriLink - Plateforme Agricole

Application web Symfony pour la gestion agricole collaborative.

## Structure du Projet

Ce projet est divisé en **6 modules** développés en parallèle par l'équipe.

| Module | Description | Responsable | Branche |
|--------|-------------|-------------|---------|
| 1 | Gestion des Utilisateurs & Authentification | [@JasGLe](https://github.com/JasGLe) | `features/module1` |
| 2 | Gestion des Exploitations Agricoles | [@BoualiWejdene](https://github.com/BoualiWejdene) | `features/module2` |
| 3 | Activités & Planification | [@Mohamed Yassine Ben Aissa](https://github.com/Yvssine04) | `features/module3`
| 4 | Marketplace & E-commerce | [@selmiroua](https://github.com/selmiroua) | `features/module4` |
| 5 | Actualités & Interactions Sociales | [@Mohamed Yassine Azzouz](https://github.com/yassineazzouz1920) | `features/module5` |
| 6 | Équipements & Maintenance | [@Rayen Sassi](https://github.com/RSassi22) | `features/module6` |

📖 **Documentation complète:** [docs/MODULES.md](docs/MODULES.md)

## Prérequis

- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js 18+ & npm
- Symfony CLI (recommandé)

## Installation

```bash
# 1. Cloner le projet
git clone [URL_DU_REPO]
cd PI_dev_symfony

# 2. Installer les dépendances PHP
composer install

# 3. Installer les dépendances JS
npm install

# 4. Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec vos paramètres

# 5. Créer la base de données (si nécessaire)
php bin/console doctrine:database:create

# 6. Vérifier le mapping des entités
php bin/console doctrine:schema:validate

# 7. Lancer le serveur
symfony server:start
# ou
php -S localhost:8000 -t public/
```

## Configuration

Modifier `.env.local` :

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/agrilink?serverVersion=8.0"
MAILER_DSN=smtp://localhost:1025
```

## Branches Git

- `main` - Production (stable)
- `dev` - Développement (intégration)
- `features/module1` - Module 1: User Management
- `features/module2` - Module 2: Exploitations
- `features/module3` - Module 3: Événements
- `features/module4` - Module 4: Marketplace
- `features/module5` - Module 5: Forum
- `features/module6` - Module 6: Équipements


## Workflow Git

```bash
# 1. Se mettre à jour depuis dev
git checkout dev
git pull origin dev

# 2. Créer/basculer sur votre branche de module
git checkout features/module[X]

# 3. Merger dev dans votre branche (régulièrement!)
git merge dev

# 4. Développer et commiter
git add .
git commit -m "feat(moduleX): description"

# 5. Pousser
git push origin features/module[X]

# 6. Créer une PR vers dev quand prêt
```

## Comptes de Test

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Admin | jasser@agrilink.tn | test123 |
| Agriculteur | hassen.trabelsi@agrilink.tn| agri123 |


## Technologies

- **Backend:** Symfony 6.4
- **Base de données:** MySQL 8.0 + Doctrine ORM
- **Frontend:** Twig + Tailwind CSS
- **Sécurité:** Symfony Security + 2FA
- **Email:** Symfony Mailer

## Liens Utiles

- [Documentation Symfony](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Tailwind CSS](https://tailwindcss.com/docs)

## License

Projet académique - ESPRIT 2024/2025
