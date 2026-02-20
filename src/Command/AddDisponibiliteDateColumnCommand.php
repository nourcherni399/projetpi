<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ajoute la colonne date à la table disponibilite et supprime jour.
 * À exécuter une fois si la migration Doctrine n'a pas été appliquée.
 */
#[AsCommand(
    name: 'app:disponibilite-add-date-column',
    description: 'Ajoute la colonne date à disponibilite et supprime jour (migration manuelle)',
)]
final class AddDisponibiliteDateColumnCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $conn = $this->connection;

            // Vérifier si la colonne date existe déjà
            $columns = $conn->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disponibilite' AND COLUMN_NAME = 'date'"
            );
            if ($columns !== []) {
                $io->success('La colonne "date" existe déjà dans la table disponibilite. Rien à faire.');
                return Command::SUCCESS;
            }

            // Vérifier si la colonne jour existe
            $hasJour = $conn->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disponibilite' AND COLUMN_NAME = 'jour'"
            ) !== [];

            $io->writeln('Ajout de la colonne date...');
            $conn->executeStatement('ALTER TABLE disponibilite ADD COLUMN date DATE DEFAULT NULL');

            if ($hasJour) {
                $io->writeln('Remplissage de date à partir de jour...');
                $rows = $conn->fetchAllAssociative('SELECT id, jour FROM disponibilite WHERE jour IS NOT NULL');
                $today = new \DateTimeImmutable('today');
                $todayN = (int) $today->format('N');
                $jourToN = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];

                foreach ($rows as $row) {
                    $jour = $row['jour'];
                    $n = $jourToN[$jour] ?? null;
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

                $conn->executeStatement('UPDATE disponibilite SET date = CURDATE() WHERE date IS NULL');
            }

            $io->writeln('Colonne date en NOT NULL...');
            $conn->executeStatement('ALTER TABLE disponibilite MODIFY COLUMN date DATE NOT NULL');

            if ($hasJour) {
                $io->writeln('Suppression de la colonne jour...');
                $conn->executeStatement('ALTER TABLE disponibilite DROP COLUMN jour');
            }

            $io->success('Table disponibilite mise à jour : colonne date ajoutée, colonne jour supprimée.');
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
