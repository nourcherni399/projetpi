<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219230000_add_genere_par_ia_to_produit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add genere_par_ia column to produit table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produit ADD genere_par_ia TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produit DROP genere_par_ia');
    }
}
