<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421233500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add handwritten invoice signature path on commandes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes ADD factureSignaturePath VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes DROP factureSignaturePath');
    }
}
