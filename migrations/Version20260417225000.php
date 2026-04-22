<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417225000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional audio path to forum messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD audio_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP audio_path');
    }
}
