<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260202160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Transform user.role VARCHAR to ENUM';
    }

    public function up(Schema $schema): void
    {
        // Transformer le champ role en ENUM
        $this->addSql("
            ALTER TABLE user 
            CHANGE role role ENUM('ROLE_ADMIN','ROLE_PARENT','ROLE_MEDECIN','ROLE_USER') 
            NOT NULL DEFAULT 'ROLE_USER'
        ");
    }

    public function down(Schema $schema): void
    {
        // Revenir à VARCHAR si nécessaire
        $this->addSql("ALTER TABLE user CHANGE role role VARCHAR(255) NOT NULL");
    }
}
