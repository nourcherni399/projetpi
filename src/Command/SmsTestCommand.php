<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

#[AsCommand(
    name: 'app:sms-test',
    description: 'Envoie un SMS de test à un numéro pour vérifier Twilio (et afficher l\'erreur si échec).',
)]
final class SmsTestCommand extends Command
{
    public function __construct(
        private readonly TexterInterface $texter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('numero', InputArgument::REQUIRED, 'Numéro au format E.164 (ex: +21612345678) ou national (ex: 12345678, indicatif par défaut 216)')
            ->setHelp('Exemples: php bin/console app:sms-test +33612345678   ou   php bin/console app:sms-test 95123456');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $raw = trim((string) $input->getArgument('numero'));

        $phone = $this->normalizeToE164($raw);
        if ($phone === '') {
            $io->error('Numéro invalide. Utilisez le format E.164 (ex: +21612345678) ou un numéro national avec indicatif 216.');
            return Command::FAILURE;
        }

        $message = 'Test AutiCare : SMS OK. ' . date('d/m/Y H:i');

        $io->info(sprintf('Envoi du SMS à %s…', $phone));

        try {
            $this->texter->send(new SmsMessage($phone, $message));
            $io->success('SMS envoyé. Vérifiez le téléphone (délai possible selon l’opérateur).');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Échec de l’envoi.');
            $io->section('Détail de l’erreur');
            $io->text($e->getMessage());
            if ($e->getPrevious() !== null) {
                $io->text('Précision: ' . $e->getPrevious()->getMessage());
            }
            $io->section('À vérifier côté Twilio');
            $io->listing([
                'Compte Trial : vous ne pouvez envoyer qu’aux numéros vérifiés dans la console Twilio (Phone Numbers → Verified Caller IDs).',
                'Le numéro "from" dans TWILIO_DSN doit être un numéro Twilio (Phone Numbers → Manage → Active numbers).',
                'Le numéro de destination doit être au format E.164 (ex: +216…, +33…).',
            ]);
            return Command::FAILURE;
        }
    }

    private function normalizeToE164(string $raw): string
    {
        $raw = preg_replace('/[\s\.\-\(\)]/', '', $raw) ?? '';
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, '+')) {
            return '+' . ltrim($raw, '+');
        }
        if (str_starts_with($raw, '00')) {
            return '+' . substr($raw, 2);
        }
        if (str_starts_with($raw, '0')) {
            $raw = substr($raw, 1);
        }
        return '+' . '216' . $raw;
    }
}
