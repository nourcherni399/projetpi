<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210150000_create_notification_table extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table notification (destinataire, type, lu, created_at, rendez_vous).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            destinataire_id INT NOT NULL,
            rendez_vous_id INT DEFAULT NULL,
            type VARCHAR(50) NOT NULL,
            lu TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_BF5476CAA4F84F6E (destinataire_id),
            INDEX IDX_BF5476CA91EF7EAA (rendez_vous_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_BF5476CAA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_BF5476CA91EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification');
    }
}
