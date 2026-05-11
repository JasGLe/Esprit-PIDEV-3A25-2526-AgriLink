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
        $schemaManager = $this->connection->createSchemaManager();

        $this->modifyMoneyColumn($schemaManager->listTableColumns('produits'), 'produits', ['prixUnitaire', 'prix_unitaire'], 'DECIMAL(12, 3) NOT NULL');
        $this->modifyMoneyColumn($schemaManager->listTableColumns('ligne_commande'), 'ligne_commande', ['prixUnitaire', 'prix_unitaire'], 'DECIMAL(12, 3) NOT NULL');
        $this->modifyMoneyColumn($schemaManager->listTableColumns('annonce'), 'annonce', ['prixUnitaire', 'prix_unitaire'], 'DECIMAL(12, 3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $this->modifyMoneyColumn($schemaManager->listTableColumns('produits'), 'produits', ['prixUnitaire', 'prix_unitaire'], 'DOUBLE PRECISION NOT NULL');
        $this->modifyMoneyColumn($schemaManager->listTableColumns('ligne_commande'), 'ligne_commande', ['prixUnitaire', 'prix_unitaire'], 'DOUBLE PRECISION NOT NULL');
        $this->modifyMoneyColumn($schemaManager->listTableColumns('annonce'), 'annonce', ['prixUnitaire', 'prix_unitaire'], 'DOUBLE PRECISION DEFAULT NULL');
    }

    /**
     * @param array<string, \Doctrine\DBAL\Schema\Column> $columns
     * @param list<string> $candidates
     */
    private function modifyMoneyColumn(array $columns, string $tableName, array $candidates, string $definition): void
    {
        foreach ($candidates as $columnName) {
            if (isset($columns[$columnName])) {
                $this->addSql(sprintf('ALTER TABLE %s MODIFY %s %s', $tableName, $columnName, $definition));

                return;
            }
        }

        $this->write(sprintf(
            'Skipping %s monetary column migration because none of [%s] exist.',
            $tableName,
            implode(', ', $candidates)
        ));
    }
}
