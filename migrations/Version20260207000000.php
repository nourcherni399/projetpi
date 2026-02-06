<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ image à la table thematique (image à un sens par thématique).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique ADD image VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique DROP image');
    }
}
