<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Medcin;
use App\Entity\Produit;
use App\Entity\User;
use App\Entity\UserHistory;
use App\Repository\UserHistoryRepository;
use App\Repository\UserPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserBehaviorTrackerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserHistoryRepository $userHistoryRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
    ) {
    }

    /**
     * @param array<string,mixed> $metadata
     * @param list<string> $preferenceSignals
     */
    public function track(
        User $user,
        string $action,
        string $itemType,
        ?int $itemId,
        array $metadata = [],
        array $preferenceSignals = [],
        int $score = 1
    ): void {
        $history = (new UserHistory())
            ->setUser($user)
            ->setAction($action)
            ->setItemType($itemType)
            ->setItemId($itemId)
            ->setMetadata($metadata === [] ? null : $metadata)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($history);

        foreach ($preferenceSignals as $signal) {
            $this->userPreferenceRepository->incrementForUser($user, $signal, $score);
        }

        $this->entityManager->flush();
    }

    public function trackProductView(User $user, Produit $produit): void
    {
        $signals = [];
        if ($produit->getCategorie() !== null) {
            $signals[] = 'product_category:' . $produit->getCategorie()->value;
        }
        $this->track(
            $user,
            'view',
            'product',
            $produit->getId(),
            ['name' => $produit->getNom()],
            $signals,
            1
        );
    }

    public function trackEventView(User $user, Evenement $event): void
    {
        $signals = [];
        if ($event->getThematique()?->getId() !== null) {
            $signals[] = 'event_theme:' . (string) $event->getThematique()->getId();
        }
        $this->track(
            $user,
            'view',
            'event',
            $event->getId(),
            ['title' => $event->getTitle()],
            $signals,
            1
        );
    }

    public function trackDoctorView(User $user, Medcin $doctor): void
    {
        $signals = [];
        if ($doctor->getSpecialite() !== null && trim($doctor->getSpecialite()) !== '') {
            $signals[] = 'doctor_speciality:' . mb_strtolower(trim($doctor->getSpecialite()));
        }
        $this->track(
            $user,
            'view',
            'doctor',
            $doctor->getId(),
            ['name' => trim((string) $doctor->getNom() . ' ' . (string) $doctor->getPrenom())],
            $signals,
            1
        );
    }
}

