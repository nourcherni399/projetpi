<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplacer icone par sous_titre sur thematique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique DROP COLUMN icone');
        $this->addSql('ALTER TABLE thematique ADD sous_titre VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique DROP COLUMN sous_titre');
        $this->addSql('ALTER TABLE thematique ADD icone VARCHAR(100) DEFAULT NULL');
    }
}
