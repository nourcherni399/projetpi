<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220201231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module contenu: passer de VARCHAR(255) à TEXT pour supprimer la limite de caractères';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module CHANGE contenu contenu LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module CHANGE contenu contenu VARCHAR(255) NOT NULL');
    }
}
