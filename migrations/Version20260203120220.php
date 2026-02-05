<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203120220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_event DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, lieu VARCHAR(255) NOT NULL, thematique_id INT DEFAULT NULL, INDEX IDX_B26681E476556AF (thematique_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE inscrit_events (id INT AUTO_INCREMENT NOT NULL, date_inscrit DATE NOT NULL, est_inscrit TINYINT(1) DEFAULT 1 NOT NULL, user_id INT NOT NULL, evenement_id INT NOT NULL, INDEX IDX_8079EEFAA76ED395 (user_id), INDEX IDX_8079EEFAFD02F13 (evenement_id), UNIQUE INDEX UNIQ_USER_EVENT (user_id, evenement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE thematique (id INT AUTO_INCREMENT NOT NULL, nom_thematique VARCHAR(255) NOT NULL, code_thematique VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, couleur VARCHAR(20) DEFAULT NULL, icone VARCHAR(100) DEFAULT NULL, ordre SMALLINT DEFAULT NULL, public_cible VARCHAR(100) DEFAULT NULL, niveau_difficulte VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E476556AF FOREIGN KEY (thematique_id) REFERENCES thematique (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inscrit_events ADD CONSTRAINT FK_8079EEFAA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inscrit_events ADD CONSTRAINT FK_8079EEFAFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E476556AF');
        $this->addSql('ALTER TABLE inscrit_events DROP FOREIGN KEY FK_8079EEFAA76ED395');
        $this->addSql('ALTER TABLE inscrit_events DROP FOREIGN KEY FK_8079EEFAFD02F13');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE inscrit_events');
        $this->addSql('DROP TABLE thematique');
        $this->addSql('ALTER TABLE blog CHANGE type type ENUM(\'recommandation\', \'plainte\', \'question\', \'experience\') NOT NULL');
        $this->addSql('ALTER TABLE module CHANGE niveau niveau ENUM(\'difficile\', \'moyen\', \'facile\') NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ROLE_ADMIN\', \'ROLE_PARENT\', \'ROLE_PATIENT\', \'ROLE_MEDECIN\', \'ROLE_USER\') DEFAULT \'ROLE_USER\' NOT NULL');
    }
}
