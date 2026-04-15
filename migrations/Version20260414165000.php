<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rental_requests table for equipment rental demand flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rental_requests (id INT AUTO_INCREMENT NOT NULL, produit_id INT NOT NULL, vendeur_id INT NOT NULL, locataire_id INT NOT NULL, full_name VARCHAR(200) NOT NULL, email VARCHAR(150) NOT NULL, phone VARCHAR(30) NOT NULL, date_naissance DATE DEFAULT NULL, address VARCHAR(255) NOT NULL, identity_number VARCHAR(80) NOT NULL, rental_start_at DATETIME NOT NULL, rental_end_at DATETIME NOT NULL, rental_duration_hours DOUBLE PRECISION NOT NULL, payment_method VARCHAR(40) NOT NULL, usage_location VARCHAR(255) NOT NULL, transport_responsibility VARCHAR(30) NOT NULL, agree_damage TINYINT(1) NOT NULL, agree_loss TINYINT(1) NOT NULL, agree_theft TINYINT(1) NOT NULL, agree_repair_replacement TINYINT(1) NOT NULL, equipment_condition_notes VARCHAR(500) DEFAULT NULL, equipment_condition_photo VARCHAR(255) DEFAULT NULL, late_return_penalty VARCHAR(500) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rental_requests');
    }
}
