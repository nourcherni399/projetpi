<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\EventSubscriber\LoginValidationSubscriber;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $role = $user->getRole();
            if ($role === UserRole::ADMIN) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($role === UserRole::MEDECIN) {
                return $this->redirectToRoute('doctor_dashboard');
            }
            return $this->redirectToRoute('home');
        }
<<<<<<< HEAD
=======

        $session = $request->getSession();
        $targetPath = $request->query->get('_target_path') ?? $request->request->get('_target_path');
        // N'accepter qu'un chemin relatif (ex. /rendez-vous/prendre/5?etape=2) pour éviter les redirections ouvertes
        if (\is_string($targetPath) && $targetPath !== '' && str_starts_with($targetPath, '/') && !str_starts_with($targetPath, '//')) {
            $session->set('_security.main.target_path', $targetPath);
        }

        $validationErrors = $session->remove(LoginValidationSubscriber::SESSION_VALIDATION_ERRORS);
        $lastUsernameFromValidation = $session->remove(LoginValidationSubscriber::SESSION_LAST_USERNAME);

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $lastUsernameFromValidation !== null && $lastUsernameFromValidation !== ''
            ? $lastUsernameFromValidation
            : $authenticationUtils->getLastUsername();
        $this->regenerateLoginCaptcha($request);

        return $this->render('front/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'errors' => $validationErrors ?? [],
            'target_path' => $targetPath,
            'captcha_image_url' => $this->generateUrl('app_login_captcha_image', ['_t' => time()]),
        ]);
    }

    #[Route('/connexion/faciale', name: 'app_login_face', methods: ['GET'])]
    public function faceLogin(Request $request): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('home');
        }
>>>>>>> 454cf3534cd44ab862139630471999260fa62858

        $targetPath = $request->query->get('_target_path') ?? $request->request->get('_target_path');
        if ($targetPath !== null && $targetPath !== '') {
            $request->getSession()->set('_security.main.target_path', $targetPath);
        }

