<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210130000_add_date_rdv_to_rendez_vous extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne date_rdv à la table rendez_vous si elle n\'existe pas.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD date_rdv DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP date_rdv');
    }
}
