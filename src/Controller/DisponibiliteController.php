<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DisponibiliteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/disponibilites')]
final class DisponibiliteController extends AbstractController
{
    public function __construct(
        private readonly DisponibiliteRepository $disponibiliteRepository,
    ) {
    }

    #[Route('', name: 'admin_disponibilite_index', methods: ['GET'])]
    public function index(): Response
    {
        $disponibilites = $this->disponibiliteRepository->findAll();
        return $this->render('admin/disponibilite/index.html.twig', ['disponibilites' => $disponibilites]);
    }
}
