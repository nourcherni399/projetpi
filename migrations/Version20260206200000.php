<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre le champ lieu de evenement nullable (optionnel).';
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
