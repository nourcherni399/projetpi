<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209105747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_history (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, utilisateur VARCHAR(255) NOT NULL, action VARCHAR(255) NOT NULL, module VARCHAR(255) DEFAULT NULL, details LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ressource (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, type_ressource VARCHAR(50) DEFAULT NULL, fichier VARCHAR(255) DEFAULT NULL, contenu VARCHAR(255) DEFAULT NULL, date_creation DATETIME NOT NULL, datemodif DATETIME NOT NULL, ordre INT DEFAULT NULL, is_active TINYINT(1) NOT NULL, module_id INT NOT NULL, INDEX IDX_939F4544AFC2B591 (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE ressource ADD CONSTRAINT FK_939F4544AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id)');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE commentaire CHANGE contenu contenu LONGTEXT NOT NULL, CHANGE date_modif date_modif DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\')');
        $this->addSql('ALTER TABLE module ADD categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL, CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA90E0F362');
        $this->addSql('DROP INDEX IDX_BF5476CA90E0F362 ON notification');
        $this->addSql('ALTER TABLE notification DROP utilisateur_concerne_id');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ressource DROP FOREIGN KEY FK_939F4544AFC2B591');
        $this->addSql('DROP TABLE action_history');
        $this->addSql('DROP TABLE ressource');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BCA76ED395');
        $this->addSql('ALTER TABLE commentaire CHANGE contenu contenu VARCHAR(255) NOT NULL, CHANGE date_modif date_modif DATETIME NOT NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE module DROP categorie, CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE notification ADD utilisateur_concerne_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA90E0F362 FOREIGN KEY (utilisateur_concerne_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BF5476CA90E0F362 ON notification (utilisateur_concerne_id)');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
