<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie un email de test pour vérifier MAILER_DSN et l'adresse d'expéditeur.
 */
#[AsCommand(
    name: 'app:test-mail',
    description: 'Envoie un email de test pour vérifier la configuration mail (MAILER_DSN, MAILER_FROM).',
)]
final class TestMailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@auticare.fr',
        private readonly string $fromName = 'AutiCare',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Adresse email de destination (ex: vous@example.com)')
            ->setHelp('Exemple : php bin/console app:test-mail amarahedil8@gmail.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = trim((string) $input->getArgument('to'));

        if (!filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse email invalide : ' . $to);
            return Command::FAILURE;
        }

        $io->writeln('Expéditeur (From) : ' . $this->fromEmail . ' <' . $this->fromName . '>');
        $io->writeln('Destinataire (To)  : ' . $to);
        $io->writeln('');

        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject('Test email AutiCare - ' . date('d/m/Y H:i'))
                ->text("Ceci est un email de test.\n\nSi vous recevez ce message, l'envoi depuis l'application fonctionne correctement.\n\n— AutiCare");

            $this->mailer->send($email);
            $io->success('Email envoyé avec succès. Vérifiez la boîte de réception (et les spams) de ' . $to);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Échec de l\'envoi');
            $io->writeln('');
            $io->writeln('<comment>Erreur :</comment> ' . $e->getMessage());
            if ($e->getPrevious() !== null) {
                $io->writeln('<comment>Détail :</comment> ' . $e->getPrevious()->getMessage());
            }
            $io->writeln('');
            $io->section('Pistes de résolution (Gmail)');
            $io->listing([
                'Utilisez un "Mot de passe d\'application" (compte Google → Sécurité → Mots de passe des applications), pas votre mot de passe habituel.',
                'Dans .env : MAILER_FROM doit être la même adresse que dans MAILER_DSN (ex: amarahedil8@gmail.com).',
                'Videz le cache : php bin/console cache:clear',
            ]);
            return Command::FAILURE;
        }
    }
}
