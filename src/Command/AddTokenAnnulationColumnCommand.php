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
 * Ajoute la colonne token_annulation à rendez_vous si elle n'existe pas
 * (pour la page annuler/reporter par le patient sans connexion).
 */
#[AsCommand(
    name: 'app:add-token-annulation-column',
    description: 'Ajoute la colonne token_annulation à rendez_vous si elle n\'existe pas',
)]
final class AddTokenAnnulationColumnCommand extends Command
{
    private const INDEX_NAME = 'UNIQ_65E8AA0AEAA36C3A';

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->connection;

        $hasColumn = $conn->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rendez_vous' AND COLUMN_NAME = 'token_annulation'"
        ) !== [];

        if ($hasColumn) {
            $io->success('La colonne token_annulation existe déjà. Rien à faire.');
            return Command::SUCCESS;
        }

        $io->writeln('Ajout de la colonne token_annulation...');
        $conn->executeStatement('ALTER TABLE rendez_vous ADD COLUMN token_annulation VARCHAR(64) DEFAULT NULL');

        $indexes = $conn->fetchFirstColumn(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rendez_vous' AND INDEX_NAME = ?",
            [self::INDEX_NAME]
        );
        if ($indexes === []) {
            $io->writeln('Création de l\'index unique sur token_annulation...');
            $conn->executeStatement('CREATE UNIQUE INDEX ' . self::INDEX_NAME . ' ON rendez_vous (token_annulation)');
        }

        $io->success('Colonne token_annulation ajoutée avec succès.');
        return Command::SUCCESS;
    }
}
