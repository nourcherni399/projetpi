<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

<<<<<<< HEAD
=======
    #[Route('/admin/profil', name: 'admin_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminProfile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileType::class, $user, [
            'data_class' => $user::class,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre profil administrateur a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/medecin/profil', name: 'doctor_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEDECIN')]
    public function doctorProfile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileType::class, $user, [
            'data_class' => $user::class,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre profil médecin a été mis à jour avec succès.');

            return $this->redirectToRoute('doctor_profile');
        }

        return $this->render('medecin/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

>>>>>>> origin/integreModule
    #[Route('/mon-profil', name: 'user_profile', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (method_exists($user, 'getRole')) {
            $role = $user->getRole();
            if ($role === UserRole::ADMIN) {
                return $this->redirectToRoute('admin_profile');
            }
            if ($role === UserRole::MEDECIN) {
                return $this->redirectToRoute('doctor_profile');
            }
        }

        $form = $this->createForm(ProfileType::class, $user, [
            'data_class' => $user::class,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('user_profile');
        }

        return $this->render('front/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/mon-profil/desactiver', name: 'user_profile_deactivate', methods: ['POST'])]
    public function deactivate(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (method_exists($user, 'getRole')) {
            $role = $user->getRole();
            if ($role === \App\Enum\UserRole::ADMIN || $role === \App\Enum\UserRole::MEDECIN) {
                return $this->redirectToRoute('user_profile');
            }
        }
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('user_deactivate', $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('user_profile');
        }
        $user->setIsActive(false);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->addFlash('success', 'Votre compte a été désactivé. Vous pouvez le réactiver en contactant l’équipe.');
        return $this->redirectToRoute('app_logout');
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> origin/integreModule
