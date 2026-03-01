<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Medcin;
use App\Entity\Patient;
use App\Entity\ParentUser;
use App\Entity\User;
use App\Enum\UserRole;
use App\Form\ProfileType;
use App\Form\UserCreateType;
use App\Form\UserEditType;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\ProduitRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'totalEvenements' => $this->evenementRepository->countAll(),
            'totalUsers' => $this->userRepository->count([]),
            'totalCommandes' => $this->commandeRepository->count([]),
            'totalProduits' => $this->produitRepository->count([]),
        ]);
    }

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

    #[Route('/admin/utilisateurs', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): Response
    {
        $order = $request->query->get('order', 'asc');
        if (!in_array(strtolower($order), ['asc', 'desc'], true)) {
            $order = 'asc';
        }
        $search = $request->query->get('q', '');
        $users = $this->userRepository->findAllOrdered($order, $search !== '' ? $search : null);
        $stats = $this->userRepository->getStats();
        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'order' => $order,
            'search' => $search,
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
            $imageFile = $form->get('image')->getData();
            if ($imageFile instanceof UploadedFile) {
                $this->handleUserImageUpload($imageFile, $user);
            }
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword !== null && is_array($plainPassword) && trim((string) ($plainPassword['first'] ?? '')) !== '') {
                $user->setPassword($this->passwordHasher->hashPassword($user, (string) $plainPassword['first']));
            }
            $selectedRole = $form->get('role')->getData();
            if ($selectedRole instanceof UserRole) {
                $user->setRole($selectedRole);
            }
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            if ($selectedRole instanceof UserRole) {
                $this->syncEditedUserTypeAndRoleData($user, $selectedRole, $form);
            }
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

            $imageFile = $form->get('image')->getData();
            if ($imageFile instanceof UploadedFile) {
                $this->handleUserImageUpload($imageFile, $user);
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

    private function syncEditedUserTypeAndRoleData(User $user, UserRole $role, FormInterface $form): void
    {
        $connection = $this->entityManager->getConnection();
        $userMeta = $this->entityManager->getClassMetadata(User::class);
        $patientMeta = $this->entityManager->getClassMetadata(Patient::class);
        $parentMeta = $this->entityManager->getClassMetadata(ParentUser::class);
        $medecinMeta = $this->entityManager->getClassMetadata(Medcin::class);

        $updates = [
            $userMeta->getColumnName('role') => $role->value,
            'type' => $this->resolveDiscriminatorForRole($role),
            $medecinMeta->getColumnName('specialite') => null,
            $medecinMeta->getColumnName('nomCabinet') => null,
            $medecinMeta->getColumnName('adresseCabinet') => null,
            $medecinMeta->getColumnName('telephoneCabinet') => null,
            $medecinMeta->getColumnName('tarifConsultation') => null,
            $parentMeta->getColumnName('relationAvecPatient') => null,
            $patientMeta->getColumnName('dateNaissance') => null,
            $patientMeta->getColumnName('adresse') => null,
            $patientMeta->getColumnName('sexe') => null,
        ];

        if ($role === UserRole::MEDECIN) {
            $updates[$medecinMeta->getColumnName('specialite')] = $form->get('specialite')->getData();
            $updates[$medecinMeta->getColumnName('nomCabinet')] = $form->get('nomCabinet')->getData();
            $updates[$medecinMeta->getColumnName('adresseCabinet')] = $form->get('adresseCabinet')->getData();
            $updates[$medecinMeta->getColumnName('telephoneCabinet')] = $form->get('telephoneCabinet')->getData();
            $tarif = $form->get('tarifConsultation')->getData();
            $updates[$medecinMeta->getColumnName('tarifConsultation')] = $tarif !== null ? (float) $tarif : null;
        } elseif ($role === UserRole::PARENT) {
            $updates[$parentMeta->getColumnName('relationAvecPatient')] = $form->get('relationAvecPatient')->getData();
        } elseif ($role === UserRole::PATIENT || $role === UserRole::USER) {
            $dn = $form->get('dateNaissance')->getData();
            $updates[$patientMeta->getColumnName('dateNaissance')] = $dn instanceof \DateTimeInterface ? $dn->format('Y-m-d') : null;
            $updates[$patientMeta->getColumnName('adresse')] = $form->get('adresse')->getData();
            $sexe = $form->get('sexe')->getData();
            $updates[$patientMeta->getColumnName('sexe')] = $sexe?->value;
        }

        $connection->update(
            $userMeta->getTableName(),
            $updates,
            [$userMeta->getColumnName('id') => $user->getId()]
        );
    }

    private function resolveDiscriminatorForRole(UserRole $role): string
    {
        return match ($role) {
            UserRole::ADMIN => 'admin',
            UserRole::MEDECIN => 'medcin',
            UserRole::PARENT => 'parent',
            UserRole::PATIENT, UserRole::USER => 'patient',
        };
    }

    private function handleUserImageUpload(UploadedFile $imageFile, User $user): bool
    {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $dir = $this->getParameter('uploads_users_directory');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $imageFile->move($dir, $newFilename);
            $user->setImage('uploads/users/' . $newFilename);
            return true;
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de la photo.');
            return false;
        }
    }
}