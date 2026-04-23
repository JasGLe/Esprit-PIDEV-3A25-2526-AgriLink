<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423101600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add biometric recognition fields (voice) to User entity';
    }

    public function up(Schema $schema): void
    {
        // Add voice recognition fields
        $this->addSql('ALTER TABLE `User` ADD voice_embedding LONGTEXT DEFAULT NULL, ADD voice_enrolled_at DATETIME DEFAULT NULL, ADD voice_enrollment_attempts INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `User` DROP voice_embedding, DROP voice_enrolled_at, DROP voice_enrollment_attempts');
    }
}
