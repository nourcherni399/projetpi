<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Medcin;
use App\Entity\Patient;
use App\Entity\ParentUser;
use App\Entity\User;
use App\Enum\UserRole;
<<<<<<< HEAD
use App\Form\ProfileType;
=======
>>>>>>> origin/integreModule
use App\Form\UserCreateType;
use App\Form\UserEditType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard/index.html.twig');
    }

<<<<<<< HEAD
    #[Route('/admin/mon-profil', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $form = $this->createForm(ProfileType::class, $user, ['data_class' => $user::class]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('admin_profile');
        }
        return $this->render('admin/profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

=======
>>>>>>> origin/integreModule
    #[Route('/admin/utilisateurs', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $order = $request->query->get('order', 'asc');
        if (!in_array(strtolower($order), ['asc', 'desc'], true)) {
            $order = 'asc';
        }
        $users = $this->userRepository->findAllOrdered($order);
        $stats = $this->userRepository->getStats();
        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'order' => $order,
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}', name: 'admin_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function userShow(int $id): Response
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }
        $userType = $user instanceof Medcin ? 'medcin' : ($user instanceof ParentUser ? 'parent' : ($user instanceof Admin ? 'admin' : 'patient'));
        return $this->render('admin/users/show.html.twig', ['user' => $user, 'userType' => $userType]);
    }

    #[Route('/admin/utilisateurs/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function userEdit(Request $request, int $id): Response
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }
        $form = $this->createForm(UserEditType::class, $user);
        if ($user instanceof Medcin) {
            $form->get('specialite')->setData($user->getSpecialite());
            $form->get('nomCabinet')->setData($user->getNomCabinet());
            $form->get('adresseCabinet')->setData($user->getAdresseCabinet());
            $form->get('telephoneCabinet')->setData($user->getTelephoneCabinet());
            $form->get('tarifConsultation')->setData($user->getTarifConsultation());
        }
        if ($user instanceof ParentUser) {
            $form->get('relationAvecPatient')->setData($user->getRelationAvecPatient());
        }
        if ($user instanceof Patient) {
            $form->get('dateNaissance')->setData($user->getDateNaissance());
            $form->get('adresse')->setData($user->getAdresse());
            $form->get('sexe')->setData($user->getSexe());
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword !== null && is_array($plainPassword) && trim((string) ($plainPassword['first'] ?? '')) !== '') {
                $user->setPassword($this->passwordHasher->hashPassword($user, (string) $plainPassword['first']));
            }
            $user->setUpdatedAt(new \DateTimeImmutable());
            if ($user instanceof Medcin && $form->has('specialite')) {
                $user->setSpecialite($form->get('specialite')->getData());
                $user->setNomCabinet($form->get('nomCabinet')->getData());
                $user->setAdresseCabinet($form->get('adresseCabinet')->getData());
                $user->setTelephoneCabinet($form->get('telephoneCabinet')->getData());
                $tarif = $form->get('tarifConsultation')->getData();
                $user->setTarifConsultation($tarif !== null ? (float) $tarif : null);
            }
            if ($user instanceof ParentUser && $form->has('relationAvecPatient')) {
                $user->setRelationAvecPatient($form->get('relationAvecPatient')->getData());
            }
            if ($user instanceof Patient && $form->has('dateNaissance')) {
                $dn = $form->get('dateNaissance')->getData();
                $user->setDateNaissance($dn instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($dn) : null);
                $user->setAdresse($form->get('adresse')->getData());
                $user->setSexe($form->get('sexe')->getData());
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'utilisateur a été modifié avec succès.');
            return $this->redirectToRoute('admin_user_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/admin/utilisateurs/{id}/supprimer', name: 'admin_user_confirm_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function userConfirmDelete(int $id): Response
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }
        return $this->render('admin/users/delete_confirm.html.twig', ['user' => $user]);
    }

    #[Route('/admin/utilisateurs/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function userDelete(Request $request, int $id): Response
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_user_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_users');
        }
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        $this->addFlash('success', 'L\'utilisateur a été supprimé.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/utilisateurs/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function userNew(Request $request): Response
    {
        $form = $this->createForm(UserCreateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $user = $this->createUserFromRole($data['role']);
            $user->setNom($data['nom']);
            $user->setPrenom($data['prenom']);
            $user->setEmail($data['email']);
            $user->setTelephone((int) $data['telephone']);
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
            $user->setIsActive($data['isActive'] ?? true);
            $user->setRole($data['role']);
            $now = new \DateTimeImmutable();
            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);

            if ($user instanceof Medcin) {
                $user->setSpecialite($data['specialite'] ?? null);
                $user->setNomCabinet($data['nomCabinet'] ?? null);
                $user->setAdresseCabinet($data['adresseCabinet'] ?? null);
                $user->setTelephoneCabinet($data['telephoneCabinet'] ?? null);
                $user->setTarifConsultation(isset($data['tarifConsultation']) ? (float) $data['tarifConsultation'] : null);
            }
            if ($user instanceof ParentUser) {
                $user->setRelationAvecPatient($data['relationAvecPatient'] ?? null);
            }
            if ($user instanceof Patient) {
                $dn = $data['dateNaissance'] ?? null;
                $user->setDateNaissance($dn instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($dn) : null);
                $user->setAdresse($data['adresse'] ?? null);
                $user->setSexe($data['sexe'] ?? null);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'utilisateur a été créé avec succès.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form,
        ]);
    }

    private function createUserFromRole(UserRole $role): User
    {
        return match ($role) {
            UserRole::ADMIN => new Admin(),
            UserRole::PATIENT => new Patient(),
            UserRole::MEDECIN => new Medcin(),
            UserRole::PARENT => new ParentUser(),
            UserRole::USER => new Patient(),
        };
    }
}
