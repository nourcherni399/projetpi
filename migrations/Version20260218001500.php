<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218001500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create favoris_article table for article favorites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE favoris_article (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, blog_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2F1D01BFA76ED395 (user_id), INDEX IDX_2F1D01BFDAE07E97 (blog_id), UNIQUE INDEX uniq_user_article_favori (user_id, blog_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE favoris_article ADD CONSTRAINT FK_2F1D01BFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favoris_article ADD CONSTRAINT FK_2F1D01BFDAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favoris_article DROP FOREIGN KEY FK_2F1D01BFA76ED395');
        $this->addSql('ALTER TABLE favoris_article DROP FOREIGN KEY FK_2F1D01BFDAE07E97');
        $this->addSql('DROP TABLE favoris_article');
    }
}

