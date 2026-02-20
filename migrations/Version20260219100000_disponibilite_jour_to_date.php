<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace la colonne jour (récurrence hebdo) par date (date précise) sur disponibilite.
 */
final class Version20260219100000_disponibilite_jour_to_date extends AbstractMigration
{
    private const JOUR_TO_NUMBER = [
        'lundi' => 1,
        'mardi' => 2,
        'mercredi' => 3,
        'jeudi' => 4,
        'vendredi' => 5,
        'samedi' => 6,
        'dimanche' => 7,
    ];

    public function getDescription(): string
    {
        return 'Disponibilite : remplacer jour (enum) par date (DATE) pour un créneau par date.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite ADD date DATE DEFAULT NULL');

        $conn = $this->connection;
        $rows = $conn->fetchAllAssociative('SELECT id, jour FROM disponibilite WHERE jour IS NOT NULL');
        $today = new \DateTimeImmutable('today');
        $todayN = (int) $today->format('N');

        foreach ($rows as $row) {
            $jour = $row['jour'];
            $n = self::JOUR_TO_NUMBER[$jour] ?? null;
            if ($n === null) {
                $next = $today->format('Y-m-d');
            } else {
                $daysToAdd = ($n - $todayN + 7) % 7;
                if ($daysToAdd === 0) {
                    $daysToAdd = 7;
                }
                $next = $today->modify('+' . $daysToAdd . ' days')->format('Y-m-d');
            }
            $conn->executeStatement('UPDATE disponibilite SET date = ? WHERE id = ?', [$next, $row['id']]);
        }

        $this->addSql('ALTER TABLE disponibilite MODIFY date DATE NOT NULL');
        $this->addSql('ALTER TABLE disponibilite DROP COLUMN jour');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE disponibilite ADD jour VARCHAR(255) DEFAULT NULL');
        $conn = $this->connection;
        $rows = $conn->fetchAllAssociative('SELECT id, date FROM disponibilite');
        $jourMap = ['1' => 'lundi', '2' => 'mardi', '3' => 'mercredi', '4' => 'jeudi', '5' => 'vendredi', '6' => 'samedi', '7' => 'dimanche'];
        foreach ($rows as $row) {
            $dateStr = $row['date'];
            if ($dateStr !== null) {
                $n = (int) (new \DateTimeImmutable($dateStr))->format('N');
                $jour = $jourMap[(string) $n] ?? 'lundi';
                $conn->executeStatement('UPDATE disponibilite SET jour = ? WHERE id = ?', [$jour, $row['id']]);
            }
        }
        $this->addSql('ALTER TABLE disponibilite MODIFY jour VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE disponibilite DROP COLUMN date');
    }
}