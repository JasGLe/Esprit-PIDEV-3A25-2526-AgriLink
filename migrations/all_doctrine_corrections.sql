-- =====================================================
-- MIGRATION SQL COMPLÈTE - Doctrine Doctor Corrections
-- Projet: AgriLink Symfony 6.4
-- Date: 2026-04-29
-- =====================================================

-- =====================================================
-- 1. TABLE: activite
-- Ajout des timestamps created_at et updated_at
-- =====================================================

-- Vérifier si la colonne existe déjà avant d'ajouter
SET @dbname = DATABASE();
SET @tablename = 'activite';

-- Ajout de created_at
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE activite ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "Column created_at already exists in activite" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de updated_at
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE activite ADD updated_at DATETIME DEFAULT NULL',
    'SELECT "Column updated_at already exists in activite" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. TABLE: evenement
-- Ajout des timestamps + correction FK id_organisateur_id
-- =====================================================

SET @tablename = 'evenement';

-- Ajout de created_at
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE evenement ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT "Column created_at already exists in evenement" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de updated_at
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE evenement ADD updated_at DATETIME DEFAULT NULL',
    'SELECT "Column updated_at already exists in evenement" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de id_organisateur_id (FK vers user)
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'id_organisateur_id') = 0,
    'ALTER TABLE evenement ADD id_organisateur_id INT DEFAULT NULL',
    'SELECT "Column id_organisateur_id already exists in evenement" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. AJOUT DES INDEX ET CLÉS ÉTRANGÈRES
-- =====================================================

-- Index pour id_organisateur_id sur evenement
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = 'evenement' 
     AND INDEX_NAME = 'IDX_EVENEMENT_ORGANISATEUR') = 0,
    'CREATE INDEX IDX_EVENEMENT_ORGANISATEUR ON evenement(id_organisateur_id)',
    'SELECT "Index IDX_EVENEMENT_ORGANISATEUR already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Clé étrangère FK vers user.id_utilisateur
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = 'evenement' 
     AND CONSTRAINT_NAME = 'FK_EVENEMENT_ORGANISATEUR') = 0,
    'ALTER TABLE evenement ADD CONSTRAINT FK_EVENEMENT_ORGANISATEUR FOREIGN KEY (id_organisateur_id) REFERENCES user(id_utilisateur) ON DELETE SET NULL',
    'SELECT "Foreign key FK_EVENEMENT_ORGANISATEUR already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. MISE À JOUR DES DONNÉES EXISTANTES (si nécessaire)
-- =====================================================

-- Mettre à jour les activités existantes sans date_debut
UPDATE activite SET date_debut = CURRENT_TIMESTAMP WHERE date_debut IS NULL;

-- Mettre à jour les événements existants sans date_evenement
UPDATE evenement SET date_evenement = CURRENT_TIMESTAMP WHERE date_evenement IS NULL;

-- Mettre à jour les timestamps pour les enregistrements existants
UPDATE activite SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL;
UPDATE evenement SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL;

-- =====================================================
-- 5. AJOUT DES COLONNES BLAMEABLE (created_by_id, updated_by_id)
-- =====================================================

-- Ajout de created_by_id et updated_by_id sur activite
SET @tablename = 'activite';

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'created_by_id') = 0,
    'ALTER TABLE activite ADD created_by_id INT DEFAULT NULL',
    'SELECT "Column created_by_id already exists in activite" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'updated_by_id') = 0,
    'ALTER TABLE activite ADD updated_by_id INT DEFAULT NULL',
    'SELECT "Column updated_by_id already exists in activite" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ajout de created_by_id et updated_by_id sur evenement
SET @tablename = 'evenement';

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'created_by_id') = 0,
    'ALTER TABLE evenement ADD created_by_id INT DEFAULT NULL',
    'SELECT "Column created_by_id already exists in evenement" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND COLUMN_NAME = 'updated_by_id') = 0,
    'ALTER TABLE evenement ADD updated_by_id INT DEFAULT NULL',
    'SELECT "Column updated_by_id already exists in evenement" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Création des clés étrangères pour created_by_id et updated_by_id
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = 'activite' 
     AND CONSTRAINT_NAME = 'FK_activite_created_by') = 0,
    'ALTER TABLE activite ADD CONSTRAINT FK_activite_created_by FOREIGN KEY (created_by_id) REFERENCES user(id_utilisateur) ON DELETE SET NULL',
    'SELECT "FK FK_activite_created_by already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = 'activite' 
     AND CONSTRAINT_NAME = 'FK_activite_updated_by') = 0,
    'ALTER TABLE activite ADD CONSTRAINT FK_activite_updated_by FOREIGN KEY (updated_by_id) REFERENCES user(id_utilisateur) ON DELETE SET NULL',
    'SELECT "FK FK_activite_updated_by already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = 'evenement' 
     AND CONSTRAINT_NAME = 'FK_evenement_created_by') = 0,
    'ALTER TABLE evenement ADD CONSTRAINT FK_evenement_created_by FOREIGN KEY (created_by_id) REFERENCES user(id_utilisateur) ON DELETE SET NULL',
    'SELECT "FK FK_evenement_created_by already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
     WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = 'evenement' 
     AND CONSTRAINT_NAME = 'FK_evenement_updated_by') = 0,
    'ALTER TABLE evenement ADD CONSTRAINT FK_evenement_updated_by FOREIGN KEY (updated_by_id) REFERENCES user(id_utilisateur) ON DELETE SET NULL',
    'SELECT "FK FK_evenement_updated_by already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- VÉRIFICATIONS
-- =====================================================

-- Vérifier la structure de la table activite
DESCRIBE activite;

-- Vérifier la structure de la table evenement
DESCRIBE evenement;

-- Vérifier les clés étrangères sur evenement
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'evenement'
AND REFERENCED_TABLE_NAME IS NOT NULL;
