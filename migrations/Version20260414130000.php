<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add promo fields directly to produits and order promo trace to commandes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits ADD promo_code VARCHAR(40) DEFAULT NULL, ADD promo_discount_percent DOUBLE PRECISION DEFAULT NULL, ADD promo_active TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE commandes ADD promo_code_applied VARCHAR(40) DEFAULT NULL, ADD promo_discount_total DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits DROP promo_code, DROP promo_discount_percent, DROP promo_active');
        $this->addSql('ALTER TABLE commandes DROP promo_code_applied, DROP promo_discount_total');
    }
}
