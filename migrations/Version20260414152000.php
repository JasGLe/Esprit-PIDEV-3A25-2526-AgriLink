<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add equipment rental fields on produits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits ADD is_rental TINYINT(1) NOT NULL DEFAULT 0, ADD rental_price_per_day DOUBLE PRECISION DEFAULT NULL, ADD rental_description VARCHAR(800) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits DROP is_rental, DROP rental_price_per_day, DROP rental_description');
    }
}
