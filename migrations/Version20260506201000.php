<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure blameable columns are nullable in all tables';
    }

    public function up(Schema $schema): void
    {
        // Ensure user table columns are nullable
        $this->addSql('ALTER TABLE user MODIFY COLUMN created_by_id INT NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE user MODIFY COLUMN updated_by_id INT NULL DEFAULT NULL');

        // Ensure security_event table columns are nullable
        $this->addSql('ALTER TABLE security_event MODIFY COLUMN created_by_id INT NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE security_event MODIFY COLUMN updated_by_id INT NULL DEFAULT NULL');

        // Ensure user_session table columns are nullable
        $this->addSql('ALTER TABLE user_session MODIFY COLUMN created_by_id INT NULL DEFAULT NULL');
        $this->addSql('ALTER TABLE user_session MODIFY COLUMN updated_by_id INT NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Not reversible
    }
}