<<<<<<< HEAD
        $session = $request->getSession();
        $validationErrors = $session->remove(LoginValidationSubscriber::SESSION_VALIDATION_ERRORS);
        $lastUsernameFromValidation = $session->remove(LoginValidationSubscriber::SESSION_LAST_USERNAME);

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $lastUsernameFromValidation !== null && $lastUsernameFromValidation !== ''
            ? $lastUsernameFromValidation
            : $authenticationUtils->getLastUsername();
        $this->regenerateLoginCaptcha($request);

        return $this->render('front/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'errors' => $validationErrors ?? [],
            'target_path' => $targetPath,
            'captcha_image_url' => $this->generateUrl('app_login_captcha_image', ['_t' => time()]),
        ]);
    }

    #[Route('/connexion/faciale', name: 'app_login_face', methods: ['GET'])]
    public function faceLogin(Request $request): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('home');
        }

        $targetPath = $request->query->get('_target_path') ?? $request->request->get('_target_path');
        if ($targetPath !== null && $targetPath !== '') {
            $request->getSession()->set('_security.main.target_path', $targetPath);
        }

        return $this->render('front/auth/face_login.html.twig', [
            'target_path' => $targetPath,
=======
        return $this->render('front/auth/face_login.html.twig', [
            'target_path' => $targetPath,
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        ]);
    }

    #[Route('/face-recognition', name: 'app_face_recognition', methods: ['POST'])]
    public function faceRecognition(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);

        if (!$this->isCsrfTokenValid('face_recognition', (string) ($payload['_csrf_token'] ?? ''))) {
            return $this->json(['ok' => false, 'message' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $descriptor = $payload['descriptor'] ?? null;
        $descriptors = $payload['descriptors'] ?? null;

        if ($email === '') {
            return $this->json(['ok' => false, 'message' => 'Données faciales invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $storedRaw = $user->getDataFaceApi();
        if ($storedRaw === null || trim($storedRaw) === '') {
            return $this->json(['ok' => false, 'message' => 'Aucune donnée faciale enregistrée pour ce compte.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $stored = json_decode($storedRaw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'message' => 'Donnée faciale stockée invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($stored) || count($stored) !== 128) {
            return $this->json(['ok' => false, 'message' => 'Donnée faciale stockée invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $stored = array_values(array_map('floatval', $stored));

        $descriptorCandidates = $this->extractDescriptorCandidates($descriptor, $descriptors);
        if ($descriptorCandidates === []) {
            return $this->json(['ok' => false, 'message' => 'Descripteur facial incomplet.'], Response::HTTP_BAD_REQUEST);
        }

        $distance = null;
        foreach ($descriptorCandidates as $candidate) {
            $current = $this->euclideanDistance($candidate, $stored);
            $distance = $distance === null ? $current : min($distance, $current);
        }

        $threshold = 0.52;

        if ($distance > $threshold) {
            return $this->json([
                'ok' => false,
                'message' => sprintf('Visage non reconnu (distance %.4f > %.2f).', $distance, $threshold),
                'distance' => $distance,
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isActive() === false) {
            return $this->json(['ok' => false, 'message' => 'Compte désactivé.'], Response::HTTP_FORBIDDEN);
        }

        $this->security->login($user, null, 'main');

        return $this->json([
            'ok' => true,
            'message' => 'Reconnaissance faciale validée. Connexion réussie.',
            'distance' => $distance,
            'redirectUrl' => $this->resolvePostLoginRedirect($user, $request),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé logout du pare-feu.');
    }

    #[Route('/confirmer-compte', name: 'app_confirm_pin', methods: ['GET'])]
    public function confirmPinRedirect(): Response
    {
        return $this->redirectToRoute('app_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        try {
            $decoded = json_decode((string) $request->getContent(), true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; ++$i) {
            $d = $a[$i] - $b[$i];
            $sum += $d * $d;
        }

        return sqrt($sum);
    }

    /**
     * @param mixed $singleDescriptor
     * @param mixed $batchDescriptors
     * @return list<array<int, float>>
     */
    private function extractDescriptorCandidates(mixed $singleDescriptor, mixed $batchDescriptors): array
    {
        $candidates = [];

        if (is_array($singleDescriptor)) {
            $normalized = array_values(array_map('floatval', $singleDescriptor));
            if (count($normalized) === 128) {
                $candidates[] = $normalized;
            }
        }

        if (is_array($batchDescriptors)) {
            foreach ($batchDescriptors as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $normalized = array_values(array_map('floatval', $item));
                if (count($normalized) === 128) {
                    $candidates[] = $normalized;
                }
            }
        }

        return $candidates;
    }

    private function resolvePostLoginRedirect(User $user, Request $request): string
    {
        $targetPath = (string) $request->getSession()->get('_security.main.target_path', '');
        if ($targetPath !== '') {
            $request->getSession()->remove('_security.main.target_path');
            return $targetPath;
        }

        $role = $user->getRole();
        if ($role === UserRole::ADMIN) {
            return $this->generateUrl('admin_dashboard');
        }
        if ($role === UserRole::MEDECIN) {
            return $this->generateUrl('doctor_dashboard');
        }

        return $this->generateUrl('home');
    }

    #[Route('/connexion/captcha-image', name: 'app_login_captcha_image', methods: ['GET'])]
    public function loginCaptchaImage(Request $request): Response
    {
        $session = $request->getSession();
        $code = (string) $session->get(LoginValidationSubscriber::SESSION_CAPTCHA_EXPECTED, '');
        if ($code === '') {
            $this->regenerateLoginCaptcha($request);
            $code = (string) $session->get(LoginValidationSubscriber::SESSION_CAPTCHA_EXPECTED, '');
        }

        $svg = $this->buildCaptchaSvg($code);
        $response = new Response($svg);
        $response->headers->set('Content-Type', 'image/svg+xml; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function regenerateLoginCaptcha(Request $request): void
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        $request->getSession()->set(LoginValidationSubscriber::SESSION_CAPTCHA_EXPECTED, $code);
    }

    private function buildCaptchaSvg(string $code): string
    {
        $width = 170;
        $height = 52;
        $bg = '#F3F4F6';
        $stroke = '#D1D5DB';
        $text = '#111827';

        $lines = '';
        for ($i = 0; $i < 7; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $lines .= sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1" opacity="0.35"/>', $x1, $y1, $x2, $y2, $stroke);
        }

        $chars = '';
        $x = 18;
        foreach (str_split($code) as $char) {
            $y = random_int(33, 40);
            $rotate = random_int(-15, 15);
            $chars .= sprintf(
                '<text x="%d" y="%d" fill="%s" font-family="Arial, sans-serif" font-size="26" font-weight="700" transform="rotate(%d %d %d)">%s</text>',
                $x,
                $y,
                $text,
                $rotate,
                $x,
                $y,
                htmlspecialchars($char, ENT_QUOTES, 'UTF-8')
            );
            $x += 28;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect x="0.5" y="0.5" width="%d" height="%d" rx="10" fill="%s" stroke="%s"/>%s%s</svg>',
            $width,
            $height,
            $width,
            $height,
            $width - 1,
            $height - 1,
            $bg,
            $stroke,
            $lines,
            $chars
        );
    }
}