<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Relation Produit-Stock : un stock contient plusieurs produits,
 * chaque produit appartient à un seul stock. La quantité est sur le produit.
 */
final class Version20260216200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change Stock to warehouse (nom), Product has ManyToOne stock + quantite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock ADD nom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit ADD stock_id INT DEFAULT NULL, ADD quantite INT DEFAULT 1 NOT NULL');

        $this->addSql("UPDATE stock SET nom = CONCAT('Stock ', id) WHERE nom IS NULL");
        $this->addSql('UPDATE produit p INNER JOIN stock s ON s.produit_id = p.id SET p.stock_id = s.id, p.quantite = s.quantite');

        $this->addSql("INSERT INTO stock (nom) SELECT 'Principal' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM stock WHERE nom = 'Principal')");
        $this->addSql("UPDATE produit SET stock_id = (SELECT id FROM stock WHERE nom = 'Principal' LIMIT 1), quantite = 1 WHERE stock_id IS NULL");

        $fkName = $this->getForeignKeyName('stock', 'produit_id');
        if ($fkName !== null) {
            $this->addSql('ALTER TABLE stock DROP FOREIGN KEY ' . $fkName);
        }
        $this->addSql('ALTER TABLE stock DROP produit_id, DROP quantite');
        $this->addSql('ALTER TABLE stock MODIFY nom VARCHAR(100) NOT NULL');

        $this->addSql('ALTER TABLE produit MODIFY stock_id INT NOT NULL');
        $this->addSql('ALTER TABLE produit ADD CONSTRAINT FK_29A5EC27CDCD6120 FOREIGN KEY (stock_id) REFERENCES stock (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_29A5EC27CDCD6120 ON produit (stock_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produit DROP FOREIGN KEY FK_29A5EC27CDCD6120');
        $this->addSql('DROP INDEX IDX_29A5EC27CDCD6120 ON produit');
        $this->addSql('ALTER TABLE produit DROP stock_id, DROP quantite');
        $this->addSql('ALTER TABLE stock ADD produit_id INT DEFAULT NULL, ADD quantite INT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE stock s INNER JOIN produit p ON p.stock_id = s.id SET s.produit_id = p.id, s.quantite = p.quantite LIMIT 1');
        $this->addSql('ALTER TABLE stock ADD CONSTRAINT FK_4B365660F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4B365660F347EFB ON stock (produit_id)');
        $this->addSql('ALTER TABLE stock DROP nom');
    }

    private function getForeignKeyName(string $table, string $column): ?string
    {
        $sql = "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL";
        $result = $this->connection->executeQuery($sql, [$table, $column]);
        $row = $result->fetchAssociative();
        return $row ? $row['CONSTRAINT_NAME'] : null;
    }
}
