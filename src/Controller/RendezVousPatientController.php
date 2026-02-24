<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\RendezVous;
use App\Enum\StatusRendezVous;
use App\Repository\DisponibiliteRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Annulation / report par le patient via lien email ou SMS (token, sans connexion).
 */
final class RendezVousPatientController extends AbstractController
{
    public function __construct(
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    private function findRdvByToken(string $token): ?RendezVous
    {
        $rdv = $this->rendezVousRepository->findOneByToken($token);
        if ($rdv === null) {
            return null;
        }
        if ($rdv->getStatus() === StatusRendezVous::ANNULER) {
            return null;
        }
        return $rdv;
    }

    #[Route('/rdv/{token}', name: 'rdv_patient_page', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function page(string $token): Response
    {
        $rdv = $this->findRdvByToken($token);
        if ($rdv === null) {
            return $this->render('front/rdv_patient/erreur.html.twig', ['message' => 'Lien invalide ou rendez-vous déjà annulé.']);
        }

        return $this->render('front/rdv_patient/page.html.twig', [
            'rdv' => $rdv,
            'token' => $token,
        ]);
    }

    #[Route('/rdv/{token}/annuler', name: 'rdv_patient_annuler', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function annuler(Request $request, string $token): Response
    {
        $rdv = $this->findRdvByToken($token);
        if ($rdv === null) {
            return $this->render('front/rdv_patient/erreur.html.twig', ['message' => 'Lien invalide ou rendez-vous déjà annulé.']);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rdv_annuler', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Formulaire invalide. Veuillez réessayer.');
                return $this->redirectToRoute('rdv_patient_annuler', ['token' => $token]);
            }
            $rdv->setStatus(StatusRendezVous::ANNULER);
            $medecin = $rdv->getMedecin();
            if ($medecin !== null) {
                $notif = new Notification();
                $notif->setDestinataire($medecin);
                $notif->setType(Notification::TYPE_RDV_ANNULE_PATIENT);
                $notif->setRendezVous($rdv);
                $this->entityManager->persist($notif);
            }
            $this->entityManager->flush();
            return $this->redirectToRoute('rdv_patient_annuler_ok', ['token' => $token]);
        }

        return $this->render('front/rdv_patient/annuler.html.twig', [
            'rdv' => $rdv,
            'token' => $token,
        ]);
    }

    #[Route('/rdv/{token}/annuler/ok', name: 'rdv_patient_annuler_ok', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function annulerOk(string $token): Response
    {
        return $this->render('front/rdv_patient/confirmation.html.twig', [
            'message' => 'Votre rendez-vous a bien été annulé.',
            'token' => $token,
        ]);
    }

    #[Route('/rdv/{token}/reporter', name: 'rdv_patient_reporter', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function reporter(Request $request, string $token): Response
    {
        $rdv = $this->findRdvByToken($token);
        if ($rdv === null) {
            return $this->render('front/rdv_patient/erreur.html.twig', ['message' => 'Lien invalide ou rendez-vous déjà annulé.']);
        }

        $medecin = $rdv->getMedecin();
        if ($medecin === null) {
            return $this->render('front/rdv_patient/erreur.html.twig', ['message' => 'Rendez-vous invalide.']);
        }

        $slots = $this->buildSlotsForReporter($rdv);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rdv_reporter', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Formulaire invalide. Veuillez réessayer.');
                return $this->redirectToRoute('rdv_patient_reporter', ['token' => $token]);
            }
            $disponibiliteId = (int) $request->request->get('disponibilite_id', 0);
            $dateRdvStr = (string) $request->request->get('date_rdv', '');
            if ($disponibiliteId > 0 && $dateRdvStr !== '') {
                $dispo = $this->disponibiliteRepository->find($disponibiliteId);
                if ($dispo !== null && $dispo->getMedecin() === $medecin && $dispo->getDate() !== null
                    && $dispo->getDate()->format('Y-m-d') === $dateRdvStr
                    && !$this->rendezVousRepository->isSlotTakenExceptRdv($dispo, $rdv)) {
                    $dateRdv = new \DateTimeImmutable($dateRdvStr);
                    $rdv->setDisponibilite($dispo);
                    $rdv->setDateRdv($dateRdv);
                    $notif = new Notification();
                    $notif->setDestinataire($medecin);
                    $notif->setType(Notification::TYPE_RDV_REPORTE_PATIENT);
                    $notif->setRendezVous($rdv);
                    $this->entityManager->persist($notif);
                    $this->entityManager->flush();
                    return $this->redirectToRoute('rdv_patient_reporter_ok', ['token' => $token]);
                }
            }
            $this->addFlash('error', 'Veuillez choisir un créneau disponible.');
        }

        return $this->render('front/rdv_patient/reporter.html.twig', [
            'rdv' => $rdv,
            'token' => $token,
            'slots' => $slots,
        ]);
    }

    #[Route('/rdv/{token}/reporter/ok', name: 'rdv_patient_reporter_ok', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function reporterOk(string $token): Response
    {
        return $this->render('front/rdv_patient/confirmation.html.twig', [
            'message' => 'Votre rendez-vous a bien été reporté. Vous recevrez un rappel à la nouvelle date.',
            'token' => $token,
        ]);
    }

    /** @return list<array{disponibilite_id: int, date_rdv: string, label: string, available: bool}> */
    private function buildSlotsForReporter(RendezVous $rdv): array
    {
        $medecin = $rdv->getMedecin();
        if ($medecin === null) {
            return [];
        }

        $dispos = $this->disponibiliteRepository->findByMedecin($medecin);
        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+4 weeks');
        $now = new \DateTimeImmutable('now');
        $slots = [];

        foreach ($dispos as $dispo) {
            if (!$dispo->isEstDispo()) {
                continue;
            }
            $date = $dispo->getDate();
            if ($date === null) {
                continue;
            }
            $dateImmutable = $date instanceof \DateTimeImmutable ? $date : new \DateTimeImmutable($date->format('Y-m-d'));
            if ($dateImmutable < $today || $dateImmutable > $end) {
                continue;
            }
            $heureFin = $dispo->getHeureFin();
            if ($heureFin !== null) {
                $slotEnd = $dateImmutable->setTime(
                    (int) $heureFin->format('H'),
                    (int) $heureFin->format('i'),
                    (int) $heureFin->format('s')
                );
                if ($slotEnd < $now) {
                    continue;
                }
            }
            $taken = $this->rendezVousRepository->isSlotTakenExceptRdv($dispo, $rdv);
            $heureDebut = $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
            $heureFinStr = $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';
            $dayOfWeek = (int) $dateImmutable->format('w');
            $dayName = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][$dayOfWeek];
            $slots[] = [
                'disponibilite_id' => $dispo->getId(),
                'date_rdv' => $dateImmutable->format('Y-m-d'),
                'label' => $dayName . ' ' . $dateImmutable->format('d/m/Y') . ', ' . $heureDebut . '-' . $heureFinStr,
                'available' => !$taken,
            ];
        }
        usort($slots, static fn (array $a, array $b): int => strcmp($a['date_rdv'], $b['date_rdv']));

        return $slots;
    }
}
