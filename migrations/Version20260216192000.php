<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les colonnes image, reset_pin et reset_pin_expires_at à la table user.
 */
final class Version20260216192000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image, reset_pin, reset_pin_expires_at columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD image VARCHAR(255) DEFAULT NULL, ADD reset_pin VARCHAR(6) DEFAULT NULL, ADD reset_pin_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP image, DROP reset_pin, DROP reset_pin_expires_at');
    }
}
