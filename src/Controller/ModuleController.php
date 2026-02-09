<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Module;
use App\Form\ModuleType;
use App\Repository\ModuleRepository;
use App\Repository\ActionHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/admin/modules')]
final class ModuleController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActionHistoryRepository $actionHistoryRepository,
    ) {
    }

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $modules = $this->moduleRepository->findBy([], ['dateCreation' => 'DESC']);
        $actionHistory = $this->actionHistoryRepository->findLatestActions(5);
        
        return $this->render('admin/module/index.html.twig', [
            'modules' => $modules,
            'actionHistory' => $actionHistory
        ]);
    }

    #[Route('/new', name: 'admin_module_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $module = new Module();
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTime();
            $module->setDateCreation($now);
            $module->setDateModif($now);
            if ($module->getImage() === null) {
                $module->setImage('');
            }
            $this->entityManager->persist($module);
            $this->entityManager->flush();
            
            // Enregistrer l'action de création
            $this->actionHistoryRepository->createAction(
                'Admin',
                'Création',
                $module->getTitre(),
                'Module créé avec succès'
            );
            
            $this->addFlash('success', 'Le module a été créé avec succès.');

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/module/new.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_module_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Module $module): Response
    {
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $module->setDateModif(new \DateTime());
            if ($module->getImage() === null) {
                $module->setImage('');
            }
            $this->entityManager->flush();
            
            // Enregistrer l'action de modification
            $this->actionHistoryRepository->createAction(
                'Admin',
                'Modification',
                $module->getTitre(),
                'Titre et description modifiés'
            );
            
            $this->addFlash('success', 'Le module a été modifié avec succès.');

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/module/edit.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_module_delete', methods: ['POST'])]
    public function delete(Request $request, Module $module): RedirectResponse
    {
        if ($this->isCsrfTokenValid('delete' . $module->getId(), $request->request->get('_token'))) {
            $blogsCount = $module->getBlogs()->count();
            $moduleTitre = $module->getTitre(); // Sauvegarder le titre avant suppression
            
            $this->entityManager->remove($module);
            $this->entityManager->flush();
            
            // Enregistrer l'action de suppression
            $details = 'Module supprimé';
            if ($blogsCount > 0) {
                $details .= ' (' . $blogsCount . ' article' . ($blogsCount > 1 ? 's' : '') . ' supprimé' . ($blogsCount > 1 ? 's' : '') . ')';
            }
            
            $this->actionHistoryRepository->createAction(
                'Admin',
                'Suppression',
                $moduleTitre,
                $details
            );
            
            $message = 'Le module a été supprimé avec succès.';
            if ($blogsCount > 0) {
                $message .= ' ' . $blogsCount . ' article' . ($blogsCount > 1 ? 's' : '') . ' associé' . ($blogsCount > 1 ? 's' : '') . ' ont également été supprimé' . ($blogsCount > 1 ? 's' : '') . '.';
            }
            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/export-pdf-action', name: 'admin_module_export_pdf_action', methods: ['POST'])]
    public function exportPdfAction(): Response
    {
        // Enregistrer l'action d'export PDF
        $this->actionHistoryRepository->createAction(
            'Admin',
            'Export PDF',
            null,
            'Export de la liste des modules'
        );

        return $this->json(['success' => true]);
    }
<<<<<<< Updated upstream
}
=======
}

>>>>>>> Stashed changes
