<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Disponibilite;
use App\Entity\LigneCommande;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Repository\CartRepository;
use App\Repository\DisponibiliteRepository;
use App\Repository\MedcinRepository;
use App\Repository\RendezVousRepository;
use App\Service\RendezVousConfirmationMailer;
use App\Service\VocalCommandeService;
use App\Service\VocalRdvService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Assistant vocal (gratuit) : Web Speech API côté navigateur.
 * RDV : l'utilisateur parle → l'IA comprend la date → confirmation vocale.
 * Commande : l'utilisateur dit « Je confirme » → commande enregistrée automatiquement.
 */
#[Route('/api')]
final class VocalController extends AbstractController
{
    /** Messages courts quand le créneau est occupé ou inexistant (par code langue). */
    private const SLOT_OCCUPIED_MSG = [
        'fr' => 'Le créneau est occupé.',
        'en' => 'This slot is occupied.',
        'ar' => 'الموعد محجوز.',
        'es' => 'El horario está ocupado.',
        'de' => 'Dieser Termin ist bereits vergeben.',
        'it' => 'Questo slot è occupato.',
    ];
    private const SLOT_NOT_FOUND_MSG = [
        'fr' => 'Il n\'y a pas de créneau à cette date et heure.',
        'en' => 'There is no slot at this date and time.',
        'ar' => 'لا يوجد موعد في هذا التاريخ والوقت.',
        'es' => 'No hay horario en esta fecha y hora.',
        'de' => 'Zu diesem Datum und dieser Uhrzeit ist kein Termin verfügbar.',
        'it' => 'Non c\'è alcun orario disponibile in questa data e ora.',
    ];
    /** Début de phrase pour proposer des créneaux (suit le message occupé / pas de créneau). */
    private const SUGGEST_SLOTS_PREFIX = [
        'fr' => ' Créneaux disponibles : ',
        'en' => ' Available slots: ',
        'ar' => ' مواعيد متاحة: ',
        'es' => ' Horarios disponibles: ',
        'de' => ' Verfügbare Termine: ',
        'it' => ' Orari disponibili: ',
    ];
    /** Message quand le RDV a été enregistré automatiquement (utilisateur connecté). */
    private const RDV_CREATED_MSG = [
        'fr' => 'Votre rendez-vous a été enregistré. Un email de confirmation vous a été envoyé. Vous pouvez fermer cette page.',
        'en' => 'Your appointment has been booked. A confirmation email has been sent to you.',
        'ar' => 'تم حجز موعدك. تم إرسال بريد التأكيد إليك.',
        'es' => 'Su cita ha sido registrada. Se le ha enviado un correo de confirmación.',
        'de' => 'Ihr Termin wurde gebucht. Eine Bestätigungs-E-Mail wurde Ihnen zugesendet.',
        'it' => 'Il suo appuntamento è stato registrato. Le è stata inviata un\'email di conferma.',
    ];
    /** Vous avez déjà une demande pour ce créneau. */
    private const ALREADY_REQUESTED_SLOT_MSG = [
        'fr' => 'Vous avez déjà une demande de rendez-vous pour ce créneau. Choisissez un autre horaire ci-dessous.',
        'en' => 'You already have an appointment request for this slot. Please choose another time below.',
        'ar' => 'لديك بالفعل طلب موعد لهذا الوقت. اختر وقتاً آخر أدناه.',
        'es' => 'Ya tiene una solicitud de cita para este horario. Elija otro más abajo.',
        'de' => 'Sie haben bereits eine Terminanfrage für diesen Zeitraum. Bitte wählen Sie unten einen anderen.',
        'it' => 'Ha già una richiesta di appuntamento per questo orario. Sceglia un altro orario qui sotto.',
    ];
    /** Compléter le profil ou se connecter pour enregistrer le RDV. */
    private const COMPLETE_PROFILE_MSG = [
        'fr' => 'Connectez-vous et complétez votre profil (nom, prénom, email) pour enregistrer le rendez-vous, ou choisissez un créneau ci-dessous.',
        'en' => 'Please log in and complete your profile (name, email) to save the appointment, or select a time slot below.',
        'ar' => 'يرجى تسجيل الدخول وإكمال ملفك (الاسم، البريد) لحفظ الموعد، أو اختر وقتاً أدناه.',
        'es' => 'Inicie sesión y complete su perfil (nombre, email) para guardar la cita, o elija un horario abajo.',
        'de' => 'Bitte melden Sie sich an und vervollständigen Sie Ihr Profil, um den Termin zu speichern, oder wählen Sie unten einen Zeitfenster.',
        'it' => 'Acceda e completi il profilo (nome, email) per salvare l\'appuntamento, oppure scelga un orario qui sotto.',
    ];

