<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506204100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make created_by_id NOT NULL again for activite and evenement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE activite SET created_by_id = (SELECT id_utilisateur FROM user ORDER BY id_utilisateur ASC LIMIT 1) WHERE created_by_id IS NULL');
        $this->addSql('UPDATE evenement SET created_by_id = (SELECT id_utilisateur FROM user ORDER BY id_utilisateur ASC LIMIT 1) WHERE created_by_id IS NULL');

        $this->addSql('ALTER TABLE activite MODIFY created_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE evenement MODIFY created_by_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activite MODIFY created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement MODIFY created_by_id INT DEFAULT NULL');
    }
}
