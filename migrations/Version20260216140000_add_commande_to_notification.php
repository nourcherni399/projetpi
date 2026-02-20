<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216140000_add_commande_to_notification extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne commande_id à la table notification pour les notifications de commande.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD commande_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_BF5476CA82EA2E54 ON notification (commande_id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA82EA2E54');
        $this->addSql('DROP INDEX IDX_BF5476CA82EA2E54 ON notification');
        $this->addSql('ALTER TABLE notification DROP commande_id');
    }
}
