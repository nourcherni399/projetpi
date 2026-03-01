<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301120000_module_bookmark extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create module_bookmark table for Enregistré (bookmark icon)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE module_bookmark (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, module_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_user_module_bookmark (user_id, module_id), INDEX IDX_MODULE_BOOKMARK_USER (user_id), INDEX IDX_MODULE_BOOKMARK_MODULE (module_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE module_bookmark ADD CONSTRAINT FK_MODULE_BOOKMARK_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE module_bookmark ADD CONSTRAINT FK_MODULE_BOOKMARK_MODULE FOREIGN KEY (module_id) REFERENCES module (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module_bookmark DROP FOREIGN KEY FK_MODULE_BOOKMARK_USER');
        $this->addSql('ALTER TABLE module_bookmark DROP FOREIGN KEY FK_MODULE_BOOKMARK_MODULE');
        $this->addSql('DROP TABLE module_bookmark');
    }
}
