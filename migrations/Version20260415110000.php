<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legal-condition storage columns from rental_requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rental_requests DROP agree_damage, DROP agree_loss, DROP agree_theft, DROP agree_repair_replacement, DROP equipment_condition_notes, DROP equipment_condition_photo, DROP late_return_penalty');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rental_requests ADD agree_damage TINYINT(1) NOT NULL, ADD agree_loss TINYINT(1) NOT NULL, ADD agree_theft TINYINT(1) NOT NULL, ADD agree_repair_replacement TINYINT(1) NOT NULL, ADD equipment_condition_notes VARCHAR(500) DEFAULT NULL, ADD equipment_condition_photo VARCHAR(255) DEFAULT NULL, ADD late_return_penalty VARCHAR(500) NOT NULL');
    }
}