    public function __construct(
        private VocalRdvService $vocalRdv,
        private VocalCommandeService $vocalCommande,
        private MedcinRepository $medecinRepository,
        private DisponibiliteRepository $disponibiliteRepository,
        private RendezVousRepository $rendezVousRepository,
        private CartRepository $cartRepository,
        private EntityManagerInterface $entityManager,
        private RendezVousConfirmationMailer $rendezVousConfirmationMailer,
    ) {
    }

    #[Route('/vocal-rdv', name: 'api_vocal_rdv', methods: ['POST'])]
    public function vocalRdv(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $transcript = trim((string) ($data['transcript'] ?? ''));
        $doctorId = isset($data['doctor_id']) ? (int) $data['doctor_id'] : null;
        $doctorName = isset($data['doctor_name']) && \is_string($data['doctor_name']) ? trim($data['doctor_name']) : null;
        $userLang = isset($data['lang']) && \is_string($data['lang']) ? trim($data['lang']) : null;

        if ($doctorName === null && $doctorId > 0) {
            $medecin = $this->medecinRepository->find($doctorId);
            if ($medecin !== null) {
                $doctorName = $medecin->getPrenom() . ' ' . $medecin->getNom();
            }
        }

        $result = $this->vocalRdv->understandAndConfirm($transcript, $doctorName, $userLang);

        $date = $result['date'];
        $time = $result['time'];
        $confirmationText = $result['confirmation_text'];
        // Langue de réponse = celle sélectionnée par l'utilisateur (pour messages et synthèse vocale)
        $lang = $userLang ?? $result['lang'] ?? 'fr-FR';

        if ($date !== null && $time !== null && $doctorId > 0) {
            $medecin = $this->medecinRepository->find($doctorId);
            if ($medecin !== null) {
                $dispo = $this->disponibiliteRepository->findSlotAt($medecin, $date, $time);
                if ($dispo === null) {
                    $confirmationText = $this->getMessageForLang(self::SLOT_NOT_FOUND_MSG, $lang);
                    $confirmationText .= $this->appendSuggestedSlots($medecin, $lang);
                    $date = null;
                    $time = null;
                } else {
                    $heureFin = $dispo->getHeureFin();
                    $slotEnd = $heureFin !== null
                        ? (new \DateTimeImmutable($date))->setTime((int) $heureFin->format('H'), (int) $heureFin->format('i'), (int) $heureFin->format('s'))
                        : null;
                    if ($slotEnd !== null && $slotEnd < new \DateTimeImmutable('now')) {
                        $confirmationText = $this->getMessageForLang(self::SLOT_NOT_FOUND_MSG, $lang);
                        $confirmationText .= $this->appendSuggestedSlots($medecin, $lang);
                        $date = null;
                        $time = null;
                    } elseif ($this->rendezVousRepository->isSlotTaken($dispo)) {
                        $confirmationText = $this->getMessageForLang(self::SLOT_OCCUPIED_MSG, $lang);
                        $confirmationText .= $this->appendSuggestedSlots($medecin, $lang);
                        $date = null;
                        $time = null;
                    } else {
                        $user = $this->getUser();
                        $alreadyRequested = false;
                        if ($user !== null) {
                            if ($user instanceof Patient) {
                                $alreadyRequested = $this->rendezVousRepository->userAlreadyHasRdvForDisponibilite($dispo, $user);
                            } else {
                                $email = $user->getEmail();
                                $alreadyRequested = $email !== null && $email !== '' && $this->rendezVousRepository->hasRdvForDisponibiliteAndEmail($dispo, $email);
                            }
                        }
                        if ($alreadyRequested) {
                            $confirmationText = $this->getMessageForLang(self::ALREADY_REQUESTED_SLOT_MSG, $lang);
                            $confirmationText .= $this->appendSuggestedSlots($medecin, $lang);
                            $date = null;
                            $time = null;
                        } else {
                            // Créneau disponible : enregistrer le RDV automatiquement si l'utilisateur est connecté
                            $rdvCreated = $this->createRendezVousIfUserConnected($medecin, $dispo, $lang);
                            if ($rdvCreated !== null) {
                                $confirmationText = $this->getMessageForLang(self::RDV_CREATED_MSG, $lang);
                                $redirectUrl = $this->generateUrl('user_appointment_book', [
                                    'id' => $doctorId,
                                    'etape' => 4,
                                    'date_rdv' => $date,
                                    'date_label' => $rdvCreated['date_label'],
                                    'type' => 'premiere',
                                    'mode' => 'cabinet',
                                ]);
                                return new JsonResponse([
                                    'date' => $date,
                                    'time' => $time,
                                    'confirmation_text' => $confirmationText,
                                    'lang' => $lang,
                                    'rdv_created' => true,
                                    'redirect_url' => $redirectUrl,
                                ]);
                            }
                            $confirmationText = $this->getMessageForLang(self::COMPLETE_PROFILE_MSG, $lang);
                            $confirmationText .= $this->appendSuggestedSlots($medecin, $lang);
                        }
                    }
                }
            }
        }

        return new JsonResponse([
            'date' => $date,
            'time' => $time,
            'confirmation_text' => $confirmationText,
            'lang' => $lang,
            'rdv_created' => false,
        ]);
    }

