<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ location_url sur evenement pour l\'URL de localisation (carte).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD location_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP location_url');
    }
}
