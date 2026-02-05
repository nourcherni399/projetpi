<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre medecin_id nullable dans la table disponibilite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite MODIFY medecin_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite MODIFY medecin_id INT NOT NULL');
    }
}
