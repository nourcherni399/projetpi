<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217235959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove article reaction table and feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS article_reaction');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE article_reaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, blog_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_F13FF39CDAE07E97 (blog_id), INDEX IDX_F13FF39CA76ED395 (user_id), UNIQUE INDEX uniq_blog_user_reaction (blog_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE article_reaction ADD CONSTRAINT FK_F13FF39CDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE article_reaction ADD CONSTRAINT FK_F13FF39CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}
