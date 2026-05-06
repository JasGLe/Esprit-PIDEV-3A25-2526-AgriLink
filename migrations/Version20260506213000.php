<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert monetary prixUnitaire columns from float to decimal';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits MODIFY prixUnitaire DECIMAL(12, 3) NOT NULL');
        $this->addSql('ALTER TABLE ligne_commande MODIFY prixUnitaire DECIMAL(12, 3) NOT NULL');
        $this->addSql('ALTER TABLE annonce MODIFY prixUnitaire DECIMAL(12, 3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits MODIFY prixUnitaire DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE ligne_commande MODIFY prixUnitaire DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE annonce MODIFY prixUnitaire DOUBLE PRECISION DEFAULT NULL');
    }
}
