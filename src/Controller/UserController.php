<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/user', name: 'user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('home');
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
