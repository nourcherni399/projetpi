<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223132310 extends AbstractMigration
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
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('DROP INDEX UNIQ_8D93D649AFD509CC ON user');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\'), CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL, CHANGE email_verified_at email_verified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_history DROP FOREIGN KEY FK_1E8A6F4AA76ED395');
        $this->addSql('ALTER TABLE user_history CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_1e8a6f4aa76ed395 ON user_history');
        $this->addSql('CREATE INDEX IDX_7FB76E41A76ED395 ON user_history (user_id)');
        $this->addSql('ALTER TABLE user_history ADD CONSTRAINT FK_1E8A6F4AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preference DROP FOREIGN KEY FK_3D44EA38A76ED395');
        $this->addSql('ALTER TABLE user_preference CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_3d44ea38a76ed395 ON user_preference');
        $this->addSql('CREATE INDEX IDX_FA0E76BFA76ED395 ON user_preference (user_id)');
        $this->addSql('ALTER TABLE user_preference ADD CONSTRAINT FK_3D44EA38A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL, CHANGE email_verification_expires_at email_verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE email_verified_at email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649AFD509CC ON user (email_verification_token)');
        $this->addSql('ALTER TABLE user_history DROP FOREIGN KEY FK_7FB76E41A76ED395');
        $this->addSql('ALTER TABLE user_history CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_7fb76e41a76ed395 ON user_history');
        $this->addSql('CREATE INDEX IDX_1E8A6F4AA76ED395 ON user_history (user_id)');
        $this->addSql('ALTER TABLE user_history ADD CONSTRAINT FK_7FB76E41A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preference DROP FOREIGN KEY FK_FA0E76BFA76ED395');
        $this->addSql('ALTER TABLE user_preference CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_fa0e76bfa76ed395 ON user_preference');
        $this->addSql('CREATE INDEX IDX_3D44EA38A76ED395 ON user_preference (user_id)');
        $this->addSql('ALTER TABLE user_preference ADD CONSTRAINT FK_FA0E76BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
