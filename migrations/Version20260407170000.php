<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add FCM device tokens table for mobile push notifications';
    }

    public function up(Schema $schema): void
    {
        // Index on full utf8mb4 VARCHAR(255) can exceed 767 bytes (older MySQL / innodb). Prefix unique is enough.
        $this->addSql('CREATE TABLE fcm_device_tokens (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token VARCHAR(255) NOT NULL, platform VARCHAR(20) DEFAULT NULL, role_snapshot VARCHAR(30) DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_fcm_device_token (token(191)), INDEX idx_fcm_device_user_active (user_id, is_active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE fcm_device_tokens');
    }
}
