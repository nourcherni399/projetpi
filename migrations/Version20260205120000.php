<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Public cible et niveau difficulté en ENUM pour thematique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE thematique MODIFY public_cible ENUM('enfant', 'parent') DEFAULT NULL");
        $this->addSql("ALTER TABLE thematique MODIFY niveau_difficulte ENUM('debutant', 'intermediaire', 'avance') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE thematique MODIFY public_cible VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique MODIFY niveau_difficulte VARCHAR(50) DEFAULT NULL');
    }
}
