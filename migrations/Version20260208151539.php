<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208151539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\')');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE notification ADD utilisateur_concerne_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA90E0F362 FOREIGN KEY (utilisateur_concerne_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BF5476CA90E0F362 ON notification (utilisateur_concerne_id)');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user DROP sms_verification_pin, DROP sms_pin_expires_at, DROP sms_sent_at, DROP sms_attempts, CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA90E0F362');
        $this->addSql('DROP INDEX IDX_BF5476CA90E0F362 ON notification');
        $this->addSql('ALTER TABLE notification DROP utilisateur_concerne_id');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD sms_verification_pin VARCHAR(6) DEFAULT NULL, ADD sms_pin_expires_at DATETIME DEFAULT NULL, ADD sms_sent_at DATETIME DEFAULT NULL, ADD sms_attempts INT DEFAULT NULL, CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
