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
use App\Repository\UserRepository;
use App\Service\NominatimGeocodingService;
use App\Service\ZoomApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evenements')]
final class EvenementController extends AbstractController
{
    public function __construct(
        private readonly EvenementRepository $evenementRepository,
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly MessageEvenementRepository $messageEvenementRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ZoomApiService $zoomApiService,
    ) {
    }

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
        $totalEvenements = $this->evenementRepository->countAll();
        $evenementsAvenir = $this->evenementRepository->countUpcoming();
        $evenementsPasses = $this->evenementRepository->countPast();
        $inscriptionsAcceptees = $this->inscritEventsRepository->countByStatut('accepte');
        $inscriptionsEnAttente = $this->inscritEventsRepository->countByStatut('en_attente');
        $totalInscriptions = $this->inscritEventsRepository->countTotalInscriptions();

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenements,
            'q' => $q,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'totalEvenements' => $totalEvenements,
            'evenementsAvenir' => $evenementsAvenir,
            'evenementsPasses' => $evenementsPasses,
            'inscriptionsAcceptees' => $inscriptionsAcceptees,
            'inscriptionsEnAttente' => $inscriptionsEnAttente,
            'totalInscriptions' => $totalInscriptions,
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $evenement = new Evenement();
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

        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
            'conversations' => $conversations,
            'unreadMessagesCount' => $unreadCount,
            'conversationUser' => $conversationUser,
            'conversationMessages' => $conversationMessages,
            'replyForm' => $replyForm,
        ]);
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
        $mode = $evenement->getMode();
        if ($mode !== 'en_ligne' && $mode !== 'hybride') {
            return new JsonResponse(['error' => 'Le lien Zoom est réservé aux événements en ligne ou hybrides.'], Response::HTTP_BAD_REQUEST);
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