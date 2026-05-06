<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blameable columns to security_event and user_session tables';
    }

    public function up(Schema $schema): void
    {
        // Add blameable columns to security_event table
        $this->addSql('ALTER TABLE security_event ADD created_by_id INT NULL');
        $this->addSql('ALTER TABLE security_event ADD updated_by_id INT NULL');
        $this->addSql('ALTER TABLE security_event ADD CONSTRAINT FK_E16EACCCB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id_utilisateur) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE security_event ADD CONSTRAINT FK_E16EACCCAF7B7B17 FOREIGN KEY (updated_by_id) REFERENCES user (id_utilisateur) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E16EACCCB03A8386 ON security_event (created_by_id)');
        $this->addSql('CREATE INDEX IDX_E16EACCCAF7B7B17 ON security_event (updated_by_id)');

        // Add blameable columns to user_session table
        $this->addSql('ALTER TABLE user_session ADD created_by_id INT NULL');
        $this->addSql('ALTER TABLE user_session ADD updated_by_id INT NULL');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_51C6A0EEB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id_utilisateur) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_51C6A0EEAF7B7B17 FOREIGN KEY (updated_by_id) REFERENCES user (id_utilisateur) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_51C6A0EEB03A8386 ON user_session (created_by_id)');
        $this->addSql('CREATE INDEX IDX_51C6A0EEAF7B7B17 ON user_session (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop from user_session
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_51C6A0EEB03A8386');
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_51C6A0EEAF7B7B17');
        $this->addSql('DROP INDEX IDX_51C6A0EEB03A8386 ON user_session');
        $this->addSql('DROP INDEX IDX_51C6A0EEAF7B7B17 ON user_session');
        $this->addSql('ALTER TABLE user_session DROP COLUMN created_by_id, DROP COLUMN updated_by_id');

        // Drop from security_event
        $this->addSql('ALTER TABLE security_event DROP FOREIGN KEY FK_E16EACCCB03A8386');
        $this->addSql('ALTER TABLE security_event DROP FOREIGN KEY FK_E16EACCCAF7B7B17');
        $this->addSql('DROP INDEX IDX_E16EACCCB03A8386 ON security_event');
        $this->addSql('DROP INDEX IDX_E16EACCCAF7B7B17 ON security_event');
        $this->addSql('ALTER TABLE security_event DROP COLUMN created_by_id, DROP COLUMN updated_by_id');
    }
}
