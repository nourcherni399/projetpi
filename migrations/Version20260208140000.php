<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create commentaire table';
    }

    public function up(Schema $schema): void
    {
        // Créer la table commentaire seulement si elle n'existe pas
        $this->addSql('CREATE TABLE IF NOT EXISTS commentaire (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, is_published TINYINT(1) NOT NULL, date_creation DATETIME NOT NULL, date_modif DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, blog_id INT NOT NULL, INDEX IDX_67F068BCA76ED395 (user_id), INDEX IDX_67F068BCDAE07E97 (blog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        
        // Ajouter les contraintes de clé étrangère seulement si elles n'existent pas déjà
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les contraintes de clé étrangère
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCDAE07E97');
        
        // Supprimer la table
        $this->addSql('DROP TABLE IF EXISTS commentaire');
    }
}
