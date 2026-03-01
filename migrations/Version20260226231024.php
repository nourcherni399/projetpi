<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226231024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE module_completion (id INT AUTO_INCREMENT NOT NULL, completed_at DATETIME NOT NULL, user_id INT NOT NULL, module_id INT NOT NULL, quiz_attempt_id INT DEFAULT NULL, INDEX IDX_AD331CE3A76ED395 (user_id), INDEX IDX_AD331CE3AFC2B591 (module_id), INDEX IDX_AD331CE3F8FE9957 (quiz_attempt_id), UNIQUE INDEX uniq_user_module_completion (user_id, module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE module_quiz (id INT AUTO_INCREMENT NOT NULL, questions_json JSON NOT NULL, created_at DATETIME NOT NULL, module_id INT NOT NULL, INDEX IDX_1E2EBF9EAFC2B591 (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE module_quiz_attempt (id INT AUTO_INCREMENT NOT NULL, score_percent NUMERIC(5, 2) NOT NULL, passed TINYINT(1) NOT NULL, answers_json JSON NOT NULL, completed_at DATETIME NOT NULL, user_id INT NOT NULL, module_id INT NOT NULL, quiz_id INT NOT NULL, INDEX IDX_62E53734A76ED395 (user_id), INDEX IDX_62E53734AFC2B591 (module_id), INDEX IDX_62E53734853CD175 (quiz_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE module_completion ADD CONSTRAINT FK_AD331CE3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_completion ADD CONSTRAINT FK_AD331CE3AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_completion ADD CONSTRAINT FK_AD331CE3F8FE9957 FOREIGN KEY (quiz_attempt_id) REFERENCES module_quiz_attempt (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE module_quiz ADD CONSTRAINT FK_1E2EBF9EAFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_quiz_attempt ADD CONSTRAINT FK_62E53734A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_quiz_attempt ADD CONSTRAINT FK_62E53734AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_quiz_attempt ADD CONSTRAINT FK_62E53734853CD175 FOREIGN KEY (quiz_id) REFERENCES module_quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE date date DATE NOT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\')');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\'), CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\')');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_65E8AA0AEAA36C3A ON rendez_vous (token_annulation)');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\'), CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\')');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE module_completion DROP FOREIGN KEY FK_AD331CE3A76ED395');
        $this->addSql('ALTER TABLE module_completion DROP FOREIGN KEY FK_AD331CE3AFC2B591');
        $this->addSql('ALTER TABLE module_completion DROP FOREIGN KEY FK_AD331CE3F8FE9957');
        $this->addSql('ALTER TABLE module_quiz DROP FOREIGN KEY FK_1E2EBF9EAFC2B591');
        $this->addSql('ALTER TABLE module_quiz_attempt DROP FOREIGN KEY FK_62E53734A76ED395');
        $this->addSql('ALTER TABLE module_quiz_attempt DROP FOREIGN KEY FK_62E53734AFC2B591');
        $this->addSql('ALTER TABLE module_quiz_attempt DROP FOREIGN KEY FK_62E53734853CD175');
        $this->addSql('DROP TABLE module_completion');
        $this->addSql('DROP TABLE module_quiz');
        $this->addSql('DROP TABLE module_quiz_attempt');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE disponibilite CHANGE date date DATE DEFAULT \'2024-01-01\' NOT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL, CHANGE categorie categorie ENUM(\'\', \'COMPRENDRE_TSA\', \'AUTONOMIE\', \'COMMUNICATION\', \'EMOTIONS\', \'VIE_QUOTIDIENNE\', \'ACCOMPAGNEMENT\') NOT NULL');
        $this->addSql('ALTER TABLE produit CHANGE categorie categorie ENUM(\'sensoriels\', \'bruit_et_environnement\', \'education_apprentissage\', \'communication_langage\', \'jeux_therapeutiques_developpement\', \'bien_etre_relaxation\', \'vie_quotidienne\') DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_65E8AA0AEAA36C3A ON rendez_vous');
        $this->addSql('ALTER TABLE rendez_vous CHANGE status status ENUM(\'en_attente\', \'confirmer\', \'annuler\') DEFAULT NULL, CHANGE motif motif ENUM(\'urgence\', \'suivie\', \'normal\') DEFAULT NULL');
        $this->addSql('ALTER TABLE thematique CHANGE public_cible public_cible ENUM(\'Enfant\', \'Parent\', \'Médecin\', \'Éducateur\', \'Aidant\', \'Autre\') DEFAULT NULL, CHANGE niveau_difficulte niveau_difficulte ENUM(\'Débutant\', \'Intermédiaire\', \'Avancé\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT NULL');
    }
}
