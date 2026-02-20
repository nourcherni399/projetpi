<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\EvenementRepository;
use App\Service\EventReminderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cron', name: 'api_cron_', methods: ['GET', 'POST'])]
final class CronController extends AbstractController
{
    public function __construct(
        private readonly EvenementRepository $evenementRepository,
        private readonly EventReminderService $eventReminderService,
    ) {
    }

    /**
     * API rappels événements : envoie e-mail + SMS aux inscrits des événements dans les 24 h.
     * Méthode : GET ou POST.
     * Auth : header X-Cron-Token ou query ?token= (REMINDER_SECRET).
     * À appeler par un cron (cron-job.org, crontab, etc.).
     */
    #[Route('/event-reminders', name: 'event_reminders', methods: ['GET', 'POST'])]
    public function eventReminders(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-Cron-Token') ?? $request->query->get('token', '');
        $expected = $this->getParameter('reminder_secret');
        if (!\is_string($expected) || $token === '' || $token !== $expected) {
            return new JsonResponse(['error' => 'Unauthorized', 'message' => 'Token invalide ou manquant.'], Response::HTTP_UNAUTHORIZED);
        }

        $now = new \DateTimeImmutable('now');
        $in24h = $now->modify('+24 hours');
        $events = $this->evenementRepository->findUpcomingForReminder($now, $in24h);

        $sentEmail = 0;
        $sentSms = 0;
        $errors = [];

        foreach ($events as $event) {
            $result = $this->eventReminderService->sendRemindersForEvent($event, true);
            $sentEmail += $result['sentEmail'];
            $sentSms += $result['sentSms'];
            $errors = array_merge($errors, $result['errors']);
        }

        return new JsonResponse([
            'ok' => true,
            'events_checked' => \count($events),
            'reminders_email_sent' => $sentEmail,
            'reminders_sms_sent' => $sentSms,
            'sms_method' => 'email_to_sms',
            'errors' => $errors,
        ]);
    }
}
