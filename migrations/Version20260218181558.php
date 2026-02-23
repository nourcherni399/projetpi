<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218181558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('DROP INDEX uniq_user_commentaire_reaction ON commentaire_reaction');
        $this->addSql('ALTER TABLE commentaire_reaction DROP FOREIGN KEY FK_CR_COMMENTAIRE');
        $this->addSql('ALTER TABLE commentaire_reaction DROP FOREIGN KEY FK_CR_USER');
        $this->addSql('ALTER TABLE commentaire_reaction ADD type VARCHAR(20) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_cr_user ON commentaire_reaction');
        $this->addSql('CREATE INDEX IDX_56C6CF2BA76ED395 ON commentaire_reaction (user_id)');
        $this->addSql('DROP INDEX idx_cr_commentaire ON commentaire_reaction');
        $this->addSql('CREATE INDEX IDX_56C6CF2BBA9CD190 ON commentaire_reaction (commentaire_id)');
        $this->addSql('ALTER TABLE commentaire_reaction ADD CONSTRAINT FK_CR_COMMENTAIRE FOREIGN KEY (commentaire_id) REFERENCES commentaire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire_reaction ADD CONSTRAINT FK_CR_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\')');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE commentaire_reaction DROP FOREIGN KEY FK_56C6CF2BA76ED395');
        $this->addSql('ALTER TABLE commentaire_reaction DROP FOREIGN KEY FK_56C6CF2BBA9CD190');
        $this->addSql('ALTER TABLE commentaire_reaction DROP type, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_commentaire_reaction ON commentaire_reaction (user_id, commentaire_id)');
        $this->addSql('DROP INDEX idx_56c6cf2bba9cd190 ON commentaire_reaction');
        $this->addSql('CREATE INDEX IDX_CR_COMMENTAIRE ON commentaire_reaction (commentaire_id)');
        $this->addSql('DROP INDEX idx_56c6cf2ba76ed395 ON commentaire_reaction');
        $this->addSql('CREATE INDEX IDX_CR_USER ON commentaire_reaction (user_id)');
        $this->addSql('ALTER TABLE commentaire_reaction ADD CONSTRAINT FK_56C6CF2BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire_reaction ADD CONSTRAINT FK_56C6CF2BBA9CD190 FOREIGN KEY (commentaire_id) REFERENCES commentaire (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
