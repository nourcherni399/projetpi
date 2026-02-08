<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Evenement;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Enum\UserRole;
use App\Repository\DisponibiliteRepository;
use App\Repository\EvenementRepository;
use App\Repository\MedcinRepository;
use App\Repository\RendezVousRepository;
use App\Repository\ThematiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly MedcinRepository $medcinRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response|RedirectResponse
    {
        $user = $this->getUser();
        if ($user !== null && method_exists($user, 'getRole')) {
            $role = $user->getRole();
            if ($role === UserRole::ADMIN) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($role === UserRole::MEDECIN) {
                return $this->redirectToRoute('doctor_dashboard');
            }
        }
        return $this->render('front/home/index.html.twig');
    }

    #[Route('/a-propos', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('front/about/index.html.twig');
    }

    #[Route('/evenements', name: 'user_events', methods: ['GET'])]
    public function events(): Response
    {
        $thematiques = $this->thematiqueRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        $grouped = [];
        foreach ($thematiques as $t) {
            $evenements = $t->getEvenements()->toArray();
            usort($evenements, static function (Evenement $a, Evenement $b): int {
                $d = ($a->getDateEvent() <=> $b->getDateEvent());
                return $d !== 0 ? $d : ($a->getHeureDebut() <=> $b->getHeureDebut());
            });
            $grouped[] = ['thematique' => $t, 'evenements' => $evenements];
        }
        $sansThematique = $this->evenementRepository->findBy(['thematique' => null], ['dateEvent' => 'ASC', 'heureDebut' => 'ASC']);
        return $this->render('front/events/index.html.twig', [
            'grouped' => $grouped,
            'sansThematique' => $sansThematique,
        ]);
    }

    #[Route('/evenements/{id}', name: 'user_event_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function eventShow(int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }
        return $this->render('front/events/show.html.twig', ['evenement' => $evenement]);
    }

    #[Route('/rendez-vous', name: 'user_appointments', methods: ['GET'])]
    public function appointments(): Response
    {
        $medecins = $this->medcinRepository->findAllOrderByNom();
        $specialites = array_values(array_unique(array_filter(array_map(
            static fn (Medcin $m) => $m->getSpecialite(),
            $medecins
        ))));
        sort($specialites);
        return $this->render('front/appointments/index.html.twig', [
            'medecins' => $medecins,
            'specialites' => $specialites,
        ]);
    }

    /** Labels pour type de consultation et mode (affichage récap/confirmation). */
    private const APPOINTMENT_TYPE_LABELS = [
        'premiere' => 'Première consultation',
        'bilan' => 'Bilan complet',
        'suivi' => 'Consultation de suivi',
        'urgent' => 'Consultation urgente',
    ];
    private const APPOINTMENT_MODE_LABELS = [
        'cabinet' => 'Au cabinet',
        'teleconsult' => 'Téléconsultation',
    ];

    /** Numéro de jour PHP (1=lundi) pour chaque Jour enum. */
    private const JOUR_TO_NUMBER = [
        'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4,
        'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7,
    ];

    #[Route('/rendez-vous/prendre/{id}', name: 'user_appointment_book', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function appointmentBook(int $id, Request $request): Response
    {
        $medecin = $this->medcinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }
        $doctor = $this->medecinToDoctorArray($medecin);
        $step = (int) $request->query->get('etape', 1);
        $step = max(1, min(4, $step));

        $disponibiliteId = $request->query->get('disponibilite_id');
        $dateRdv = (string) $request->query->get('date_rdv', '');
        $type = (string) $request->query->get('type', 'premiere');
        $mode = (string) $request->query->get('mode', 'cabinet');
        $motif = (string) $request->query->get('motif', '');

        if (!isset(self::APPOINTMENT_TYPE_LABELS[$type])) {
            $type = 'premiere';
        }
        if (!isset(self::APPOINTMENT_MODE_LABELS[$mode])) {
            $mode = 'cabinet';
        }

        $slots = $this->getAvailableSlotsForMedecin($medecin);

        $choices = [
            'disponibilite_id' => $disponibiliteId,
            'date_rdv' => $dateRdv,
            'date_label' => $request->query->get('date_label', ''),
            'type' => $type,
            'type_label' => self::APPOINTMENT_TYPE_LABELS[$type],
            'mode' => $mode,
            'mode_label' => self::APPOINTMENT_MODE_LABELS[$mode],
            'motif' => $motif,
        ];

        return $this->render('front/appointments/book.html.twig', [
            'doctor' => $doctor,
            'step' => $step,
            'choices' => $choices,
            'slots' => $slots,
        ]);
    }

    /**
     * @return list<array{disponibilite_id: int, date_rdv: string, label: string}>
     */
    private function getAvailableSlotsForMedecin(Medcin $medecin): array
    {
        $dispos = $this->disponibiliteRepository->findByMedecin($medecin);
        $slots = [];
        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+4 weeks');
        $jourNumber = self::JOUR_TO_NUMBER;

        foreach ($dispos as $dispo) {
            if (!$dispo->isEstDispo() || $dispo->getJour() === null) {
                continue;
            }
            $jourValue = $dispo->getJour()->value;
            $targetDayNum = $jourNumber[$jourValue] ?? null;
            if ($targetDayNum === null) {
                continue;
            }
            $iter = $today;
            while ($iter <= $end) {
                if ((int) $iter->format('N') === $targetDayNum) {
                    if (!$this->rendezVousRepository->isSlotTaken($dispo, $iter)) {
                        $heureDebut = $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
                        $heureFin = $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';
                        $slots[] = [
                            'disponibilite_id' => $dispo->getId(),
                            'date_rdv' => $iter->format('Y-m-d'),
                            'label' => ucfirst($jourValue) . ' ' . $iter->format('d/m/Y') . ', ' . $heureDebut . '-' . $heureFin,
                        ];
                    }
                }
                $iter = $iter->modify('+1 day');
            }
        }
        usort($slots, static fn (array $a, array $b): int => strcmp($a['date_rdv'] . $a['disponibilite_id'], $b['date_rdv'] . $b['disponibilite_id']));
        return $slots;
    }

    #[Route('/rendez-vous/prendre/{id}/confirmer', name: 'user_appointment_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentSubmit(int $id, Request $request): Response
    {
        $medecin = $this->medcinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }

        $disponibiliteId = (int) $request->request->get('disponibilite_id', 0);
        $dateRdvStr = (string) $request->request->get('date_rdv', '');
        $disponibilite = $disponibiliteId > 0 ? $this->disponibiliteRepository->find($disponibiliteId) : null;
        
        // Convertir la chaîne en DateTime
        $dateRdv = null;
        if ($dateRdvStr !== '') {
            try {
                $dateRdv = new \DateTime($dateRdvStr);
            } catch (\Throwable) {
            }
        }
        
        if ($disponibilite === null || $dateRdv === null) {
            $this->addFlash('error', 'Créneau ou date invalide.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }
        
        if ($this->rendezVousRepository->isSlotTaken($disponibilite, $dateRdv)) {
            $this->addFlash('error', 'Ce créneau n\'est plus disponible.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        if ($nom === '' || $prenom === '') {
            $this->addFlash('error', 'Nom et prénom obligatoires.');
            return $this->redirectToRoute('user_appointment_book', [
                'id' => $id,
                'etape' => 3,
                'disponibilite_id' => $disponibiliteId ?: null,
                'date_rdv' => $dateRdvStr ?: null,
                'type' => $request->request->get('type'),
                'mode' => $request->request->get('mode'),
                'motif' => $request->request->get('motif'),
            ]);
        }

        // Validation du token CSRF
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('rdv_submit', $token)) {
            $this->addFlash('error', 'Session expirée. Veuillez recommencer.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        // Détermination du motif
        $motifKey = (string) $request->request->get('motif_key', 'normal');
        $motif = match ($motifKey) {
            'urgence' => Motif::URGENCE,
            'suivie' => Motif::SUIVIE,
            default => Motif::NORMAL,
        };

        // Création du rendez-vous
        $rendezVous = new RendezVous();
        $rendezVous->setMedecin($medecin);
        $rendezVous->setDisponibilite($disponibilite);
        $rendezVous->setDateRdv($dateRdv);
        $rendezVous->setNom($nom);
        $rendezVous->setPrenom($prenom);
        $rendezVous->setStatus(StatusRendezVous::EN_ATTENTE);
        $rendezVous->setMotif($motif);
        $rendezVous->setTelephone((string) $request->request->get('telephone', ''));
        $rendezVous->setAdresse((string) $request->request->get('adresse', ''));
        $rendezVous->setNotePatient((string) $request->request->get('note', ''));
        
        // Gestion de la date de naissance
        $dateNaissance = $request->request->get('date_naissance');
        if ($dateNaissance !== null && $dateNaissance !== '') {
            try {
                $rendezVous->setDateNaissance(new \DateTime($dateNaissance));
            } catch (\Throwable) {
            }
        }
        
        // Association avec le patient connecté
        $user = $this->getUser();
        if ($user instanceof Patient) {
            $rendezVous->setPatient($user);
        }

        // Sauvegarde en base de données
        $this->entityManager->persist($rendezVous);
        $this->entityManager->flush();

        // Création de la notification pour le médecin
        $notification = new Notification();
        $notification->setDestinataire($medecin);
        $notification->setType(Notification::TYPE_DEMANDE_RDV);
        $notification->setRendezVous($rendezVous);
        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre demande de rendez-vous a été envoyée. Le médecin vous répondra sous peu.');
        
        return $this->redirectToRoute('user_appointment_book', [
            'id' => $id,
            'etape' => 4,
            'date_rdv' => $dateRdvStr,
            'date_label' => $request->request->get('date_label', ''),
            'type' => $request->request->get('type', 'premiere'),
            'mode' => $request->request->get('mode', 'cabinet'),
            'motif' => $request->request->get('motif', ''),
        ]);
    }

    /**
     * @return array{id: int, name: string, initials: string, specialty: string, specialty_class: string, rating: string, reviews: int, description: string, address: string, phone: string, email: string, price: int|float, has_cabinet: bool, has_teleconsult: bool}
     */
    private function medecinToDoctorArray(Medcin $medecin): array
    {
        $nom = $medecin->getNom() ?? '';
        $prenom = $medecin->getPrenom() ?? '';
        $initials = (mb_substr($nom, 0, 1) . mb_substr($prenom, 0, 1)) ?: 'DR';
        $specialite = $medecin->getSpecialite() ?? 'Spécialiste';
        $specialtyClass = match (mb_strtolower($specialite)) {
            'psychiatre' => 'bg-emerald-100 text-emerald-800',
            'psychologue' => 'bg-emerald-100 text-emerald-800',
            'orthophoniste' => 'bg-sky-100 text-sky-800',
            default => 'bg-[#A7C7E7]/20 text-[#4B5563]',
        };

        return [
            'id' => $medecin->getId(),
            'name' => trim('Dr. ' . $nom . ' ' . $prenom) ?: 'Praticien',
            'initials' => mb_strtoupper($initials),
            'specialty' => $specialite,
            'specialty_class' => $specialtyClass,
            'rating' => '—',
            'reviews' => 0,
            'description' => 'Praticien accompagnant les personnes avec TSA. Cabinet : ' . ($medecin->getNomCabinet() ?? 'non renseigné') . '.',
            'address' => $medecin->getAdresseCabinet() ?? '—',
            'phone' => $medecin->getTelephoneCabinet() ?? $medecin->getTelephone() ?? '—',
            'email' => $medecin->getEmail() ?? '—',
            'price' => (int) round($medecin->getTarifConsultation() ?? 0),
            'has_cabinet' => $medecin->getAdresseCabinet() !== null && $medecin->getAdresseCabinet() !== '',
            'has_teleconsult' => true,
        ];
    }

    #[Route('/inscription', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('front/auth/register.html.twig');
    }

    #[Route('/connexion', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('front/auth/login.html.twig');
    }
}
