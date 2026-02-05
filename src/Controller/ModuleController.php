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
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/admin/modules')]
final class ModuleController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_module_index', methods: ['GET'])]
    public function index(): Response
    {
        $modules = $this->moduleRepository->findBy([], ['dateCreation' => 'DESC']);
        return $this->render('admin/module/index.html.twig', ['modules' => $modules]);
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
            $this->addFlash('success', 'Le module a été créé avec succès.');

            return $this->redirectToRoute('admin_module_index');
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
            $this->addFlash('success', 'Le module a été modifié avec succès.');

            return $this->redirectToRoute('admin_module_index');
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
            $this->entityManager->remove($module);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le module a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_module_index');
    }
}
