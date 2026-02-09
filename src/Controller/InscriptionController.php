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

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
            if ($this->userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Un compte existe déjà avec cette adresse e-mail.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }
<<<<<<< HEAD
            
            if (!$role instanceof UserRole || !\in_array($role, [UserRole::MEDECIN, UserRole::PATIENT, UserRole::PARENT, UserRole::USER], true)) {
=======

            $role = $data['role'];
            if (!$role instanceof UserRole || !\in_array($role, [UserRole::PATIENT, UserRole::PARENT, UserRole::USER], true)) {
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
                $this->addFlash('error', 'Profil non autorisé pour l\'inscription.');
                return $this->render('front/auth/register.html.twig', ['form' => $form]);
            }

            try {
<<<<<<< HEAD
                $user = match ($role) {
                    UserRole::MEDECIN => new Medcin(),
                    UserRole::PARENT => new ParentUser(),
                    UserRole::PATIENT => new Patient(),
                    default => new Patient(),
                };
=======
                $user = $role === UserRole::PARENT ? new ParentUser() : new Patient();
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
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
<<<<<<< HEAD
                    $sexe = isset($data['sexe']) && $data['sexe'] !== '' ? trim((string) $data['sexe']) : null;
                    $user->setSexe($sexe !== null && $sexe !== '' ? mb_substr($sexe, 0, 20) : null);
                }
                if ($user instanceof Medcin) {
                    $specialite = isset($data['specialite']) && $data['specialite'] !== '' ? trim((string) $data['specialite']) : null;
                    $user->setSpecialite($specialite !== null && $specialite !== '' ? mb_substr($specialite, 0, 255) : null);
                    
                    $nomCabinet = isset($data['nomCabinet']) && $data['nomCabinet'] !== '' ? trim((string) $data['nomCabinet']) : null;
                    $user->setNomCabinet($nomCabinet !== null && $nomCabinet !== '' ? mb_substr($nomCabinet, 0, 255) : null);
                    
                    $adresseCabinet = isset($data['adresseCabinet']) && $data['adresseCabinet'] !== '' ? trim((string) $data['adresseCabinet']) : null;
                    $user->setAdresseCabinet($adresseCabinet !== null && $adresseCabinet !== '' ? mb_substr($adresseCabinet, 0, 500) : null);
                    
                    $telephoneCabinet = isset($data['telephoneCabinet']) && $data['telephoneCabinet'] !== '' ? trim((string) $data['telephoneCabinet']) : null;
                    $user->setTelephoneCabinet($telephoneCabinet !== null && $telephoneCabinet !== '' ? mb_substr($telephoneCabinet, 0, 20) : null);
                    
                    $tarifConsultation = isset($data['tarifConsultation']) ? (int) $data['tarifConsultation'] : null;
                    $user->setTarifConsultation($tarifConsultation > 0 ? $tarifConsultation : null);

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