    /**
     * Crée le rendez-vous si l'utilisateur est connecté et a nom/prénom/email. Retourne les infos pour le redirect ou null.
     * @return array{date_label: string}|null
     */
    private function createRendezVousIfUserConnected(Medcin $medecin, Disponibilite $dispo, string $lang): ?array
    {
        $user = $this->getUser();
        if ($user === null) {
            return null;
        }

        $nom = $user->getNom() ?? '';
        $prenom = $user->getPrenom() ?? '';
        $email = $user->getEmail() ?? '';
        if ($nom === '' || mb_strlen($nom) < 2 || $prenom === '' || mb_strlen($prenom) < 2 || $email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $telephone = $user->getTelephone() !== null ? (string) $user->getTelephone() : '';
        if (preg_match_all('/\d/', $telephone) < 8 && $telephone !== '') {
            $telephone = '';
        }

        $dateRdv = $dispo->getDate();
        if ($dateRdv === null) {
            return null;
        }
        $dateRdv = $dateRdv instanceof \DateTimeImmutable ? $dateRdv : new \DateTimeImmutable($dateRdv->format('Y-m-d'));

        $dayName = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][(int) $dateRdv->format('w')];
        $heureDebut = $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
        $heureFin = $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';
        $dateLabel = $dayName . ' ' . $dateRdv->format('d/m/Y') . ', ' . $heureDebut . '-' . $heureFin;

        $rdv = new RendezVous();
        $rdv->setMedecin($medecin);
        $rdv->setDisponibilite($dispo);
        $rdv->setDateRdv($dateRdv);
        $rdv->setNom($nom);
        $rdv->setPrenom($prenom);
        $rdv->setStatus(StatusRendezVous::EN_ATTENTE);
        $rdv->setMotif(Motif::NORMAL);
        $rdv->setTelephone($telephone !== '' ? $telephone : null);
        $rdv->setAdresse(null);
        $rdv->setNotePatient('vide');
        $rdv->setEmail($email);
        if ($user instanceof Patient) {
            $rdv->setPatient($user);
        }

        $this->entityManager->persist($rdv);

        $notif = new Notification();
        $notif->setDestinataire($medecin);
        $notif->setType(Notification::TYPE_DEMANDE_RDV);
        $notif->setRendezVous($rdv);
        $this->entityManager->persist($notif);

        $this->entityManager->flush();

        $this->rendezVousConfirmationMailer->sendDemandeEnregistree($rdv);

        return ['date_label' => $dateLabel];
    }

    private function getMessageForLang(array $messages, string $langCode): string
    {
        $prefix = substr($langCode, 0, 2);
        return $messages[$prefix] ?? $messages['fr'];
    }

    /**
     * Retourne une phrase avec 2–3 créneaux disponibles pour le médecin (à enchaîner après le message occupé / pas de créneau).
     */
    private function appendSuggestedSlots(Medcin $medecin, string $langCode): string
    {
        $labels = $this->getNextAvailableSlotLabels($medecin, 3);
        if ($labels === []) {
            return '';
        }
        $prefix = $this->getMessageForLang(self::SUGGEST_SLOTS_PREFIX, $langCode);
        return $prefix . implode(', ', $labels) . '.';
    }

