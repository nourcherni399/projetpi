<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210140000_create_note_table extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table note (medecin, patient, contenu, date_creation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note (
            id INT AUTO_INCREMENT NOT NULL,
            medecin_id INT NOT NULL,
            patient_id INT NOT NULL,
            contenu LONGTEXT NOT NULL,
            date_creation DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CFBDFA14F42A439 (medecin_id),
            INDEX IDX_CFBDFA146B899279 (patient_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_CFBDFA14F42A439 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE,
            CONSTRAINT FK_CFBDFA146B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE note');
    }
}
