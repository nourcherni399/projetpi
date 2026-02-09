<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajouter actif (visible sur le site) sur thematique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique ADD actif TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique DROP actif');
    }
}
