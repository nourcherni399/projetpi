<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216150000_add_reset_pin_to_user extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute reset_pin et reset_pin_expires_at à la table user pour la récupération de mot de passe.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD reset_pin VARCHAR(6) DEFAULT NULL, ADD reset_pin_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP reset_pin, DROP reset_pin_expires_at');
    }
}
