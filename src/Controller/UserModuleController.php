<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Module;
use App\Form\ModuleType;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/modules')]
final class UserModuleController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: 'user_modules_index', methods: ['GET'])]
    #[Route('', name: 'user_modules', methods: ['GET'])]  // Alias pour compatibilité
    public function index(): Response
    {
        $modules = $this->moduleRepository->findBy(['isPublished' => true], ['dateCreation' => 'DESC']);
        return $this->render('front/modules/index.html.twig', ['modules' => $modules]);
    }

    #[Route('/{id}', name: 'user_module_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        return $this->render('front/modules/show.html.twig', ['module' => $module]);
    }

    #[Route('/{id}/edit', name: 'user_module_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Module $module): Response
    {
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $saved = $this->handleImageUpload($imageFile, $module);
                if (!$saved) {
                    return $this->render('front/modules/edit.html.twig', [
                        'module' => $module,
                        'form' => $form,
                    ]);
                }
            }
            $module->setDateModif(new \DateTime());
            if ($module->getImage() === null) {
                $module->setImage('');
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Le module a été modifié avec succès.');

            return $this->redirectToRoute('user_module_show', ['id' => $module->getId()]);
        }

        return $this->render('front/modules/edit.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'user_module_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Module $module): Response
    {
        if ($this->isCsrfTokenValid('delete' . $module->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($module);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le module a été supprimé avec succès.');
        }

        return $this->redirectToRoute('user_modules_index');
    }

    private function handleImageUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $imageFile, Module $module): bool
    {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $dir = $this->getParameter('uploads_modules_directory');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $imageFile->move($dir, $newFilename);
            $module->setImage('uploads/modules/' . $newFilename);
            return true;
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
            return false;
        }
    }
}
