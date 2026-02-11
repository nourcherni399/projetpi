<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210120000_create_ressource_table extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table ressource (manquante).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ressource (
            id INT AUTO_INCREMENT NOT NULL,
            module_id INT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            type_ressource VARCHAR(50) DEFAULT NULL,
            fichier VARCHAR(255) DEFAULT NULL,
            contenu VARCHAR(255) DEFAULT NULL,
            date_creation DATETIME NOT NULL,
            datemodif DATETIME NOT NULL,
            ordre INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL,
            INDEX IDX_939F4544AFC2B591 (module_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ressource ADD CONSTRAINT FK_939F4544AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ressource DROP FOREIGN KEY FK_939F4544AFC2B591');
        $this->addSql('DROP TABLE ressource');
    }
}
