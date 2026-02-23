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
    name: 'app:fix-user-reset-pin-columns',
    description: 'Ajoute les colonnes reset_pin et reset_pin_expires_at à la table user si elles n\'existent pas (récupération mot de passe).',
)]
final class FixUserResetPinColumnsCommand extends Command
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
            $result = $this->connection->executeQuery("SHOW COLUMNS FROM user LIKE 'reset_pin'");
            $hasResetPin = $result->fetchOne() !== false;
        } catch (\Throwable $e) {
            $io->error('Impossible d\'accéder à la table user: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($hasResetPin) {
            $io->success('Les colonnes reset_pin et reset_pin_expires_at existent déjà. Rien à faire.');
            return Command::SUCCESS;
        }

        $io->writeln('Ajout des colonnes reset_pin et reset_pin_expires_at...');

        try {
            $this->connection->executeStatement('ALTER TABLE user ADD reset_pin VARCHAR(6) DEFAULT NULL, ADD reset_pin_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        } catch (\Throwable $e) {
            $io->error('Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Colonnes ajoutées. La récupération de mot de passe fonctionnera.');
        return Command::SUCCESS;
    }
}
