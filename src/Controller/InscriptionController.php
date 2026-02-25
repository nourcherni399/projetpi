<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Patient;
use App\Entity\ParentUser;
use App\Enum\UserRole;
use App\Form\InscriptionType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Attribute\Route;

final class InscriptionController extends AbstractController
{
    private const VERIFY_TOKEN_VALID_HOURS = 24;
    private const FROM_EMAIL = 'amarahedil8@gmail.com';
    private const FROM_NAME = 'AutiCare';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
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

            if ($email === '' || strlen($email) > 180) {
                $this->addFlash('error', 'Adresse e-mail invalide.');
                $tp = $request->query->get('_target_path') ?? $request->request->get('_target_path');
                return $this->render('front/auth/register.html.twig', ['form' => $form, 'target_path' => $tp]);
            }

            if ($this->userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Un compte existe déjà avec cette adresse e-mail.');
                $tp = $request->query->get('_target_path') ?? $request->request->get('_target_path');
                return $this->render('front/auth/register.html.twig', ['form' => $form, 'target_path' => $tp]);
            }

            $role = $data['role'];
            if (!$role instanceof UserRole || !\in_array($role, [UserRole::PATIENT, UserRole::PARENT], true)) {
                $this->addFlash('error', 'Profil non autorisé pour l\'inscription.');
                $tp = $request->query->get('_target_path') ?? $request->request->get('_target_path');
                return $this->render('front/auth/register.html.twig', ['form' => $form, 'target_path' => $tp]);
            }

            try {
                $user = $role === UserRole::PARENT ? new ParentUser() : new Patient();
                $user->setNom(mb_substr(trim((string) ($data['nom'] ?? '')), 0, 255));
                $user->setPrenom(mb_substr(trim((string) ($data['prenom'] ?? '')), 0, 255));
                $user->setEmail($email);
                $user->setTelephone((int) ($data['telephone'] ?? 0));
                $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
                $user->setIsActive(false);
                $user->setRole($role);
                $now = new \DateTimeImmutable();
                $user->setCreatedAt($now);
                $user->setUpdatedAt($now);
                $verifyToken = $this->generateEmailVerificationToken();
                $user->setEmailVerificationToken($verifyToken);
                $user->setEmailVerificationExpiresAt($now->modify('+' . self::VERIFY_TOKEN_VALID_HOURS . ' hours'));
                $user->setEmailVerifiedAt(null);

                $dataFaceApiRaw = (string) $form->get('dataFaceApi')->getData();
                if ($dataFaceApiRaw !== '') {
                    $decoded = json_decode($dataFaceApiRaw, true);
                    if (is_array($decoded) && count($decoded) === 128) {
                        $user->setDataFaceApi(json_encode(array_map('floatval', $decoded), JSON_THROW_ON_ERROR));
                    }
                }

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

                try {
                    $this->sendVerificationEmail($user, $verifyToken);
                } catch (\Throwable $e) {
                    $this->logger->error('Envoi e-mail de confirmation échoué', [
                        'exception' => $e,
                        'user_id' => $user->getId(),
                        'email' => $user->getEmail(),
                    ]);
                    $this->addFlash('error', 'Le compte a été créé, mais l\'e-mail de confirmation n\'a pas pu être envoyé. Veuillez réessayer plus tard.');
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $url = $this->urlGenerator->generate('app_register_verify_email', ['token' => $verifyToken], UrlGeneratorInterface::ABSOLUTE_URL);
                        $this->addFlash('info', 'Lien de confirmation (dev): ' . $url);
                    }
                    return $this->redirectToRoute('app_login');
                }

