<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220140000_create_idee_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table idee_evenement pour les idées d\'événements proposées par l\'IA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE idee_evenement (
            id INT AUTO_INCREMENT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            theme VARCHAR(100) DEFAULT NULL,
            pourquoi VARCHAR(500) DEFAULT NULL,
            mots_cle VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE idee_evenement');
    }
}
