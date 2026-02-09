<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $targetPath = $request->query->get('_target_path');
            if ($targetPath !== null && $targetPath !== '') {
                return $this->redirect($targetPath);
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
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $targetPath = $request->query->get('_target_path');

        return $this->render('front/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'target_path' => $targetPath,
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé logout du pare-feu.');
    }

    #[Route('/confirmer-compte', name: 'app_confirm_pin', methods: ['GET', 'POST'])]
    public function confirmPinRedirect(): Response
    {
        return $this->redirectToRoute('app_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
