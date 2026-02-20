<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\MessageEvenement;
use App\Form\EvenementType;
use App\Form\MessageEvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InscritEventsRepository;
use App\Repository\MessageEvenementRepository;
use App\Repository\ThematiqueRepository;
use App\Repository\UserRepository;
use App\Service\EventReminderService;
use App\Service\HuggingFaceService;
use App\Service\NominatimGeocodingService;
use App\Service\ZoomApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/evenements')]
final class EvenementController extends AbstractController
{
    public function __construct(
        private readonly EvenementRepository $evenementRepository,
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly MessageEvenementRepository $messageEvenementRepository,
        private readonly UserRepository $userRepository,
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ZoomApiService $zoomApiService,
        private readonly EventReminderService $eventReminderService,
        private readonly HuggingFaceService $huggingFaceService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    private const PERIODS_RECHERCHE = [
        'Ce mois' => 'this month',
        'Ce trimestre' => '2025',
        '2025' => '2025',
        '2026' => '2026',
    ];

    private const SESSION_RECHERCHE_MONDIALE = 'evenement_recherche_mondiale';

    #[Route('', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = $request->query->get('q');
        $sortBy = $request->query->get('sort', 'date');
        if (!in_array($sortBy, ['date', 'lieu', 'theme', 'titre'], true)) {
            $sortBy = 'date';
        }
        $sortOrder = $request->query->get('order', 'asc');
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'], true)) {
            $sortOrder = 'asc';
        }
        $evenements = $this->evenementRepository->searchAndSort($q, $sortBy, $sortOrder);
        $eventIds = array_map(static fn ($e) => (int) $e->getId(), $evenements);
        $unreadByEvent = $this->messageEvenementRepository->countUnreadFromUserByEvenementIds($eventIds);

        $totalEvenements = $this->evenementRepository->countAll();
        $evenementsAvenir = $this->evenementRepository->countUpcoming();
        $evenementsPasses = $this->evenementRepository->countPast();
        $inscriptionsAcceptees = $this->inscritEventsRepository->countByStatut('accepte');
        $inscriptionsEnAttente = $this->inscritEventsRepository->countByStatut('en_attente');
        $totalInscriptions = $this->inscritEventsRepository->countTotalInscriptions();

