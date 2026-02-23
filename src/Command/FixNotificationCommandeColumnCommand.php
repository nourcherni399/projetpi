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
    name: 'app:fix-notification-commande-column',
    description: 'Ajoute la colonne commande_id à la table notification si elle n\'existe pas (corrige l\'erreur Unknown column commande_id).',
)]
final class FixNotificationCommandeColumnCommand extends Command
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
            $result = $this->connection->executeQuery("SHOW COLUMNS FROM notification LIKE 'commande_id'");
            $hasColumn = $result->fetchOne() !== false;
        } catch (\Throwable $e) {
            $io->error('Impossible d\'accéder à la table notification: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if ($hasColumn) {
            $io->success('La colonne notification.commande_id existe déjà. Rien à faire.');
            return Command::SUCCESS;
        }

        $io->writeln('Ajout de la colonne commande_id...');

        try {
            $this->connection->executeStatement('ALTER TABLE notification ADD commande_id INT DEFAULT NULL');
            $this->connection->executeStatement('CREATE INDEX IDX_BF5476CA82EA2E54 ON notification (commande_id)');
            $this->connection->executeStatement('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        } catch (\Throwable $e) {
            $io->error('Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Colonne, index et clé étrangère ajoutés. Rechargez la page.');
        return Command::SUCCESS;
    }
}
