<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add progressive bans, product moderation fields and admin moderation notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `User` ADD ban_count INT DEFAULT 0 NOT NULL, ADD banned_until DATETIME DEFAULT NULL, ADD is_permanently_banned TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE produits ADD moderation_status VARCHAR(20) DEFAULT \'pending\' NOT NULL, ADD created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits DROP moderation_status, DROP created_at');
        $this->addSql('ALTER TABLE `User` DROP ban_count, DROP banned_until, DROP is_permanently_banned');
    }
}