        $session = $request->getSession();
        $upcoming = $session->get(self::SESSION_RECHERCHE_MONDIALE);
        $session->remove(self::SESSION_RECHERCHE_MONDIALE);

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenements,
            'unreadByEvent' => $unreadByEvent,
            'q' => $q,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'totalEvenements' => $totalEvenements,
            'evenementsAvenir' => $evenementsAvenir,
            'evenementsPasses' => $evenementsPasses,
            'inscriptionsAcceptees' => $inscriptionsAcceptees,
            'inscriptionsEnAttente' => $inscriptionsEnAttente,
            'totalInscriptions' => $totalInscriptions,
            'periods' => self::PERIODS_RECHERCHE,
            'upcomingEventsResults' => $upcoming['results'] ?? null,
            'upcomingEventsError' => $upcoming['error'] ?? null,
            'upcomingSearchQuerySent' => $upcoming['searchQuery'] ?? '',
            'upcomingSearchPerformed' => $upcoming !== null,
            'upcomingSearchQuery' => $upcoming['query'] ?? '',
            'upcomingSearchPeriod' => $upcoming['periodKey'] ?? '2025',
            'huggingface_configured' => $this->huggingFaceService->hasApiKey(),
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $evenement = new Evenement();
        $prefill = $request->getSession()->remove('idee_evenement_prefill');
        if (\is_array($prefill)) {
            if (!empty($prefill['titre'])) {
                $evenement->setTitle($prefill['titre']);
            }
            if (isset($prefill['description'])) {
                $evenement->setDescription($prefill['description']);
            }
            if (!empty($prefill['theme'])) {
                $thematiques = $this->thematiqueRepository->search($prefill['theme']);
                if (\count($thematiques) > 0) {
                    $evenement->setThematique($thematiques[0]);
                }
            }
        }
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($evenement);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'événement a été créé avec succès.');

            return $this->redirectToRoute('admin_evenement_index');
        }

        return $this->render('admin/evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
        $participants = $this->inscritEventsRepository->findByEvenementOrderByDate($evenement);
        $conversationsRaw = $this->messageEvenementRepository->findConversationsByEvenement($evenement);
        $conversations = [];
        foreach ($conversationsRaw as $c) {
            $u = $this->userRepository->find($c['user_id']);
            if ($u !== null) {
                $conversations[] = ['user' => $u, 'last_at' => $c['last_at']];
            }
        }
        $unreadCount = $this->messageEvenementRepository->countUnreadFromUserByEvenement($evenement);

        $conversationUser = null;
        $conversationMessages = [];
        $replyForm = null;
        $userId = $request->query->getInt('user');
        if ($userId > 0) {
            $conversationUser = $this->userRepository->find($userId);
            if ($conversationUser !== null) {
                $this->messageEvenementRepository->markAsReadByEvenementAndUser($evenement, $conversationUser);
                $conversationMessages = $this->messageEvenementRepository->findByEvenementAndUserOrderByDate($evenement, $conversationUser);
                $newReply = new MessageEvenement();
                $newReply->setEvenement($evenement);
                $newReply->setUser($conversationUser);
                $replyForm = $this->createForm(MessageEvenementType::class, $newReply);
            }
        }

        $imageFileExists = false;
        $imageUrl = null;
        if ($evenement->getImage() !== null && $evenement->getImage() !== '') {
            $uploadDir = $this->getParameter('uploads_evenements_directory');
            $basename = basename($evenement->getImage());
            if ($basename !== '' && is_file($uploadDir . \DIRECTORY_SEPARATOR . $basename)) {
                $imageFileExists = true;
                $imageUrl = $this->urlGenerator->generate('admin_evenement_affiche', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
            'conversations' => $conversations,
            'unreadMessagesCount' => $unreadCount,
            'conversationUser' => $conversationUser,
            'conversationMessages' => $conversationMessages,
            'replyForm' => $replyForm,
            'image_file_exists' => $imageFileExists,
            'image_url' => $imageUrl,
            'huggingface_configured' => $this->huggingFaceService->hasApiKey(),
        ]);
    }

    #[Route('/{id}/affiche', name: 'admin_evenement_affiche', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function affiche(int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null || $evenement->getImage() === null || $evenement->getImage() === '') {
            throw new NotFoundHttpException('Affiche introuvable.');
        }
        $basename = basename(str_replace('\\', '/', $evenement->getImage()));
        if ($basename === '') {
            throw new NotFoundHttpException('Affiche introuvable.');
        }
        $uploadDir = rtrim(str_replace('/', \DIRECTORY_SEPARATOR, (string) $this->getParameter('uploads_evenements_directory')), \DIRECTORY_SEPARATOR);
        $projectDir = $this->getParameter('kernel.project_dir');
        $candidates = [
            $uploadDir . \DIRECTORY_SEPARATOR . $basename,
            $projectDir . \DIRECTORY_SEPARATOR . 'public' . \DIRECTORY_SEPARATOR . 'uploads' . \DIRECTORY_SEPARATOR . 'evenements' . \DIRECTORY_SEPARATOR . $basename,
        ];
        $filePath = null;
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                $filePath = $path;
                break;
            }
        }
        if ($filePath === null) {
            throw new NotFoundHttpException('Affiche introuvable.');
        }
        $mime = match (strtolower(pathinfo($basename, \PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        $response = new BinaryFileResponse($filePath, Response::HTTP_OK, ['Content-Type' => $mime], true);
        $response->headers->set('Cache-Control', 'private, max-age=300');
        return $response;
    }

    #[Route('/{id}/message', name: 'admin_evenement_message_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function replyMessage(Request $request, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
        $userId = $request->request->getInt('user_id');
        if ($userId <= 0) {
            $this->addFlash('error', 'Conversation invalide.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $id]);
        }
        $user = $this->userRepository->find($userId);
        if ($user === null) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $id]);
        }
        if (!$this->isCsrfTokenValid('admin_event_reply_' . $id . '_' . $userId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $id, 'user' => $userId]);
        }

        $message = new MessageEvenement();
        $message->setEvenement($evenement);
        $message->setUser($user);
        $message->setEnvoyePar(MessageEvenement::ENVOYE_PAR_ADMIN);
        $message->setDateEnvoi(new \DateTimeImmutable());
        $form = $this->createForm(MessageEvenementType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($message);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre réponse a été envoyée.');
        } else {
            $this->addFlash('error', 'Le message ne peut pas être vide.');
        }

        return $this->redirectToRoute('admin_evenement_show', ['id' => $id, 'user' => $userId]);
    }

    #[Route('/{id}/send-reminders', name: 'admin_evenement_send_reminders', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendReminders(Request $request, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
        $csrfToken = 'send_reminders_' . $id;
        if (!$this->isCsrfTokenValid($csrfToken, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $id]);
        }

        try {
            $result = $this->eventReminderService->sendRemindersForEvent($evenement, false);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi des rappels : ' . $e->getMessage());
            return $this->redirectToRoute('admin_evenement_show', ['id' => $id]);
        }

        $msg = sprintf(
            'Rappels envoyés : %d e-mail(s).',
            $result['sentEmail']
        );
        if ($result['sentEmail'] === 0 && $result['sentSms'] === 0) {
            $this->addFlash('warning', 'Aucun rappel envoyé. Vérifiez que l’événement a des participants avec le statut « Accepté » ou « En attente » et une adresse e-mail renseignée dans leur profil.');
        } elseif ($result['errors'] !== []) {
            $this->addFlash('warning', $msg . ' Quelques erreurs : ' . implode(' ; ', array_slice($result['errors'], 0, 3)));
        } else {
            $this->addFlash('success', $msg);
        }

        return $this->redirectToRoute('admin_evenement_show', ['id' => $id]);
    }

    #[Route('/{id}/generate-image', name: 'admin_evenement_generate_image', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function generateImage(Request $request, int $id): JsonResponse
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            return new JsonResponse(['error' => 'Événement introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_evenement_generate_image_' . $id, (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        $customPrompt = $request->request->get('prompt');
        $customPrompt = \is_string($customPrompt) ? trim($customPrompt) : '';

        if ($customPrompt !== '') {
            $prompt = mb_substr($customPrompt, 0, 1000);
            $prompt = 'Affiche, illustration. ' . $prompt . ' Style professionnel, couleurs harmonieuses.';
        } else {
            $parts = [];
            $title = $evenement->getTitle();
            if ($title !== null && $title !== '') {
                $parts[] = $title;
            }
            $desc = $evenement->getDescription();
            if ($desc !== null && $desc !== '') {
                $parts[] = mb_substr(strip_tags($desc), 0, 400);
            }
            if ($evenement->getThematique() !== null) {
                $parts[] = 'Thématique : ' . $evenement->getThematique()->getNomThematique();
            }
            if ($evenement->getDateEvent() !== null) {
                $parts[] = 'Date : ' . $evenement->getDateEvent()->format('d/m/Y');
            }
            $prompt = implode('. ', $parts);
            if ($prompt === '') {
                return new JsonResponse(['error' => 'Saisissez un prompt ou ajoutez au moins un titre / une description à l\'événement.'], Response::HTTP_BAD_REQUEST);
            }
            $prompt = 'Affiche pour un événement bienveillant, familles et inclusion. ' . $prompt . ' Style illustration douce, couleurs apaisantes, professionnel.';
        }

        $result = $this->huggingFaceService->generateImageFromPrompt($prompt, $id);
        if ($result === null) {
            $result = $this->huggingFaceService->generateImageFromPrompt($prompt, $id);
        }
        if ($result === null && mb_strlen($prompt) > 80) {
            $shortPrompt = mb_substr($prompt, 0, 80) . '. Illustration.';
            $result = $this->huggingFaceService->generateImageFromPrompt($shortPrompt, $id);
        }
        if ($result === null) {
            $err = $this->huggingFaceService->getLastApiError();
            $detail = (\is_array($err) && isset($err['message'])) ? (string) $err['message'] : '';
            $msg = 'La génération n\'a pas abouti. Réessayez dans un instant.';
            if ($detail !== '') {
                $msg .= ' (' . $detail . ')';
            }
            return new JsonResponse(['error' => $msg], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $imagePath = $result['path'];
        $evenement->setImage($imagePath);
        $this->entityManager->flush();

        $raw = $result['content'];
        $ext = strtolower(pathinfo($imagePath, \PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        $imageDataUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);

        $path = $this->urlGenerator->generate('admin_evenement_affiche', ['id' => $id]);
        $imageUrl = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $path . (str_contains($path, '?') ? '&' : '?') . 't=' . time();
        return new JsonResponse([
            'image_url' => $imageUrl,
            'image_path' => $imagePath,
            'image_data_url' => $imageDataUrl,
        ]);
    }

    #[Route('/{id}/liste-participants.pdf', name: 'admin_evenement_participants_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function participantsPdf(int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
        $participants = $this->inscritEventsRepository->findByEvenementOrderByDate($evenement);
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->addFlash('error', 'Export PDF indisponible : installez "composer require dompdf/dompdf" puis réessayez.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $id]);
        }
        $html = $this->renderView('admin/evenement/participants_pdf.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
        ]);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('defaultFont', 'DejaVu Sans');
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $datePart = $evenement->getDateEvent() ? $evenement->getDateEvent()->format('Y-m-d') : date('Y-m-d');
        $filename = 'participants-' . preg_replace('/[^a-zA-Z0-9\-]/', '-', (string) $evenement->getTitle()) . '-' . $datePart . '.pdf';
        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/inscriptions/{inscriptionId}/accepter', name: 'admin_evenement_inscription_accept', requirements: ['inscriptionId' => '\d+'], methods: ['POST'])]
    public function acceptInscription(Request $request, int $inscriptionId): Response
    {
        $inscription = $this->inscritEventsRepository->find($inscriptionId);
        if ($inscription === null) {
            throw new NotFoundHttpException('Inscription introuvable.');
        }
        if (!$this->isCsrfTokenValid('inscription_accept_' . $inscriptionId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $inscription->getEvenement()->getId()]);
        }
        $inscription->setStatut('accepte');
        $inscription->setEstInscrit(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'Inscription acceptée.');
        return $this->redirectToRoute('admin_evenement_show', ['id' => $inscription->getEvenement()->getId()]);
    }

    #[Route('/inscriptions/{inscriptionId}/refuser', name: 'admin_evenement_inscription_refuse', requirements: ['inscriptionId' => '\d+'], methods: ['POST'])]
    public function refuseInscription(Request $request, int $inscriptionId): Response
    {
        $inscription = $this->inscritEventsRepository->find($inscriptionId);
        if ($inscription === null) {
            throw new NotFoundHttpException('Inscription introuvable.');
        }
        if (!$this->isCsrfTokenValid('inscription_refuse_' . $inscriptionId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $inscription->getEvenement()->getId()]);
        }
        $inscription->setStatut('refuse');
        $inscription->setEstInscrit(false);
        $this->entityManager->flush();
        $this->addFlash('success', 'Inscription refusée.');
        return $this->redirectToRoute('admin_evenement_show', ['id' => $inscription->getEvenement()->getId()]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'événement a été modifié avec succès.');
            return $this->redirectToRoute('admin_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_evenement_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw new NotFoundHttpException('Événement introuvable.');
        }
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_evenement_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_evenement_index');
        }
        $this->entityManager->remove($evenement);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'événement a été supprimé.');
        return $this->redirectToRoute('admin_evenement_index');
    }

    #[Route('/geocode', name: 'admin_evenement_geocode', methods: ['GET'])]
    public function geocode(Request $request, NominatimGeocodingService $geocoding): JsonResponse
    {
        $address = $request->query->get('address');
        if (!is_string($address) || trim($address) === '') {
            return new JsonResponse(['error' => 'Paramètre address requis.'], Response::HTTP_BAD_REQUEST);
        }
        $result = $geocoding->geocode($address);
        if (isset($result['error'])) {
            return new JsonResponse(['error' => $result['error']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new JsonResponse(['lat' => $result['lat'], 'lng' => $result['lng']]);
    }

    /**
     * Génère un lien Zoom sans événement (pour formulaire de création).
     * POST: title, date (Y-m-d), heure_debut (H:i), heure_fin (H:i), mode, _token.
     */
    #[Route('/generer-zoom-lien', name: 'admin_evenement_generer_zoom_lien', methods: ['POST'])]
    public function genererZoomLien(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_evenement_generer_zoom_lien', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }
        $mode = \is_string($request->request->get('mode')) ? trim($request->request->get('mode')) : '';
        if (!\in_array($mode, ['en_ligne', 'hybride'], true)) {
            return new JsonResponse(['error' => 'Sélectionnez le mode « En ligne » ou « Hybride ».'], Response::HTTP_BAD_REQUEST);
        }
        $title = \is_string($request->request->get('title')) ? trim($request->request->get('title')) : '';
        if ($title === '') {
            return new JsonResponse(['error' => 'Saisissez un titre pour l\'événement.'], Response::HTTP_BAD_REQUEST);
        }
        $dateStr = \is_string($request->request->get('date')) ? trim($request->request->get('date')) : '';
        $heureDebutStr = \is_string($request->request->get('heure_debut')) ? trim($request->request->get('heure_debut')) : '';
        $heureFinStr = \is_string($request->request->get('heure_fin')) ? trim($request->request->get('heure_fin')) : '';
        if ($dateStr === '' || $heureDebutStr === '' || $heureFinStr === '') {
            return new JsonResponse(['error' => 'Date et horaires (début, fin) requis.'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $startTime = new \DateTime(
                $dateStr . ' ' . $heureDebutStr . ':00',
                new \DateTimeZone('Africa/Tunis')
            );
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Date ou heure de début invalide.'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $endTime = new \DateTime($dateStr . ' ' . $heureFinStr . ':00', new \DateTimeZone('Africa/Tunis'));
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Heure de fin invalide.'], Response::HTTP_BAD_REQUEST);
        }
        $durationMinutes = (int) round(($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);
        if ($durationMinutes < 15) {
            $durationMinutes = 60;
        }
        $result = $this->zoomApiService->createMeeting($title, $startTime, $durationMinutes, 'Africa/Tunis');
        if (isset($result['error'])) {
            return new JsonResponse(['error' => $result['error']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new JsonResponse(['join_url' => $result['join_url']]);
    }

    #[Route('/{id}/generer-zoom', name: 'admin_evenement_generer_zoom', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function genererZoom(Request $request, int $id): JsonResponse
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            return new JsonResponse(['error' => 'Événement introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if (!$this->isCsrfTokenValid('admin_evenement_generer_zoom_' . $id, (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }
        // Utiliser le mode envoyé par le formulaire (changement Présentiel → En ligne / Hybride sans enregistrer)
        $modeFromRequest = $request->request->get('mode');
        if ($modeFromRequest === null && $request->request->has('evenement')) {
            $data = $request->request->all('evenement');
            $modeFromRequest = $data['mode'] ?? null;
        }
        $modeFromRequest = \is_string($modeFromRequest) ? trim($modeFromRequest) : null;
        $mode = \in_array($modeFromRequest, ['en_ligne', 'hybride'], true) ? $modeFromRequest : $evenement->getMode();
        if ($mode !== 'en_ligne' && $mode !== 'hybride') {
            return new JsonResponse(['error' => 'Le lien Zoom est réservé aux événements en ligne ou hybrides. Sélectionnez « En ligne » ou « Hybride » et réessayez.'], Response::HTTP_BAD_REQUEST);
        }
        $dateEvent = $evenement->getDateEvent();
        $heureDebut = $evenement->getHeureDebut();
        $heureFin = $evenement->getHeureFin();
        if ($dateEvent === null || $heureDebut === null || $heureFin === null) {
            return new JsonResponse(['error' => 'Date et horaires requis pour créer la réunion Zoom.'], Response::HTTP_BAD_REQUEST);
        }
        $startTime = new \DateTime(
            $dateEvent->format('Y-m-d') . ' ' . $heureDebut->format('H:i:s'),
            new \DateTimeZone('Africa/Tunis')
        );
        $durationMinutes = (int) round(($heureFin->getTimestamp() - $heureDebut->getTimestamp()) / 60);
        if ($durationMinutes < 15) {
            $durationMinutes = 60;
        }
        $result = $this->zoomApiService->createMeeting(
            (string) $evenement->getTitle(),
            $startTime,
            $durationMinutes,
            'Africa/Tunis'
        );
        if (isset($result['error'])) {
            return new JsonResponse(['error' => $result['error']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $evenement->setMeetingUrl($result['join_url']);
        $this->entityManager->flush();
        return new JsonResponse(['join_url' => $result['join_url']]);
    }
}