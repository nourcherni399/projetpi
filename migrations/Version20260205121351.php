<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205121351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL, is_published TINYINT(1) NOT NULL, image VARCHAR(255) NOT NULL, is_urgent TINYINT(1) DEFAULT NULL, is_visible TINYINT(1) NOT NULL, date_creation DATETIME NOT NULL, date_modif DATETIME NOT NULL, contenu VARCHAR(255) NOT NULL, module_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_C0155143AFC2B591 (module_id), INDEX IDX_C0155143A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE commentaire (id INT AUTO_INCREMENT NOT NULL, contenu VARCHAR(255) NOT NULL, is_published TINYINT(1) NOT NULL, date_creation DATETIME NOT NULL, date_modif DATETIME NOT NULL, blog_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_67F068BCDAE07E97 (blog_id), INDEX IDX_67F068BCA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE disponibilite (id INT AUTO_INCREMENT NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\'), duree INT DEFAULT 0 NOT NULL, est_dispo TINYINT(1) DEFAULT 1 NOT NULL, medecin_id INT DEFAULT NULL, INDEX IDX_2CBACE2F4F31A84 (medecin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_event DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, lieu VARCHAR(255) NOT NULL, thematique_id INT DEFAULT NULL, INDEX IDX_B26681E476556AF (thematique_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE inscrit_events (id INT AUTO_INCREMENT NOT NULL, date_inscrit DATE NOT NULL, est_inscrit TINYINT(1) DEFAULT 1 NOT NULL, user_id INT NOT NULL, evenement_id INT NOT NULL, INDEX IDX_8079EEFAA76ED395 (user_id), INDEX IDX_8079EEFAFD02F13 (evenement_id), UNIQUE INDEX UNIQ_USER_EVENT (user_id, evenement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE module (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, contenu VARCHAR(255) NOT NULL, niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, image VARCHAR(255) NOT NULL, is_published TINYINT(1) NOT NULL, date_creation DATETIME NOT NULL, date_modif DATETIME NOT NULL, admin_id INT DEFAULT NULL, INDEX IDX_C242628642B8210 (admin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE produit (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\'), prix DOUBLE PRECISION NOT NULL, disponibilite TINYINT(1) DEFAULT 1 NOT NULL, image VARCHAR(500) DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_29A5EC27A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, adresse VARCHAR(500) DEFAULT NULL, date_naissance DATE DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, note_patient LONGTEXT DEFAULT \'vide\', status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), motif ENUM(\'urgence\', \'suivie\', \'normal\'), medecin_id INT NOT NULL, disponibilite_id INT DEFAULT NULL, patient_id INT DEFAULT NULL, INDEX IDX_65E8AA0A4F31A84 (medecin_id), INDEX IDX_65E8AA0A2B9D6493 (disponibilite_id), INDEX IDX_65E8AA0A6B899279 (patient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE stock (id INT AUTO_INCREMENT NOT NULL, quantite INT DEFAULT 0 NOT NULL, produit_id INT NOT NULL, UNIQUE INDEX UNIQ_4B365660F347EFB (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE thematique (id INT AUTO_INCREMENT NOT NULL, nom_thematique VARCHAR(255) NOT NULL, code_thematique VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, couleur VARCHAR(20) DEFAULT NULL, icone VARCHAR(100) DEFAULT NULL, ordre SMALLINT DEFAULT NULL, public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\'), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, telephone INT NOT NULL, password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\'), type VARCHAR(255) NOT NULL, date_naissance DATE DEFAULT NULL, adresse VARCHAR(500) DEFAULT NULL, sexe VARCHAR(20) DEFAULT NULL, relation_avec_patient VARCHAR(100) DEFAULT NULL, specialite VARCHAR(255) DEFAULT NULL, nom_cabinet VARCHAR(255) DEFAULT NULL, adresse_cabinet VARCHAR(500) DEFAULT NULL, telephone_cabinet VARCHAR(30) DEFAULT NULL, tarif_consultation DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE blog ADD CONSTRAINT FK_C0155143AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id)');
        $this->addSql('ALTER TABLE blog ADD CONSTRAINT FK_C0155143A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE disponibilite ADD CONSTRAINT FK_2CBACE2F4F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E476556AF FOREIGN KEY (thematique_id) REFERENCES thematique (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inscrit_events ADD CONSTRAINT FK_8079EEFAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscrit_events ADD CONSTRAINT FK_8079EEFAFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module ADD CONSTRAINT FK_C242628642B8210 FOREIGN KEY (admin_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC27A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A4F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A2B9D6493 FOREIGN KEY (disponibilite_id) REFERENCES disponibilite (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog DROP FOREIGN KEY FK_C0155143AFC2B591');
        $this->addSql('ALTER TABLE blog DROP FOREIGN KEY FK_C0155143A76ED395');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCDAE07E97');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE disponibilite DROP FOREIGN KEY FK_2CBACE2F4F31A84');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E476556AF');
        $this->addSql('ALTER TABLE inscrit_events DROP FOREIGN KEY FK_8079EEFAA76ED395');
        $this->addSql('ALTER TABLE inscrit_events DROP FOREIGN KEY FK_8079EEFAFD02F13');
        $this->addSql('ALTER TABLE module DROP FOREIGN KEY FK_C242628642B8210');
        $this->addSql('ALTER TABLE produit DROP FOREIGN KEY FK_29A5EC27A76ED395');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A4F31A84');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A2B9D6493');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE stock DROP FOREIGN KEY FK_4B365660F347EFB');
        $this->addSql('DROP TABLE blog');
        $this->addSql('DROP TABLE commentaire');
        $this->addSql('DROP TABLE disponibilite');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE inscrit_events');
        $this->addSql('DROP TABLE module');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE stock');
        $this->addSql('DROP TABLE thematique');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
