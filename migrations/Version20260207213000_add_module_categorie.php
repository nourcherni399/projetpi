<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réajoute la colonne categorie à la table module (supprimée par Version20260207212408)
 * pour que les migrations suivantes qui font CHANGE categorie puissent s'exécuter.
 */
final class Version20260207213000_add_module_categorie extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add categorie column to module table (CategorieModule enum)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE module ADD categorie ENUM('', 'COMPRENDRE_TSA', 'AUTONOMIE', 'COMMUNICATION', 'EMOTIONS', 'VIE_QUOTIDIENNE', 'ACCOMPAGNEMENT') NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module DROP categorie');
    }
}
