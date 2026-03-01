<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225210000_add_date_to_disponibilite extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne date à la table disponibilite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite ADD COLUMN date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite DROP COLUMN date');
    }
}
