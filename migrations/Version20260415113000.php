<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove trailing status and created_at columns from rental_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rental_requests DROP status, DROP created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rental_requests ADD status VARCHAR(20) NOT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
