<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix Doctrine Doctor Issues:
 * 1. Make createdBy NOT NULL in evenement and activite
 * 2. Add updatedAt to security_event
 */
final class Version20260429230323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix Doctrine Doctor Issues: Make createdBy NOT NULL and add updatedAt to security_event';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        foreach (['activite', 'evenement'] as $tableName) {
            $columns = $schemaManager->listTableColumns($tableName);

            if (!isset($columns['created_by_id'])) {
                $this->addSql(sprintf('ALTER TABLE %s ADD created_by_id INT DEFAULT NULL', $tableName));
            }

            if (!isset($columns['updated_by_id'])) {
                $this->addSql(sprintf('ALTER TABLE %s ADD updated_by_id INT DEFAULT NULL', $tableName));
            }
        }

        $firstUserId = $this->connection->fetchOne('SELECT id_utilisateur FROM user ORDER BY id_utilisateur ASC LIMIT 1');

        if ($firstUserId !== false) {
            $this->addSql(sprintf('UPDATE activite SET created_by_id = %d WHERE created_by_id IS NULL', (int) $firstUserId));
            $this->addSql(sprintf('UPDATE evenement SET created_by_id = %d WHERE created_by_id IS NULL', (int) $firstUserId));
            $this->addSql('ALTER TABLE activite MODIFY created_by_id INT NOT NULL');
            $this->addSql('ALTER TABLE evenement MODIFY created_by_id INT NOT NULL');
        } else {
            $this->write('Skipping NOT NULL enforcement for activite/evenement.created_by_id because no user rows exist yet.');
        }

        $securityEventColumns = $schemaManager->listTableColumns('security_event');

        if (!isset($securityEventColumns['updated_at'])) {
            $this->addSql('ALTER TABLE security_event ADD COLUMN updated_at DATETIME NULL AFTER created_at');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        foreach (['activite', 'evenement'] as $tableName) {
            $columns = $schemaManager->listTableColumns($tableName);

            if (isset($columns['created_by_id'])) {
                $this->addSql(sprintf('ALTER TABLE %s MODIFY created_by_id INT DEFAULT NULL', $tableName));
            }
        }

        $securityEventColumns = $schemaManager->listTableColumns('security_event');

        if (isset($securityEventColumns['updated_at'])) {
            $this->addSql('ALTER TABLE security_event DROP COLUMN updated_at');
        }
    }
}
