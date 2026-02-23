<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_history and user_preference tables for recommendation engine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, action VARCHAR(30) NOT NULL, item_type VARCHAR(30) NOT NULL, item_id INT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1E8A6F4AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_preference (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, category VARCHAR(120) NOT NULL, weight INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3D44EA38A76ED395 (user_id), UNIQUE INDEX uniq_user_preference_user_category (user_id, category), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_history ADD CONSTRAINT FK_1E8A6F4AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preference ADD CONSTRAINT FK_3D44EA38A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_history DROP FOREIGN KEY FK_1E8A6F4AA76ED395');
        $this->addSql('ALTER TABLE user_preference DROP FOREIGN KEY FK_3D44EA38A76ED395');
        $this->addSql('DROP TABLE user_history');
        $this->addSql('DROP TABLE user_preference');
    }
}

