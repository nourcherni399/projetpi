<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227005501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_highlight (id INT AUTO_INCREMENT NOT NULL, target_type VARCHAR(20) NOT NULL, target_id INT NOT NULL, start_offset INT NOT NULL, end_offset INT NOT NULL, color VARCHAR(20) DEFAULT \'yellow\' NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_C97855D0A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE user_highlight ADD CONSTRAINT FK_C97855D0A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_highlight DROP FOREIGN KEY FK_C97855D0A76ED395');
        $this->addSql('DROP TABLE user_highlight');
    }
}
