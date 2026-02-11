<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211130000_create_cart_tables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les tables cart et cart_item pour le panier.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_BA388B7A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, produit_id INT NOT NULL, quantite INT NOT NULL, prix DOUBLE PRECISION NOT NULL, INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE2527F347EFB (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE2527F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B7A76ED395');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE2527F347EFB');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
    }
}
