<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime le schéma gamification : colonne user.points, tables user_badge, point_transaction, badge.
 */
final class Version20260218120000_drop_gamification extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les tables et colonnes de la gamification (badge, user_badge, point_transaction, user.points).';
    }

    public function up(Schema $schema): void
    {
        // Désactiver les vérifications FK pour supprimer les tables sans dépendre des noms de contraintes
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS user_badge');
        $this->addSql('DROP TABLE IF EXISTS point_transaction');
        $this->addSql('DROP TABLE IF EXISTS badge');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
        $this->addSql('ALTER TABLE user DROP COLUMN points');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD points INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE badge (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, nom VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_BADGE_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_badge (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, badge_id INT NOT NULL, earned_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_USER_BADGE_USER (user_id), INDEX IDX_USER_BADGE_BADGE (badge_id), UNIQUE INDEX UNIQ_USER_BADGE (user_id, badge_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE point_transaction (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, amount INT NOT NULL, reason VARCHAR(80) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', reference_id INT DEFAULT NULL, INDEX IDX_POINT_TX_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_USER_BADGE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_USER_BADGE_BADGE FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE point_transaction ADD CONSTRAINT FK_POINT_TX_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
