<div align="center">
  <img src="public/logo_with_text.png" alt="AgriLink Logo" width="280"/>
  
  <br/>
  
  <p><em>Plateforme Agricole Collaborative — Symfony 6.4</em></p>

  ![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
  ![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=flat-square&logo=symfony&logoColor=white)
  ![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
  ![Tailwind CSS](https://img.shields.io/badge/TailwindCSS-3.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
  ![License](https://img.shields.io/badge/Licence-Académique-green?style=flat-square)

</div>

---

## 📖 À propos

**AgriLink** est une application web Symfony conçue pour la **gestion agricole collaborative**. Elle offre une suite complète d'outils pour les agriculteurs, les gestionnaires d'exploitations et les acteurs de la filière agri-alimentaire.

---

## 🧩 Modules du Projet

Le projet est structuré en **6 modules** développés en parallèle :

| # | Module | Description | Responsable | Branche |
|---|--------|-------------|-------------|---------|
| `1` | 👤 Utilisateurs & Auth | Gestion des comptes et authentification | [@JasGLe](https://github.com/JasGLe) | `features/module1` |
| `2` | 🌾 Exploitations | Gestion des exploitations agricoles | [@BoualiWejdene](https://github.com/BoualiWejdene) | `features/module2` |
| `3` | 📅 Activités | Planification et suivi des activités | [@Yvssine04](https://github.com/Yvssine04) | `features/module3` |
| `4` | 🛒 Marketplace | E-commerce et vente de produits | [@selmiroua](https://github.com/selmiroua) | `features/module4` |
| `5` | 📰 Actualités | Interactions sociales et forum | [@yassineazzouz1920](https://github.com/yassineazzouz1920) | `features/module5` |
| `6` | 🔧 Équipements | Gestion et maintenance du matériel | [@RSassi22](https://github.com/RSassi22) | `features/module6` |

> 📖 **Documentation complète :** [docs/MODULES.md](docs/MODULES.md)

---

## 🛠️ Technologies

| Couche | Technologie |
|--------|-------------|
| **Backend** | Symfony 6.4 |
| **Base de données** | MySQL 8.0 + Doctrine ORM |
| **Frontend** | Twig + Tailwind CSS |
| **Sécurité** | Symfony Security + 2FA |
| **Email** | Symfony Mailer |

---

## ✅ Prérequis

Avant de commencer, assurez-vous d'avoir installé :

- 🐘 **PHP** 8.2+
- 🎼 **Composer**
- 🗄️ **MySQL** 8.0+
- 🟢 **Node.js** 18+ & npm
- ⚡ **Symfony CLI** *(recommandé)*

---

## 🚀 Installation

```bash
# 1. Cloner le projet
git clone [URL_DU_REPO]
cd PI_dev_symfony

# 2. Installer les dépendances PHP
composer install

# 3. Installer les dépendances JavaScript
npm install

# 4. Configurer l'environnement
cp .env .env.local
# → Éditer .env.local avec vos paramètres de connexion

# 5. Créer la base de données
php bin/console doctrine:database:create

# 6. Vérifier le mapping des entités
php bin/console doctrine:schema:validate

# 7. Lancer le serveur de développement
symfony server:start
# ou
php -S localhost:8000 -t public/
```

---

## ⚙️ Configuration

Modifiez le fichier `.env.local` avec vos paramètres :

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/agrilink?serverVersion=8.0"
MAILER_DSN=smtp://localhost:1025
```

---

## 🌿 Branches Git

```
main                  ← Production (stable)
dev                   ← Intégration & développement
├── features/module1  ← Utilisateurs & Authentification
├── features/module2  ← Exploitations Agricoles
├── features/module3  ← Activités & Planification
├── features/module4  ← Marketplace & E-commerce
├── features/module5  ← Actualités & Forum
└── features/module6  ← Équipements & Maintenance
```

---

## 🔄 Workflow Git

Suivez ce workflow pour contribuer proprement au projet :

```bash
# 1. Se synchroniser avec dev
git checkout dev
git pull origin dev

# 2. Basculer sur votre branche de module
git checkout features/module[X]

# 3. Merger dev dans votre branche régulièrement
git merge dev

# 4. Développer et commiter
git add .
git commit -m "feat(moduleX): description courte de la modification"

# 5. Pousser vos changements
git push origin features/module[X]

# 6. Ouvrir une Pull Request vers dev quand prêt ✅
```

> 💡 **Conseil :** Mergez `dev` dans votre branche fréquemment pour éviter les conflits.

---

## 🔑 Comptes de Test

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| 🛡️ Admin | jasser@agrilink.tn | `test123` |
| 🌾 Agriculteur | hassen.trabelsi@agrilink.tn | `agri123` |

> ⚠️ Ces identifiants sont réservés à l'environnement de développement local.

---

## 🔗 Ressources Utiles

- 📘 [Documentation Symfony](https://symfony.com/doc/current/index.html)
- 🗃️ [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- 🎨 [Tailwind CSS](https://tailwindcss.com/docs)
- 🔒 [Symfony Security](https://symfony.com/doc/current/security.html)

---

## 📄 Licence

> Projet académique — **ESPRIT** · Année universitaire 2024/2025

<div align="center">
  <sub>Made with ❤️ by the AgriLink Team</sub>
</div>
