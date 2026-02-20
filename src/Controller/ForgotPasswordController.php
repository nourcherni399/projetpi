<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

#[Route('/mot-de-passe-oublie', name: 'app_forgot_password_')]
final class ForgotPasswordController extends AbstractController
{
    private const PIN_LENGTH = 6;
    private const PIN_VALID_MINUTES = 15;
    private const FROM_EMAIL = 'amarahedil8@gmail.com';
    private const FROM_NAME = 'AutiCare';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            if ($email === '') {
                $this->addFlash('error', 'Veuillez indiquer votre adresse e-mail.');
                return $this->redirectToRoute('app_forgot_password_request');
            }
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $pin = $this->generatePin();
                $user->setResetPin($pin);
                $user->setResetPinExpiresAt(new \DateTimeImmutable('+' . self::PIN_VALID_MINUTES . ' minutes'));
                $this->entityManager->flush();
                try {
                    $this->sendPinEmail($user->getEmail(), $pin);
                    $this->addFlash('success', 'Un e-mail avec votre code PIN a été envoyé à ' . $email . '. Il est valide 15 minutes.');
                } catch (\Throwable $e) {
                    $this->logger->error('Envoi e-mail récupération mot de passe échoué', ['exception' => $e, 'to' => $email]);
                    $this->addFlash('error', 'L\'envoi de l\'e-mail a échoué. Vérifiez votre configuration mail (MAILER_DSN) ou les paramètres du compte Gmail.');
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $this->addFlash('info', 'En développement : utilisez ce code PIN pour tester : ' . $pin);
                    }
                }
            } else {
                $this->addFlash('success', 'Si un compte existe avec cette adresse, un e-mail avec un code PIN vous a été envoyé. Vérifiez votre boîte de réception.');
            }
            return $this->redirectToRoute('app_forgot_password_request', ['email' => $email]);
        }

        $email = (string) ($request->query->get('email') ?? '');
        $sent = $email !== '' && $request->getSession()->getFlashBag()->has('success');

        return $this->render('front/auth/forgot_password_request.html.twig', [
            'email' => $email,
            'sent' => $sent,
        ]);
    }

    #[Route('/reinitialiser', name: 'reset', methods: ['GET', 'POST'])]
    public function reset(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $email = trim((string) ($request->request->get('email') ?? $request->query->get('email') ?? ''));
        $pin = trim((string) ($request->request->get('pin') ?? $request->query->get('pin') ?? ''));

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('password_confirm');
            if ($email === '' || $pin === '') {
                $this->addFlash('error', 'Veuillez indiquer votre e-mail et le code PIN reçu.');
            } elseif (strlen($newPassword ?? '') < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');
            } else {
                $user = $this->userRepository->findOneBy(['email' => $email]);
                if (!$user instanceof User) {
                    $this->addFlash('error', 'Adresse e-mail ou code PIN incorrect.');
                } elseif ($user->getResetPin() !== $pin) {
                    $this->addFlash('error', 'Code PIN incorrect ou expiré.');
                } elseif ($user->getResetPinExpiresAt() === null || $user->getResetPinExpiresAt() < new \DateTimeImmutable()) {
                    $this->addFlash('error', 'Ce code PIN a expiré. Demandez un nouveau code.');
                } else {
                    $user->setPassword($this->passwordHasher->hashPassword($user, (string) $newPassword));
                    $user->setResetPin(null);
                    $user->setResetPinExpiresAt(null);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
                    return $this->redirectToRoute('app_login');
                }
            }
            return $this->redirectToRoute('app_forgot_password_reset', ['email' => $email, 'pin' => $pin]);
        }

        $errorMessages = $request->getSession()->getFlashBag()->get('error', []);
        $error = $errorMessages[0] ?? '';

        return $this->render('front/auth/forgot_password_reset.html.twig', [
            'email' => $email,
            'pin' => $pin,
            'error' => $error,
        ]);
    }

    private function generatePin(): string
    {
        $digits = '';
        for ($i = 0; $i < self::PIN_LENGTH; $i++) {
            $digits .= (string) random_int(0, 9);
        }
        return $digits;
    }

    private function sendPinEmail(string $toEmail, string $pin): void
    {
        $resetUrl = $this->urlGenerator->generate('app_forgot_password_reset', [
            'email' => $toEmail,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($toEmail)
            ->subject('Réinitialisation de votre mot de passe - AutiCare')
            ->html($this->getEmailHtml($pin, $resetUrl));
        $this->mailer->send($email);
    }

    private function getEmailHtml(string $pin, string $resetUrl): string
    {
        $resetUrl = htmlspecialchars($resetUrl, \ENT_QUOTES, 'UTF-8');
        $pin = htmlspecialchars($pin, \ENT_QUOTES, 'UTF-8');
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><style>body{font-family:sans-serif;background:#f5f1eb;padding:20px;color:#4B5563;} .box{max-width:400px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #E5E0D8;} .pin{font-size:28px;font-weight:bold;letter-spacing:8px;color:#A7C7E7;text-align:center;margin:20px 0;} p{line-height:1.6;} .btn{display:inline-block;margin:16px 0;padding:14px 28px;background:#A7C7E7;color:#fff!important;text-decoration:none;border-radius:8px;font-weight:bold;} .footer{font-size:12px;color:#6B7280;margin-top:24px;}</style></head>
        <body>
        <div class="box">
        <p>Bonjour,</p>
        <p>Vous avez demandé à réinitialiser votre mot de passe sur AutiCare.</p>
        <p>Voici votre code PIN à usage unique (valide 15 minutes) :</p>
        <div class="pin">{$pin}</div>
        <p><strong>Cliquez sur le bouton ci-dessous</strong> pour ouvrir la page où saisir ce code et définir votre nouveau mot de passe :</p>
        <p style="text-align:center;"><a href="{$resetUrl}" class="btn">Définir mon mot de passe</a></p>
        <p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><a href="{$resetUrl}">{$resetUrl}</a></p>
        <p>Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</p>
        <p class="footer">— L'équipe AutiCare</p>
        </div>
        </body>
        </html>
        HTML;
    }
}
