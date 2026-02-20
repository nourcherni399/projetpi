<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:drop-gamification',
    description: 'Supprime les tables et la colonne de gamification (badge, user_badge, point_transaction, user.points).',
)]
final class DropGamificationCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln('Suppression du schéma gamification...');

        try {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            $this->connection->executeStatement('DROP TABLE IF EXISTS user_badge');
            $this->connection->executeStatement('DROP TABLE IF EXISTS point_transaction');
            $this->connection->executeStatement('DROP TABLE IF EXISTS badge');
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

            $hasPoints = $this->connection->executeQuery("SHOW COLUMNS FROM user LIKE 'points'")->fetchOne() !== false;
            if ($hasPoints) {
                $this->connection->executeStatement('ALTER TABLE user DROP COLUMN points');
                $io->writeln('Colonne user.points supprimée.');
            }

            $io->success('Gamification supprimée : tables badge, user_badge, point_transaction et colonne user.points.');
        } catch (\Throwable $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
