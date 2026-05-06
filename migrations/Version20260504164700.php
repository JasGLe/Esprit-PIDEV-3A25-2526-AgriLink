<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504164700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add marketplace_user_event table for recommendation tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE marketplace_user_event (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, event_type VARCHAR(30) NOT NULL, product_id INT DEFAULT NULL, query_text VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', meta_json JSON DEFAULT NULL, INDEX idx_mue_user_created (user_id, created_at), INDEX idx_mue_type_created (event_type, created_at), INDEX idx_mue_product_created (product_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE marketplace_user_event');
    }
}

