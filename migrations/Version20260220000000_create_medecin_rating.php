<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000000_create_medecin_rating extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table medecin_rating pour les évaluations des médecins par les patients.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE medecin_rating (
            id INT AUTO_INCREMENT NOT NULL,
            medecin_id INT NOT NULL,
            user_id INT NOT NULL,
            note SMALLINT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX medecin_user_unique (medecin_id, user_id),
            INDEX IDX_MEDECIN_RATING_MEDECIN (medecin_id),
            INDEX IDX_MEDECIN_RATING_USER (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_MEDECIN_RATING_MEDECIN FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_MEDECIN_RATING_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE medecin_rating');
    }
}