                $this->addFlash('success', 'Votre compte a été créé. Vérifiez votre e-mail pour l\'activer.');
                $targetPath = $request->query->get('_target_path') ?? $request->request->get('_target_path');
                if (\is_string($targetPath) && $targetPath !== '' && str_starts_with($targetPath, '/') && !str_starts_with($targetPath, '//')) {
                    return $this->redirectToRoute('app_login', ['_target_path' => $targetPath]);
                }
                return $this->redirectToRoute('app_login');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.');
                $tp = $request->query->get('_target_path') ?? $request->request->get('_target_path');
                return $this->render('front/auth/register.html.twig', ['form' => $form, 'target_path' => $tp]);
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
        }

        $targetPath = $request->query->get('_target_path') ?? $request->request->get('_target_path');

        return $this->render('front/auth/register.html.twig', [
            'form' => $form,
            'target_path' => $targetPath,
        ]);
    }

    #[Route('/inscription/verification/{token}', name: 'app_register_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): Response
    {
        $token = trim($token);
        if ($token === '') {
            $this->addFlash('error', 'Lien de confirmation invalide.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->findOneByEmailVerificationToken($token);
        if (!$user) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou déjà utilisé.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getEmailVerificationExpiresAt() === null || $user->getEmailVerificationExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Le lien de confirmation a expiré. Veuillez vous réinscrire.');
            return $this->redirectToRoute('register');
        }

        $user->setIsActive(true);
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationExpiresAt(null);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre e-mail a été confirmé. Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/inscription/renvoyer-confirmation', name: 'app_register_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $token = (string) $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('resend_verification_email', $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_login');
        }

        $email = trim((string) ($request->request->get('resend_email') ?? $request->request->get('email') ?? ''));
        if ($email === '') {
            $this->addFlash('error', 'Veuillez indiquer votre e-mail.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof \App\Entity\User) {
            $this->addFlash('success', 'Si un compte non activé existe avec cet e-mail, un nouveau lien de confirmation a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isActive() === true && $user->getEmailVerifiedAt() !== null) {
            $this->addFlash('info', 'Ce compte est déjà activé. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        $verifyToken = $this->generateEmailVerificationToken();
        $now = new \DateTimeImmutable();
        $user->setEmailVerificationToken($verifyToken);
        $user->setEmailVerificationExpiresAt($now->modify('+' . self::VERIFY_TOKEN_VALID_HOURS . ' hours'));
        $user->setEmailVerifiedAt(null);
        $user->setUpdatedAt($now);
        $this->entityManager->flush();

        try {
            $this->sendVerificationEmail($user, $verifyToken);
            $this->addFlash('success', 'Un nouveau lien de confirmation a été envoyé à votre e-mail.');
        } catch (\Throwable $e) {
            $this->logger->error('Renvoi e-mail de confirmation échoué', [
                'exception' => $e,
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);
            $this->addFlash('error', 'Impossible d\'envoyer le lien pour le moment. Réessayez plus tard.');
            if ($this->getParameter('kernel.environment') === 'dev') {
                $url = $this->urlGenerator->generate('app_register_verify_email', ['token' => $verifyToken], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->addFlash('info', 'Lien de confirmation (dev): ' . $url);
            }
        }

        return $this->redirectToRoute('app_login');
    }

    private function generateEmailVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function sendVerificationEmail(\App\Entity\User $user, string $token): void
    {
        $verifyUrl = $this->urlGenerator->generate(
            'app_register_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars(trim((string) $user->getPrenom() . ' ' . (string) $user->getNom()), ENT_QUOTES, 'UTF-8');

        $email = (new Email())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to((string) $user->getEmail())
            ->subject('Confirmez votre compte - AutiCare')
            ->html(
                <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <style>
                        body{font-family:sans-serif;background:#f5f1eb;padding:20px;color:#4B5563;}
                        .box{max-width:460px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #E5E0D8;}
                        .btn{display:inline-block;margin:16px 0;padding:14px 28px;background:#A7C7E7;color:#fff!important;text-decoration:none;border-radius:8px;font-weight:bold;}
                        .footer{font-size:12px;color:#6B7280;margin-top:24px;}
                    </style>
                </head>
                <body>
                <div class="box">
                    <p>Bonjour {$safeName},</p>
                    <p>Merci pour votre inscription sur AutiCare.</p>
                    <p>Pour activer votre compte, cliquez sur le bouton ci-dessous (lien valable 24 heures) :</p>
                    <p style="text-align:center;"><a href="{$safeUrl}" class="btn">Confirmer mon e-mail</a></p>
                    <p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><a href="{$safeUrl}">{$safeUrl}</a></p>
                    <p class="footer">— L'équipe AutiCare</p>
                </div>
                </body>
                </html>
                HTML
            );
        $this->mailer->send($email);
    }
}