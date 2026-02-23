<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221011848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change blog.contenu from VARCHAR(255) to TEXT for full article display';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog CHANGE contenu contenu LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blog CHANGE contenu contenu VARCHAR(255) NOT NULL');
    }
}
