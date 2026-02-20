<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212100000_create_message_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create message_evenement table for user-admin discussion per event.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_evenement (
            id INT AUTO_INCREMENT NOT NULL,
            evenement_id INT NOT NULL,
            user_id INT NOT NULL,
            contenu LONGTEXT NOT NULL,
            date_envoi DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            envoye_par VARCHAR(10) NOT NULL,
            lu TINYINT(1) DEFAULT 0 NOT NULL,
            INDEX IDX_MSG_EVENT (evenement_id),
            INDEX IDX_MSG_USER (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_MSG_EVENEMENT FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE,
            CONSTRAINT FK_MSG_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE message_evenement');
    }
}
