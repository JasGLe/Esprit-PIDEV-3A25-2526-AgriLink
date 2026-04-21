<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery confirmation token for QR delivery flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes ADD deliveryConfirmationToken VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_commandes_delivery_token ON commandes (deliveryConfirmationToken)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_commandes_delivery_token ON commandes');
        $this->addSql('ALTER TABLE commandes DROP deliveryConfirmationToken');
    }
}
