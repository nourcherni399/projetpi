<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Patient;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class ConnectGoogleController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/connect/google', name: 'app_connect_google_start', methods: ['GET'])]
    public function connectGoogleStart(): RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('home');
        }

        return $this->clientRegistry
            ->getClient('google')
            ->redirect([], []);
    }

    #[Route('/connect/google/check', name: 'app_connect_google_check', methods: ['GET'])]
    public function connectGoogleCheck(Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('home');
        }

        $code = $request->query->get('code');
        $state = $request->query->get('state');

        if (!$code) {
            $this->addFlash('error', 'Connexion Google annulée ou invalide.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $client = $this->clientRegistry->getClient('google');
            $redirectUri = $this->urlGenerator->generate('app_connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $options = [
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ];
            if ($state !== null && $state !== '') {
                $options['state'] = $state;
            }
            $token = $client->getOAuth2Provider()->getAccessToken('authorization_code', $options);
            $googleUser = $client->getOAuth2Provider()->getResourceOwner($token);
        } catch (IdentityProviderException|\Exception $e) {
            $this->addFlash('error', 'Impossible de récupérer les informations Google. Réessayez.');
            return $this->redirectToRoute('app_login');
        }

        $googleId = $googleUser->getId();
        $email = $googleUser->getEmail();
        $firstName = $googleUser->getFirstName() ?? '';
        $lastName = $googleUser->getLastName() ?? '';

        if ($email === null || $email === '') {
            $this->addFlash('error', 'Votre compte Google ne partage pas d\'adresse e-mail. Autorisez l\'accès à votre adresse e-mail.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);
        if ($user === null) {
            $user = $this->userRepository->findOneBy(['email' => $email]);
        }

        if ($user === null) {
            $user = new Patient();
            $user->setGoogleId($googleId);
            $user->setEmail($email);
            $user->setPrenom(mb_substr($firstName ?: 'Prénom', 0, 255));
            $user->setNom(mb_substr($lastName ?: 'Utilisateur', 0, 255));
            $user->setTelephone(0);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->setIsActive(true);
            $user->setRole(UserRole::PATIENT);
            $now = new \DateTimeImmutable();
            $user->setCreatedAt($now);
            $user->setUpdatedAt($now);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            if ($user->getGoogleId() === null) {
                $user->setGoogleId($googleId);
                $user->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
            }
        }

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));

        $targetPath = $this->getTargetPath($request->getSession(), 'main');
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        $role = $user->getRole();
        if ($role === UserRole::ADMIN) {
            return $this->redirectToRoute('admin_dashboard');
        }
        if ($role === UserRole::MEDECIN) {
            return $this->redirectToRoute('doctor_dashboard');
        }

        return $this->redirectToRoute('home');
    }
}
