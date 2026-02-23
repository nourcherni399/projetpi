<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Valide les champs email et mot de passe du formulaire de connexion côté PHP
 * (sans validation HTML5) et redirige vers la page de login avec les erreurs si besoin.
 */
final class LoginValidationSubscriber implements EventSubscriberInterface
{
    private const LOGIN_PATH = '/connexion';
    public const SESSION_LAST_USERNAME = 'app_login_last_username';
    public const SESSION_VALIDATION_ERRORS = 'app_login_validation_errors';
    public const SESSION_CAPTCHA_QUESTION = 'app_login_captcha_question';
    public const SESSION_CAPTCHA_EXPECTED = 'app_login_captcha_expected';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->isMethod('POST') || $request->getPathInfo() !== self::LOGIN_PATH) {
            return;
        }

        $email = trim((string) $request->request->get('email', ''));
        $password = $request->request->get('password');
        $captchaAnswer = trim((string) $request->request->get('captcha_answer', ''));
        $expectedCaptcha = (string) $request->getSession()->get(self::SESSION_CAPTCHA_EXPECTED, '');

        $errors = [];

        if ($email === '') {
            $errors['email'] = 'Veuillez renseigner votre adresse e-mail.';
        }

        if ($password === null || trim((string) $password) === '') {
            $errors['password'] = 'Veuillez renseigner votre mot de passe.';
        }

        if ($captchaAnswer === '') {
            $errors['captcha'] = 'Veuillez confirmer que vous n\'êtes pas un robot.';
        } elseif ($expectedCaptcha === '' || !hash_equals($expectedCaptcha, mb_strtoupper($captchaAnswer))) {
            $errors['captcha'] = 'Vérification anti-robot invalide. Veuillez réessayer.';
        }

        if ($errors === []) {
            return;
        }

        $session = $request->getSession();
        $session->set(self::SESSION_LAST_USERNAME, $email);
        $session->set(self::SESSION_VALIDATION_ERRORS, $errors);

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_login', $request->query->all()),
            RedirectResponse::HTTP_FOUND
        ));
    }
}
