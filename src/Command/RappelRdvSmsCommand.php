<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\RendezVous;
use App\Repository\RendezVousRepository;
use App\Service\RappelSmsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rappel-rdv-sms',
    description: 'Envoie les rappels SMS pour les rendez-vous dans les X heures (ex. 24h avant). À lancer via cron.',
)]
final class RappelRdvSmsCommand extends Command
{
    public function __construct(
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly RappelSmsService $rappelSmsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_OPTIONAL, 'Heures avant le RDV pour envoyer le rappel', '24')
            ->addOption('window', null, InputOption::VALUE_OPTIONAL, 'Fenêtre en heures (ex. 2 = envoi si RDV dans [hours-window, hours])', '2')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les RDV concernés sans envoyer de SMS');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hoursBefore = (int) $input->getOption('hours');
        $windowHours = (float) $input->getOption('window');
        $dryRun = (bool) $input->getOption('dry-run');

        $now = new \DateTimeImmutable('now');
        $minRdvDt = $now->modify('+' . ($hoursBefore - $windowHours) . ' hours');
        $maxRdvDt = $now->modify('+' . $hoursBefore . ' hours');

        $candidats = $this->rendezVousRepository->findCandidatsRappelSms(3);
        $toSend = [];

        foreach ($candidats as $rdv) {
            $rdvDt = $this->getRendezVousDateTime($rdv);
            if ($rdvDt === null) {
                continue;
            }
            if ($rdvDt >= $minRdvDt && $rdvDt <= $maxRdvDt) {
                $toSend[] = $rdv;
            }
        }

        if ($toSend === []) {
            $io->success('Aucun rendez-vous dans la fenêtre de rappel.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d rappel(s) à envoyer (RDV dans %d–%dh).', \count($toSend), $hoursBefore - (int) $windowHours, $hoursBefore));

        $sent = 0;
        foreach ($toSend as $rdv) {
            $rdvDt = $this->getRendezVousDateTime($rdv);
            $io->text(sprintf('  RDV #%d %s %s – %s', $rdv->getId(), $rdv->getDateRdv()?->format('d/m/Y'), $rdv->getDisponibilite()?->getHeureDebut()?->format('H:i') ?? '?', $rdv->getTelephone()));
            if ($dryRun) {
                $sent++;
                continue;
            }
            if ($this->rappelSmsService->envoyerRappel($rdv)) {
                $sent++;
                $io->success(sprintf('  Envoyé à %s', $rdv->getTelephone()));
            } else {
                $err = $this->rappelSmsService->getLastError();
                $io->warning(sprintf('  Échec envoi à %s', $rdv->getTelephone()));
                if ($err !== null && $err !== '') {
                    $io->text('    → ' . $err);
                }
            }
        }

        $io->success(sprintf('Rappels traités : %d / %d.', $sent, \count($toSend)));
        return Command::SUCCESS;
    }

    private function getRendezVousDateTime(RendezVous $rdv): ?\DateTimeImmutable
    {
        $date = $rdv->getDateRdv();
        $heure = $rdv->getDisponibilite()?->getHeureDebut();
        if ($date === null || $heure === null) {
            return null;
        }
        $d = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromInterface($date);
        return $d->setTime((int) $heure->format('H'), (int) $heure->format('i'), 0);
    }
}
