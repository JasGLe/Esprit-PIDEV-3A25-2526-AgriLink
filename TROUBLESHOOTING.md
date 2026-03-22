# 🔧 TROUBLESHOOTING GUIDE - AgriLink

## ❌ Erreur: "Typed property App\Entity\User::$roles must not be accessed before initialization"

### Cause
Cette erreur se produit lorsque la colonne `roles` dans la table `user` contient des valeurs `NULL` au lieu d'un tableau JSON valide.

### Solution (DÉJÀ APPLIQUÉE)

```bash
# 1. Mettre à jour tous les utilisateurs existants avec un rôle par défaut
cd /Users/jasseryahyaoui/Desktop/PI_dev_symfony
php bin/console dbal:run-sql "UPDATE user SET roles = JSON_ARRAY('ROLE_AGRICULTEUR') WHERE roles IS NULL OR roles = 'null'"

# 2. Vérifier que les roles sont bien définis
php bin/console dbal:run-sql "SELECT id, email, roles FROM user LIMIT 5"

# 3. Vider le cache Symfony
php bin/console cache:clear

# 4. Redémarrer le serveur
symfony server:stop
symfony server:start
```

### Vérification manuelle

Si le problème persiste, vérifiez manuellement dans phpMyAdmin (http://localhost:8888/phpMyAdmin/) :

1. Sélectionnez la base `agrilink`
2. Ouvrez la table `user`
3. Vérifiez que la colonne `roles` contient des valeurs comme `["ROLE_AGRICULTEUR"]` et non `NULL`

## ❌ Erreur: "Neither the property "get" nor one of the methods "get()" exist in class Request"

### Cause
Tentative d'accès incorrect aux attributs de la requête dans Twig. En Symfony 8, il faut utiliser `app.request.attributes.get('_route')` au lieu de `app.request.get('_route')`.

### Solution (DÉJÀ APPLIQUÉE)

Le template `layouts/app.html.twig` a été corrigé pour utiliser:
```twig
{{ app.request.attributes.get('_route') }}
```

Si vous rencontrez cette erreur dans vos propres templates:

**Incorrect:**
```twig
{{ app.request.get('_route') }}
```

**Correct:**
```twig
{{ app.request.attributes.get('_route') }}
```

Après correction:
```bash
php bin/console cache:clear
```

## ❌ Erreur: "Cannot access login page"

### Solutions possibles

1. **Vérifier que le serveur tourne**
   ```bash
   # Vérifier les processus
   ps aux | grep php
   
   # Redémarrer le serveur
   symfony server:stop
   symfony server:start
   ```

2. **Vérifier l'URL**
   - Accéder à: http://127.0.0.1:8000/login
   - Pas http://localhost:8000/login (peut causer des problèmes de session)

3. **Vérifier les routes**
   ```bash
   php bin/console debug:router | grep login
   # Devrait afficher: app_login  ANY  /login
   ```

4. **Vérifier les logs**
   ```bash
   tail -f var/log/dev.log
   ```

## ❌ Erreur: "Invalid credentials" lors de la connexion

### Causes possibles

1. **Mot de passe incorrect**
   - Les mots de passe sont hashés avec bcrypt
   - Assurez-vous d'utiliser le bon mot de passe

2. **Compte non créé via l'application**
   - Si vous avez créé un utilisateur manuellement en SQL, le mot de passe doit être hashé

3. **Solution: Créer un nouveau compte**
   ```bash
   # Aller sur /register et créer un nouveau compte
   # Ou réinitialiser le mot de passe d'un compte existant
   ```

### Créer un compte test avec mot de passe connu

```bash
# Via console Symfony (créer un nouveau fichier temporaire)
php -r "echo password_hash('password123', PASSWORD_BCRYPT);"
# Copier le hash généré (exemple: $2y$10$...)

# Puis insérer dans la DB
php bin/console dbal:run-sql "UPDATE user SET password = '\$2y\$10\$hash_ici' WHERE email = 'votre@email.com'"
```

## ❌ Erreur: "Access denied for user 'root'@'localhost'"

### Cause
MySQL MAMP n'est pas démarré ou les identifiants sont incorrects.

### Solution

1. **Démarrer MAMP**
   - Ouvrir l'application MAMP
   - Cliquer sur "Start Servers"
   - Attendre que les voyants Apache et MySQL soient verts

2. **Vérifier le port MySQL**
   ```bash
   # Le port par défaut MAMP est 8889, pas 3306
   # Vérifier dans .env.local:
   cat .env.local | grep DATABASE_URL
   # Devrait contenir: 127.0.0.1:8889
   ```

3. **Tester la connexion**
   ```bash
   /Applications/MAMP/Library/bin/mysql -uroot -proot -h127.0.0.1 -P8889
   # Si ça marche, taper: exit
   ```

## ❌ Erreur: Le CSS Tailwind ne charge pas (page sans style)

### Solution

```bash
# 1. Recompiler Tailwind
npm run build

# 2. Vérifier que le fichier existe
ls -lh public/build/app.css
# Devrait afficher ~12KB

# 3. Vider le cache navigateur
# Cmd+Shift+R (Mac) ou Ctrl+Shift+R (Windows)

# 4. Vérifier dans le HTML source
# Chercher: <link rel="stylesheet" href="/build/app.css">
```

## ❌ Erreur: Upload de photo ne fonctionne pas

### Vérifications

1. **Le dossier existe**
   ```bash
   ls -la /Applications/MAMP/htdocs/agrilink/uploads/profiles/
   ```

2. **Les permissions sont bonnes (755)**
   ```bash
   chmod -R 755 /Applications/MAMP/htdocs/agrilink/uploads/
   ```

3. **Le chemin dans .env.local est correct**
   ```bash
   cat .env.local | grep UPLOADS_BASE_DIR
   # Devrait correspondre à votre installation MAMP
   ```

4. **Tester manuellement**
   ```bash
   # Créer un fichier test
   touch /Applications/MAMP/htdocs/agrilink/uploads/profiles/test.txt
   
   # Si erreur "Permission denied", problème de permissions
   ```

## ❌ Erreur 500: "An exception occurred"

### Solution

1. **Activer le mode debug (normalement déjà actif en dev)**
   ```bash
   # Dans .env ou .env.local
   APP_ENV=dev
   ```

2. **Vider le cache**
   ```bash
   php bin/console cache:clear
   ```

3. **Consulter les logs**
   ```bash
   tail -50 var/log/dev.log
   ```

4. **Vérifier la base de données**
   ```bash
   php bin/console doctrine:schema:validate
   ```

## ❌ Page blanche sans erreur

### Solution

1. **Vérifier les logs PHP**
   ```bash
   # Logs MAMP
   tail -f /Applications/MAMP/logs/php_error.log
   ```

2. **Augmenter memory_limit**
   - Éditer php.ini dans MAMP
   - memory_limit = 256M

3. **Désactiver OPcache (temporairement)**
   - Dans php.ini MAMP
   - opcache.enable = 0

## 🔍 Commandes de diagnostic

### Vérifier l'installation complète

```bash
cd /Users/jasseryahyaoui/Desktop/PI_dev_symfony

# 1. Version PHP
php -v
# Devrait être >= 8.4

# 2. Extensions PHP requises
php -m | grep -E "(pdo|mysql|json|mbstring)"

# 3. Composer installé
composer --version

# 4. Node.js installé
node --version

# 5. Base de données accessible
php bin/console dbal:run-sql "SELECT 1"

# 6. Migrations exécutées
php bin/console doctrine:migrations:status

# 7. Routes enregistrées
php bin/console debug:router | wc -l
# Devrait afficher ~27

# 8. Services disponibles
php bin/console debug:container FileUploader

# 9. Templates valides
php bin/console lint:twig templates/

# 10. Cache writable
ls -la var/cache/
```

## 📞 Besoin d'aide supplémentaire ?

Si aucune de ces solutions ne fonctionne :

1. **Copier le message d'erreur complet** depuis `var/log/dev.log`
2. **Vérifier la console navigateur** (F12 → Console)
3. **Faire un screenshot** de l'erreur
4. **Consulter la documentation Symfony** : https://symfony.com/doc/current/

---

**Dernière mise à jour**: 22 Mars 2026  
**Version**: 1.0.0
