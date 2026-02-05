<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Étendre public_cible : medecin, educateur, aidant, autre';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE thematique MODIFY public_cible ENUM('enfant', 'parent', 'medecin', 'educateur', 'aidant', 'autre') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE thematique MODIFY public_cible ENUM('enfant', 'parent') DEFAULT NULL");
    }
}
