<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize NULL culture.statut values to EN_ATTENTE and enforce default';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['culture'])) {
            $this->write('Skipping culture.statut normalization because culture table does not exist.');

            return;
        }

        $columns = $schemaManager->listTableColumns('culture');

        if (!isset($columns['statut'])) {
            $this->write('Skipping culture.statut normalization because statut column does not exist.');

            return;
        }

        $this->addSql("UPDATE culture SET statut = 'EN_ATTENTE' WHERE statut IS NULL OR statut = ''");
        $this->addSql("ALTER TABLE culture MODIFY statut VARCHAR(255) NOT NULL DEFAULT 'EN_ATTENTE'");
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['culture'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('culture');

        if (isset($columns['statut'])) {
            $this->addSql("ALTER TABLE culture MODIFY statut VARCHAR(255) DEFAULT 'EN_ATTENTE'");
        }
    }
}
