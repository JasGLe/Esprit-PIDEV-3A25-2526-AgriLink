<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412221421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add security fields to user: pending_email, is_banned, banned_at, ban_reason, backup_codes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD pending_email VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD is_banned TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE user ADD banned_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD ban_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD backup_codes JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP pending_email');
        $this->addSql('ALTER TABLE user DROP is_banned');
        $this->addSql('ALTER TABLE user DROP banned_at');
        $this->addSql('ALTER TABLE user DROP ban_reason');
        $this->addSql('ALTER TABLE user DROP backup_codes');
    }
}
