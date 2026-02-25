<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

#[AsCommand(
    name: 'app:sms-test-rappel',
    description: 'Envoie un SMS de rappel de rendez-vous (exemple) à un numéro pour tester le contenu du message.',
)]
final class SmsTestRappelCommand extends Command
{
    public function __construct(
        private readonly TexterInterface $texter,
        private readonly string $nomCabinet = 'AutiCare',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('numero', InputArgument::REQUIRED, 'Numéro (ex: +21695244861 ou 95244861)')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date du RDV (ex: 25/02/2026)', null)
            ->addOption('heure', null, InputOption::VALUE_OPTIONAL, 'Heure du RDV (ex: 14:00)', '10:00')
            ->addOption('patient', null, InputOption::VALUE_OPTIONAL, 'Nom du patient (ex: Jean Dupont)', 'Jean Dupont')
            ->setHelp('Exemple: php bin/console app:sms-test-rappel +21695244861');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $raw = trim((string) $input->getArgument('numero'));
        $phone = $this->normalizeToE164($raw);

        if ($phone === '') {
            $io->error('Numéro invalide. Ex: +21695244861 ou 95244861');
            return Command::FAILURE;
        }

        $dateOption = $input->getOption('date');
        if ($dateOption !== null && $dateOption !== '') {
            $dateStr = $dateOption;
        } else {
            $demain = new \DateTimeImmutable('tomorrow');
            $dateStr = $demain->format('d/m/Y');
        }
        $heureStr = trim((string) $input->getOption('heure')) ?: '10:00';
        $patient = trim((string) $input->getOption('patient')) ?: 'Jean Dupont';

        $message = sprintf(
            '%s - Rappel de rendez-vous : le %s à %s. Patient : %s. Merci de vous présenter à l\'heure.',
            $this->nomCabinet,
            $dateStr,
            $heureStr,
            $patient
        );

        $io->info('Envoi du SMS de rappel (exemple) à ' . $phone . '…');
        $io->text(['Contenu du message :', '', '  ' . $message, '']);

        try {
            $this->texter->send(new SmsMessage($phone, $message));
            $io->success('SMS de rappel envoyé. Vérifiez le téléphone.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Échec de l\'envoi.');
            $io->text($e->getMessage());
            if ($e->getPrevious() !== null) {
                $io->text('Précision: ' . $e->getPrevious()->getMessage());
            }
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
