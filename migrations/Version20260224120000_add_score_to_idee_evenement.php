<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224120000_add_score_to_idee_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne score (0-100) à idee_evenement pour les propositions IA avec analyse.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE idee_evenement ADD score INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE idee_evenement DROP score');
    }
}