    /** @return list<string> Libellés courts de créneaux disponibles (ex. "lundi 25/02 10h-10h30"). */
    private function getNextAvailableSlotLabels(Medcin $medecin, int $max): array
    {
        $dispos = $this->disponibiliteRepository->findByMedecin($medecin);
        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+4 weeks');
        $now = new \DateTimeImmutable('now');
        $available = [];

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
            if ($this->rendezVousRepository->isSlotTaken($dispo)) {
                continue;
            }
            $dayName = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][(int) $dateImmutable->format('w')];
            $hD = $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H\hi') : '—';
            $hF = $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H\hi') : '—';
            $available[] = $dayName . ' ' . $dateImmutable->format('d/m') . ' ' . $hD . '-' . $hF;
            if (\count($available) >= $max) {
                break;
            }
        }
        return $available;
    }

    /**
     * Confirmer la commande par la voix : l'utilisateur dit « Je confirme ma commande » (ou équivalent dans sa langue).
     */
    #[Route('/vocal-commande', name: 'api_vocal_commande', methods: ['POST'])]
    public function vocalCommande(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous devez être connecté pour confirmer une commande.',
                'lang' => 'fr-FR',
            ], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $transcript = trim((string) ($data['transcript'] ?? ''));
        $userLang = isset($data['lang']) && \is_string($data['lang']) ? trim($data['lang']) : null;
        $csrfToken = isset($data['_token']) ? (string) $data['_token'] : '';

        if (!$this->isCsrfTokenValid('order_confirm', $csrfToken)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Jeton de sécurité invalide. Rechargez la page et réessayez.',
                'lang' => $userLang ?? 'fr-FR',
            ], 400);
        }

        $sessionData = $request->getSession()->get('order_checkout_data');
        if (!$sessionData || !\is_array($sessionData)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Session expirée. Veuillez refaire le formulaire de commande.',
                'lang' => $userLang ?? 'fr-FR',
            ], 400);
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->isEmpty()) {
            $request->getSession()->remove('order_checkout_data');
            return new JsonResponse([
                'success' => false,
                'message' => 'Votre panier est vide.',
                'lang' => $userLang ?? 'fr-FR',
            ], 400);
        }

        $result = $this->vocalCommande->detectConfirmIntent($transcript, $userLang);

        if ($result['intent'] === 'reject') {
            return new JsonResponse([
                'success' => false,
                'message' => $result['message'],
                'lang' => $result['lang'],
                'order_id' => null,
                'redirect_url' => null,
            ]);
        }

        if ($result['intent'] === 'confirm') {
            foreach ($cart->getItems() as $item) {
                if ($item->getQuantite() <= 0) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Veuillez corriger les quantités dans le panier.',
                        'lang' => $result['lang'],
                    ], 400);
                }
            }

            $commande = new Commande();
            $commande->setUser($user);
            $commande->setNom($sessionData['nom'] ?? '');
            $commande->setEmail($sessionData['email'] ?? '');
            $commande->setTelephone($sessionData['telephone'] ?? '');
            $commande->setAdresse($sessionData['adresse'] ?? '');
            $commande->setCodePostal($sessionData['codePostal'] ?? '');
            $commande->setVille($sessionData['ville'] ?? '');
            $commande->setModePayment($sessionData['modePayment'] ?? 'a_la_livraison');
            $commande->setStatut('en_attente');

            $totalPrice = 0.0;
            foreach ($cart->getItems() as $item) {
                $ligne = new LigneCommande();
                $ligne->setCommande($commande);
                $ligne->setProduit($item->getProduit());
                $ligne->setQuantite($item->getQuantite());
                $ligne->setPrix($item->getPrix());
                $ligne->setSousTotal($item->getTotalPrice());
                $commande->addLigne($ligne);
                $totalPrice += $item->getTotalPrice();
                $this->entityManager->persist($ligne);
            }
            $commande->setTotal($totalPrice);

            $this->entityManager->persist($commande);
            $cart->clear();
            $cart->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $request->getSession()->remove('order_checkout_data');

            return new JsonResponse([
                'success' => true,
                'message' => $result['message'],
                'lang' => $result['lang'],
                'order_id' => $commande->getId(),
                'redirect_url' => $this->generateUrl('order_confirmation', ['id' => $commande->getId()]),
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => $result['message'],
            'lang' => $result['lang'],
            'order_id' => null,
            'redirect_url' => null,
        ]);
    }
}
