<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224124235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE medecin_rating DROP FOREIGN KEY FK_MEDECIN_RATING_USER');
        $this->addSql('ALTER TABLE medecin_rating DROP FOREIGN KEY FK_MEDECIN_RATING_MEDECIN');
        $this->addSql('ALTER TABLE medecin_rating CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_medecin_rating_medecin ON medecin_rating');
        $this->addSql('CREATE INDEX IDX_4030A62F4F31A84 ON medecin_rating (medecin_id)');
        $this->addSql('DROP INDEX idx_medecin_rating_user ON medecin_rating');
        $this->addSql('CREATE INDEX IDX_4030A62FA76ED395 ON medecin_rating (user_id)');
        $this->addSql('ALTER TABLE medecin_rating ADD CONSTRAINT FK_MEDECIN_RATING_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE medecin_rating ADD CONSTRAINT FK_MEDECIN_RATING_MEDECIN FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous ADD token_annulation VARCHAR(64) DEFAULT NULL, CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_65E8AA0AEAA36C3A ON rendez_vous (token_annulation)');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE medecin_rating DROP FOREIGN KEY FK_4030A62F4F31A84');
        $this->addSql('ALTER TABLE medecin_rating DROP FOREIGN KEY FK_4030A62FA76ED395');
        $this->addSql('ALTER TABLE medecin_rating CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_4030a62f4f31a84 ON medecin_rating');
        $this->addSql('CREATE INDEX IDX_MEDECIN_RATING_MEDECIN ON medecin_rating (medecin_id)');
        $this->addSql('DROP INDEX idx_4030a62fa76ed395 ON medecin_rating');
        $this->addSql('CREATE INDEX IDX_MEDECIN_RATING_USER ON medecin_rating (user_id)');
        $this->addSql('ALTER TABLE medecin_rating ADD CONSTRAINT FK_4030A62F4F31A84 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE medecin_rating ADD CONSTRAINT FK_4030A62FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_65E8AA0AEAA36C3A ON rendez_vous');
        $this->addSql('ALTER TABLE rendez_vous DROP token_annulation, CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
