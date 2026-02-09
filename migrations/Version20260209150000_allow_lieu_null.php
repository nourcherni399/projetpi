<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209150000AllowLieuNull extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autoriser NULL sur la colonne lieu de evenement (champ optionnel).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement CHANGE lieu lieu VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement CHANGE lieu lieu VARCHAR(255) NOT NULL');
    }
}
