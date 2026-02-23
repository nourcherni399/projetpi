<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218190000_add_commentaire_reaction extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add commentaire_reaction table for like reactions on comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commentaire_reaction (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, commentaire_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CR_USER (user_id), INDEX IDX_CR_COMMENTAIRE (commentaire_id), UNIQUE INDEX uniq_user_commentaire_reaction (user_id, commentaire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE commentaire_reaction ADD CONSTRAINT FK_CR_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire_reaction ADD CONSTRAINT FK_CR_COMMENTAIRE FOREIGN KEY (commentaire_id) REFERENCES commentaire (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commentaire_reaction DROP FOREIGN KEY FK_CR_USER');
        $this->addSql('ALTER TABLE commentaire_reaction DROP FOREIGN KEY FK_CR_COMMENTAIRE');
        $this->addSql('DROP TABLE commentaire_reaction');
    }
}
