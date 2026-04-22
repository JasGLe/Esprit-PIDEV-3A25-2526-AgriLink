<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gamification fields (login_count, user_points, earned_badges) to User table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `User` ADD login_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE `User` ADD user_points INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE `User` ADD earned_badges JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `User` DROP login_count');
        $this->addSql('ALTER TABLE `User` DROP user_points');
        $this->addSql('ALTER TABLE `User` DROP earned_badges');
    }
}
