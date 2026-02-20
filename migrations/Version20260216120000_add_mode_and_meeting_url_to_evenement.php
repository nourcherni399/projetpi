<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216120000_add_mode_and_meeting_url_to_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute mode (présentiel/en_ligne/hybride) et meeting_url (lien Zoom) à evenement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD mode VARCHAR(20) DEFAULT \'presentiel\' NOT NULL, ADD meeting_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP mode, DROP meeting_url');
    }
}
