<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206153545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, date_creation DATETIME NOT NULL, medecin_id INT NOT NULL, patient_id INT NOT NULL, INDEX IDX_CFBDFA144F31A84 (medecin_id), INDEX IDX_CFBDFA146B899279 (patient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, lu TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, destinataire_id INT NOT NULL, rendez_vous_id INT DEFAULT NULL, INDEX IDX_BF5476CAA4F84F6E (destinataire_id), INDEX IDX_BF5476CA91EF7EAA (rendez_vous_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA144F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA146B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA91EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\')');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous ADD date_rdv DATE DEFAULT NULL, CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('ALTER TABLE thematique ADD sous_titre VARCHAR(255) DEFAULT NULL, ADD actif TINYINT(1) DEFAULT 1 NOT NULL, DROP icone, CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA144F31A84');
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA146B899279');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA4F84F6E');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA91EF7EAA');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE notification');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous DROP date_rdv, CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique ADD icone VARCHAR(100) DEFAULT NULL, DROP sous_titre, DROP actif, CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
