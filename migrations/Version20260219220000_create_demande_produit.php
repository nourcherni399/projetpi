<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée uniquement la table demande_produit (fiches IA en attente de validation).
 */
final class Version20260219220000_create_demande_produit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create demande_produit table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demande_produit (
            id INT AUTO_INCREMENT NOT NULL,
            demande_client LONGTEXT NOT NULL,
            nom VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            categorie VARCHAR(255) NOT NULL,
            prix_estime DOUBLE PRECISION NOT NULL,
            budget_client DOUBLE PRECISION DEFAULT NULL,
            caracteristiques LONGTEXT DEFAULT NULL,
            donnees_externes JSON DEFAULT NULL,
            statut VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            validated_at DATETIME DEFAULT NULL,
            demandeur_id INT DEFAULT NULL,
            validated_by_id INT DEFAULT NULL,
            produit_id INT DEFAULT NULL,
            INDEX IDX_1952578895A6EE59 (demandeur_id),
            INDEX IDX_19525788C69DE5E5 (validated_by_id),
            UNIQUE INDEX UNIQ_19525788F347EFB (produit_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE demande_produit ADD CONSTRAINT FK_1952578895A6EE59 FOREIGN KEY (demandeur_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_produit ADD CONSTRAINT FK_19525788C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_produit ADD CONSTRAINT FK_19525788F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_produit DROP FOREIGN KEY FK_1952578895A6EE59');
        $this->addSql('ALTER TABLE demande_produit DROP FOREIGN KEY FK_19525788C69DE5E5');
        $this->addSql('ALTER TABLE demande_produit DROP FOREIGN KEY FK_19525788F347EFB');
        $this->addSql('DROP TABLE demande_produit');
    }
}
