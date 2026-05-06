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
        // First, check for NULL values and handle them gracefully
        // For evenement: Set created_by_id to a default user (ID 1) if NULL
        $this->addSql('UPDATE evenement SET created_by_id = COALESCE(created_by_id, 1) WHERE created_by_id IS NULL');

        // For activite: Set created_by_id to a default user (ID 1) if NULL
        $this->addSql('UPDATE activite SET created_by_id = COALESCE(created_by_id, 1) WHERE created_by_id IS NULL');

        // Now make created_by_id NOT NULL in evenement
        $this->addSql('ALTER TABLE evenement CHANGE created_by_id created_by_id INT NOT NULL');

        // Make created_by_id NOT NULL in activite
        $this->addSql('ALTER TABLE activite CHANGE created_by_id created_by_id INT NOT NULL');

        // Add updated_at to security_event if it doesn't exist
        $this->addSql('ALTER TABLE security_event ADD COLUMN updated_at DATETIME NULL AFTER created_at');
    }

    public function down(Schema $schema): void
    {
        // Revert: Make created_by_id nullable in evenement
        $this->addSql('ALTER TABLE evenement CHANGE created_by_id created_by_id INT DEFAULT NULL');

        // Revert: Make created_by_id nullable in activite
        $this->addSql('ALTER TABLE activite CHANGE created_by_id created_by_id INT DEFAULT NULL');

        // Remove updated_at from security_event
        $this->addSql('ALTER TABLE security_event DROP COLUMN updated_at');
    }
}
