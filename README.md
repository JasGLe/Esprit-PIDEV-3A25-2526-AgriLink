<div align="center">
  <img src="public/logo_with_text.png" alt="AgriLink Logo" width="280"/>

  <br/>

  <p><em>Collaborative Agriculture Platform — Symfony 6.4</em></p>

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=flat-square&logo=symfony&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Bootstrap 5](https://img.shields.io/badge/Bootstrap-5.3.x-7952B3?style=flat-square&logo=bootstrap&logoColor=white) ![License](https://img.shields.io/badge/Academic-License-green?style=flat-square)

</div>

---

## About

**AgriLink** is a Symfony web application built for **collaborative agricultural management**. It provides a complete set of tools for farmers, farm managers, and stakeholders in the agri-food ecosystem.

---

## Project Modules

The project is structured into **6 modules** developed in parallel:

| #   | Module                | Description                           | Owner                                                      | Branch             |
| --- | --------------------- | ------------------------------------- | ---------------------------------------------------------- | ------------------ |
| `1` | User Management & Auth| Account management and authentication | [@JasGLe](https://github.com/JasGLe)                       | `features/module1` |
| `2` | Farm Operations       | Farm and field management             | [@BoualiWejdene](https://github.com/BoualiWejdene)         | `features/module2` |
| `3` | Activities            | Activity planning and tracking        | [@Yvssine04](https://github.com/Yvssine04)                 | `features/module3` |
| `4` | Marketplace           | E-commerce and product sales          | [@selmiroua](https://github.com/selmiroua)                 | `features/module4` |
| `5` | News & Community      | Social interactions and forum         | [@yassineazzouz1920](https://github.com/yassineazzouz1920) | `features/module5` |
| `6` | Equipment             | Equipment management and maintenance  | [@RSassi22](https://github.com/RSassi22)                   | `features/module6` |

> Full documentation: `docs/MODULES.md`

---

## Prerequisites

Make sure you have the following installed:

- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js 18+ and npm
- Symfony CLI recommended

---

## Installation

```bash
# 1. Clone the repository
git clone [REPOSITORY_URL]
cd PI_dev_symfony

# 2. Install PHP dependencies
composer install

# 3. Install JavaScript dependencies
npm install

# 4. Configure the environment
cp .env.example .env.local
# Then edit .env.local with your local settings

# 5. Create the database
php bin/console doctrine:database:create --if-not-exists

# 6. Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 7. Build frontend assets
npm run build

# 8. Start the development server
symfony server:start
# or
php -S localhost:8000 -t public/
```

---

## Configuration

Update `.env.local` with your local values:

```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/agrilink?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN=null://null
APP_URL=http://127.0.0.1:8000
DEFAULT_URI=http://127.0.0.1:8000
```

---

## Git Branches

```text
main                  <- Production (stable)
dev                   <- Integration and development
|-- features/module1  <- User Management and Authentication
|-- features/module2  <- Farm Operations
|-- features/module3  <- Activities and Planning
|-- features/module4  <- Marketplace and E-commerce
|-- features/module5  <- News and Community
\-- features/module6  <- Equipment and Maintenance
```

---

## Git Workflow

Follow this workflow to contribute cleanly:

```bash
# 1. Sync with dev
git checkout dev
git pull origin dev

# 2. Switch to your module branch
git checkout features/module[X]

# 3. Merge dev into your branch regularly
git merge dev

# 4. Develop and commit
git add .
git commit -m "feat(moduleX): short description of the change"

# 5. Push your changes
git push origin features/module[X]

# 6. Open a Pull Request to dev when ready
```

---

## Useful Resources

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Symfony Security](https://symfony.com/doc/current/security.html)

---

## License

Academic project — **ESPRIT** · Academic year 2024/2025

<div align="center">
  <sub>Made by the AgriLink Team</sub>
</div>
