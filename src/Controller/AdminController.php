<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Medcin;
use App\Entity\Patient;
use App\Entity\ParentUser;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\UserCreateType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/admin/utilisateurs', name: 'admin_users', methods: ['GET'])]
    public function users(): Response
    {
        $users = $this->userRepository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);
        return $this->render('admin/users/index.html.twig', ['users' => $users]);
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
