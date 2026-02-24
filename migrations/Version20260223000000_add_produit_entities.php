<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add produit columns (sku, statut_publication, note_moyenne, nb_avis, seuil_alerte),
 * create produit_image, produit_historique, avis_produit tables,
 * add produit_id and TYPE_ALERTE_STOCK support to notification.
 */
final class Version20260223000000_add_produit_entities extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add produit columns, create ProduitImage, ProduitHistorique, AvisProduit, update Notification with produit';
    }

    public function up(Schema $schema): void
    {
        // 1. Add columns to produit
        $this->addSql('ALTER TABLE produit ADD sku VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE produit ADD statut_publication VARCHAR(20) DEFAULT 'brouillon' NOT NULL");
        $this->addSql('ALTER TABLE produit ADD note_moyenne DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE produit ADD nb_avis INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE produit ADD seuil_alerte INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_29A5EC27F9038C4 ON produit (sku)');

        // 2. Create produit_image
        $this->addSql('CREATE TABLE produit_image (id INT AUTO_INCREMENT NOT NULL, produit_id INT NOT NULL, chemin VARCHAR(500) NOT NULL, ordre INT NOT NULL, INDEX IDX_produit_image_produit (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE produit_image ADD CONSTRAINT FK_produit_image_produit FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');

        // 3. Create produit_historique
        $this->addSql('CREATE TABLE produit_historique (id INT AUTO_INCREMENT NOT NULL, produit_id INT NOT NULL, user_id INT NOT NULL, champ VARCHAR(255) NOT NULL, ancienne_valeur VARCHAR(500) DEFAULT NULL, nouvelle_valeur VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_produit_hist_produit (produit_id), INDEX IDX_produit_hist_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE produit_historique ADD CONSTRAINT FK_produit_hist_produit FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE produit_historique ADD CONSTRAINT FK_produit_hist_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        // 4. Create avis_produit
        $this->addSql('CREATE TABLE avis_produit (id INT AUTO_INCREMENT NOT NULL, produit_id INT NOT NULL, user_id INT NOT NULL, note INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX avis_produit_user_unique (produit_id, user_id), INDEX IDX_avis_produit_produit (produit_id), INDEX IDX_avis_produit_user (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE avis_produit ADD CONSTRAINT FK_avis_produit_produit FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis_produit ADD CONSTRAINT FK_avis_produit_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');

        // 5. Add produit_id to notification
        $this->addSql('ALTER TABLE notification ADD produit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_E0479AA1F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_E0479AA1F347EFB ON notification (produit_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_E0479AA1F347EFB');
        $this->addSql('DROP INDEX IDX_E0479AA1F347EFB ON notification');
        $this->addSql('ALTER TABLE notification DROP produit_id');

        $this->addSql('ALTER TABLE avis_produit DROP FOREIGN KEY FK_avis_produit_produit');
        $this->addSql('ALTER TABLE avis_produit DROP FOREIGN KEY FK_avis_produit_user');
        $this->addSql('DROP TABLE avis_produit');

        $this->addSql('ALTER TABLE produit_historique DROP FOREIGN KEY FK_produit_hist_produit');
        $this->addSql('ALTER TABLE produit_historique DROP FOREIGN KEY FK_produit_hist_user');
        $this->addSql('DROP TABLE produit_historique');

        $this->addSql('ALTER TABLE produit_image DROP FOREIGN KEY FK_produit_image_produit');
        $this->addSql('DROP TABLE produit_image');

        $this->addSql('DROP INDEX UNIQ_29A5EC27F9038C4 ON produit');
        $this->addSql('ALTER TABLE produit DROP sku, DROP statut_publication, DROP note_moyenne, DROP nb_avis, DROP seuil_alerte');
    }
}
