<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Force la colonne user.role en type ENUM (MySQL) au lieu de VARCHAR.
 */
final class Version20260203120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Force user.role en ENUM (ROLE_ADMIN, ROLE_PARENT, ROLE_PATIENT, ROLE_MEDECIN, ROLE_USER)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user CHANGE role role ENUM('ROLE_ADMIN', 'ROLE_PARENT', 'ROLE_PATIENT', 'ROLE_MEDECIN', 'ROLE_USER') NOT NULL DEFAULT 'ROLE_USER'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user CHANGE role role VARCHAR(255) NOT NULL');
    }
}
