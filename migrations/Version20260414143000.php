<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add promo validity period dates on produits';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits ADD promo_start_at DATE DEFAULT NULL, ADD promo_end_at DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits DROP promo_start_at, DROP promo_end_at');
    }
}
