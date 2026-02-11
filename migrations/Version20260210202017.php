<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210202017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BA388B7A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prix DOUBLE PRECISION NOT NULL, cart_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE2527F347EFB (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, adresse VARCHAR(255) NOT NULL, code_postal VARCHAR(20) NOT NULL, ville VARCHAR(100) NOT NULL, total DOUBLE PRECISION NOT NULL, statut VARCHAR(50) NOT NULL, mode_payment VARCHAR(50) NOT NULL, date_creation DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_6EEAA67DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE favoris (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_8933C432A76ED395 (user_id), INDEX IDX_8933C432F347EFB (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ligne_commande (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prix DOUBLE PRECISION NOT NULL, sous_total DOUBLE PRECISION NOT NULL, commande_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_3170B74B82EA2E54 (commande_id), INDEX IDX_3170B74BF347EFB (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, total_price DOUBLE PRECISION NOT NULL, status VARCHAR(50) NOT NULL, payment_method VARCHAR(50) NOT NULL, card_type VARCHAR(50) DEFAULT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, postal_code VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_F5299398A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prix DOUBLE PRECISION NOT NULL, order_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F09F347EFB (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE2527F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE favoris ADD CONSTRAINT FK_8933C432A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favoris ADD CONSTRAINT FK_8933C432F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCDAE07E97');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE commentaire CHANGE contenu contenu LONGTEXT NOT NULL, CHANGE date_modif date_modif DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\')');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14F42A439');
        $this->addSql('ALTER TABLE note CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_cfbdfa14f42a439 ON note');
        $this->addSql('CREATE INDEX IDX_CFBDFA144F31A84 ON note (medecin_id)');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14F42A439 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user DROP confirmation_pin, DROP confirmation_pin_expires_at, DROP discr, CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B7A76ED395');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE2527F347EFB');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DA76ED395');
        $this->addSql('ALTER TABLE favoris DROP FOREIGN KEY FK_8933C432A76ED395');
        $this->addSql('ALTER TABLE favoris DROP FOREIGN KEY FK_8933C432F347EFB');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09F347EFB');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE favoris');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCDAE07E97');
        $this->addSql('ALTER TABLE commentaire CHANGE contenu contenu VARCHAR(255) NOT NULL, CHANGE date_modif date_modif DATETIME NOT NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id)');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA144F31A84');
        $this->addSql('ALTER TABLE note CHANGE date_creation date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_cfbdfa144f31a84 ON note');
        $this->addSql('CREATE INDEX IDX_CFBDFA14F42A439 ON note (medecin_id)');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA144F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD confirmation_pin VARCHAR(10) DEFAULT NULL, ADD confirmation_pin_expires_at DATETIME DEFAULT NULL, ADD discr VARCHAR(255) DEFAULT \'patient\' NOT NULL, CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
