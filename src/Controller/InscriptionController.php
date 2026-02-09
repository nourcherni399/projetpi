<?php declare(strict_types=1);

namespace App\Controller;


use App\Entity\Medcin;
use App\Entity\Patient;
use App\Entity\ParentUser;
use App\Enum\UserRole;
use App\Form\InscriptionType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/inscription', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $form = $this->createForm(InscriptionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            $email = trim((string) ($data['email'] ?? ''));
            $role = $data['role'] ?? null;

            $email = trim((string) ($data['email'] ?? ''));


            if ($email === '' || strlen($email) > 180) {
                $this->addFlash('error', 'Adresse e-mail invalide.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }

            if (!$role instanceof UserRole) {
                $this->addFlash('error', 'Rôle invalide.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }

            if ($this->userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Un compte existe déjà avec cette adresse e-mail.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }

            $role = $data['role'];
            if (!$role instanceof UserRole || !\in_array($role, [UserRole::PATIENT, UserRole::PARENT, UserRole::USER], true)) {
                $this->addFlash('error', 'Profil non autorisé pour l\'inscription.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }

            try {
                $user = match ($role) {
                    UserRole::PARENT => new ParentUser(),
                    UserRole::PATIENT => new Patient(),
                    default => new Patient(),
                };
                $user->setNom(mb_substr(trim((string) ($data['nom'] ?? '')), 0, 255));
                $user->setPrenom(mb_substr(trim((string) ($data['prenom'] ?? '')), 0, 255));
                $user->setEmail($email);
                $user->setTelephone((int) ($data['telephone'] ?? 0));
                $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
                $user->setIsActive(true);
                $user->setRole($role);
                $now = new \DateTimeImmutable();
                $user->setCreatedAt($now);
                $user->setUpdatedAt($now);

                if ($user instanceof ParentUser) {
                    $rel = isset($data['relationAvecPatient']) && $data['relationAvecPatient'] !== '' ? trim((string) $data['relationAvecPatient']) : null;
                    $user->setRelationAvecPatient($rel !== null && $rel !== '' ? mb_substr($rel, 0, 100) : null);
                }
                if ($user instanceof Patient) {
                    $dn = $data['dateNaissance'] ?? null;
                    $user->setDateNaissance($dn instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($dn) : null);
                    $adresse = isset($data['adresse']) && $data['adresse'] !== '' ? trim((string) $data['adresse']) : null;
                    $user->setAdresse($adresse !== null && $adresse !== '' ? mb_substr($adresse, 0, 500) : null);
                    
                    $sexe = $data['sexe'] ?? null;
                    $user->setSexe($sexe instanceof \App\Enum\Sexe ? $sexe : null);
                }

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
        }

        return $this->render('front/auth/register.html.twig', [
            'form' => $form,
        ]);
    }
}

