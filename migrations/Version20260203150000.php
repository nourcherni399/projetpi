<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de la relation User OneToMany Produit (colonne user_id sur produit).
 */
final class Version20260203150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout relation User OneToMany Produit (user_id sur produit)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produit ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC27A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_29A5EC27A76ED395 ON produit (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produit DROP FOREIGN KEY FK_29A5EC27A76ED395');
        $this->addSql('DROP INDEX IDX_29A5EC27A76ED395 ON produit');
        $this->addSql('ALTER TABLE produit DROP user_id');
    }
}
