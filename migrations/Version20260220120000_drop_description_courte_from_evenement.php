<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime le champ "En bref" (description_courte) de la table evenement.
 */
final class Version20260220120000_drop_description_courte_from_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la colonne description_courte (En bref) de evenement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP COLUMN description_courte');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD description_courte VARCHAR(500) DEFAULT NULL');
    }
}
