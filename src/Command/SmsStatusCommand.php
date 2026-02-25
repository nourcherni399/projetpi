<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sms-status',
    description: 'Affiche si les SMS (Twilio) sont activés ou non.',
)]
final class SmsStatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dsn = $_ENV['TWILIO_DSN'] ?? getenv('TWILIO_DSN') ?: '';
        $enabled = $dsn !== '' && str_starts_with($dsn, 'twilio://');

        if ($enabled) {
            $io->success('SMS activés (Twilio configuré). Les rappels RDV peuvent être envoyés par SMS.');
            $io->text(['Pour envoyer les rappels :', '  php bin/console app:rappel-rdv-sms --hours=24']);
        } else {
            $io->warning('SMS désactivés.');
            $io->text([
                'Twilio n\'est pas configuré ou TWILIO_DSN est vide.',
                'Dans .env, définissez :',
                '  TWILIO_DSN=twilio://ACxxx:TOKEN@default?from=+1234567890',
                'Puis redémarrez l\'application.',
            ]);
        }

        return Command::SUCCESS;
    }
}
