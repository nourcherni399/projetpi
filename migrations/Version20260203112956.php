<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203112956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modifie les champs ENUM des tables blog, module et user';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql("ALTER TABLE user CHANGE role role ENUM('ROLE_ADMIN','ROLE_PARENT','ROLE_MEDECIN','ROLE_USER') NOT NULL DEFAULT 'ROLE_USER'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau VARCHAR(255) NOT NULL');
        $this->addSql("ALTER TABLE user CHANGE role role VARCHAR(255) NOT NULL");
    }
}