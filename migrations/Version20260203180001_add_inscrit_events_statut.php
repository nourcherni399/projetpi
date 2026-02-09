<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203180001_add_inscrit_events_statut extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add statut column to inscrit_events for admin accept/refuse.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inscrit_events ADD statut VARCHAR(20) DEFAULT 'en_attente' NOT NULL");
        $this->addSql("UPDATE inscrit_events SET statut = 'accepte' WHERE est_inscrit = 1");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscrit_events DROP statut');
    }
}
