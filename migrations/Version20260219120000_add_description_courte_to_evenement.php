<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219120000_add_description_courte_to_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute description_courte (En bref) à evenement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD description_courte VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP description_courte');
    }
}
