<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218173724 extends AbstractMigration
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
        $this->addSql('DROP INDEX uniq_user_article_favori ON favoris_article');
        $this->addSql('ALTER TABLE favoris_article DROP FOREIGN KEY FK_2F1D01BFDAE07E97');
        $this->addSql('ALTER TABLE favoris_article DROP FOREIGN KEY FK_2F1D01BFA76ED395');
        $this->addSql('ALTER TABLE favoris_article CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_2f1d01bfa76ed395 ON favoris_article');
        $this->addSql('CREATE INDEX IDX_B7D0A763A76ED395 ON favoris_article (user_id)');
        $this->addSql('DROP INDEX idx_2f1d01bfdae07e97 ON favoris_article');
        $this->addSql('CREATE INDEX IDX_B7D0A763DAE07E97 ON favoris_article (blog_id)');
        $this->addSql('ALTER TABLE favoris_article ADD CONSTRAINT FK_2F1D01BFDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favoris_article ADD CONSTRAINT FK_2F1D01BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
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
        $this->addSql('ALTER TABLE disponibilite CHANGE jour jour ENUM(\'lundi\', \'mardi\', \'mercredi\', \'jeudi\', \'vendredi\', \'samedi\', \'dimanche\') DEFAULT NULL');
        $this->addSql('ALTER TABLE favoris_article DROP FOREIGN KEY FK_B7D0A763A76ED395');
        $this->addSql('ALTER TABLE favoris_article DROP FOREIGN KEY FK_B7D0A763DAE07E97');
        $this->addSql('ALTER TABLE favoris_article CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_article_favori ON favoris_article (user_id, blog_id)');
        $this->addSql('DROP INDEX idx_b7d0a763a76ed395 ON favoris_article');
        $this->addSql('CREATE INDEX IDX_2F1D01BFA76ED395 ON favoris_article (user_id)');
        $this->addSql('DROP INDEX idx_b7d0a763dae07e97 ON favoris_article');
        $this->addSql('CREATE INDEX IDX_2F1D01BFDAE07E97 ON favoris_article (blog_id)');
        $this->addSql('ALTER TABLE favoris_article ADD CONSTRAINT FK_B7D0A763A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favoris_article ADD CONSTRAINT FK_B7D0A763DAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
