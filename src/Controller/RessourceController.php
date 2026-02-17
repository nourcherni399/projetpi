<?php

namespace App\Controller;

use App\Entity\Module;
use App\Entity\Ressource;
use App\Form\RessourceType;
use App\Repository\ModuleRepository;
use App\Repository\RessourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/modules/ressources')]
final class RessourceController extends AbstractController
{
    public function __construct(
        private readonly RessourceRepository $ressourceRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: 'admin_ressource_index', methods: ['GET'])]
    public function index(Request $request): RedirectResponse
    {
        $moduleId = $request->query->getInt('module', 0);
        return $this->redirectToRoute('admin_dashboard', [
            'resourceModule' => $moduleId > 0 ? $moduleId : null,
        ]);
    }

    #[Route('/new', name: 'admin_ressource_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $ressource = new Ressource();
        $moduleId = $request->query->getInt('module', 0);
        if ($moduleId > 0) {
            $defaultModule = $this->moduleRepository->find($moduleId);
            if ($defaultModule instanceof Module) {
                $ressource->setModule($defaultModule);
            }
        }

        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mediaFile = $form->get('mediaFile')->getData();
            $this->validateTypeAndContent($form, $ressource, $mediaFile);

            if ($form->isValid()) {
                if ($mediaFile !== null) {
                    $saved = $this->handleMediaUpload($mediaFile, $ressource);
                    if (!$saved) {
                        return $this->render('admin/ressource/new.html.twig', [
                            'form' => $form,
                            'ressource' => $ressource,
                        ]);
                    }
                }

                $now = new \DateTimeImmutable();
                $ressource->setDateCreation($now);
                $ressource->setDateModif($now);
                $this->entityManager->persist($ressource);
                $this->entityManager->flush();

                $this->addFlash('success', 'La ressource a ete creee avec succes.');
                return $this->redirectToRoute('admin_dashboard', [
                    'resourceModule' => $ressource->getModule()?->getId(),
                ]);
            }
        }

        return $this->render('admin/ressource/new.html.twig', [
            'form' => $form,
            'ressource' => $ressource,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_ressource_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Ressource $ressource): Response
    {
        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mediaFile = $form->get('mediaFile')->getData();
            $this->validateTypeAndContent($form, $ressource, $mediaFile);

            if ($form->isValid()) {
                if ($mediaFile !== null) {
                    $saved = $this->handleMediaUpload($mediaFile, $ressource);
                    if (!$saved) {
                        return $this->render('admin/ressource/edit.html.twig', [
                            'form' => $form,
                            'ressource' => $ressource,
                        ]);
                    }
                }

                $ressource->setDateModif(new \DateTimeImmutable());
                $this->entityManager->flush();

                $this->addFlash('success', 'La ressource a ete modifiee avec succes.');
                return $this->redirectToRoute('admin_dashboard', [
                    'resourceModule' => $ressource->getModule()?->getId(),
                ]);
            }
        }

        return $this->render('admin/ressource/edit.html.twig', [
            'form' => $form,
            'ressource' => $ressource,
        ]);
    }

    #[Route('/{id}', name: 'admin_ressource_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Ressource $ressource): RedirectResponse
    {
        $moduleId = $ressource->getModule()?->getId();
        if ($this->isCsrfTokenValid('delete_ressource' . $ressource->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($ressource);
            $this->entityManager->flush();
            $this->addFlash('success', 'La ressource a ete supprimee avec succes.');
        }

        return $this->redirectToRoute('admin_dashboard', [
            'resourceModule' => $moduleId,
        ]);
    }

    private function validateTypeAndContent(\Symfony\Component\Form\FormInterface $form, Ressource $ressource, mixed $mediaFile): void
    {
        $type = $ressource->getTypeRessource();
        $contenu = trim((string) $ressource->getContenu());

        if ($type === 'url') {
            if (!preg_match('#^https?://#i', $contenu)) {
                $form->get('contenu')->addError(new FormError('Pour le type URL, le contenu doit etre une URL http(s) valide.'));
            }
            if ($mediaFile !== null) {
                $form->get('mediaFile')->addError(new FormError("Le type URL n'accepte pas d'upload de fichier."));
            }
            return;
        }

        if ($type === 'video') {
            $isHttpUrl = (bool) preg_match('#^https?://#i', $contenu);
            $isLocalUploadedFile = str_starts_with($contenu, 'uploads/ressources/');
            if ($mediaFile === null && !$isHttpUrl && !$isLocalUploadedFile) {
                $form->get('contenu')->addError(new FormError('Pour le type video, fournissez une URL video ou uploadez un fichier video.'));
            }
            return;
        }

        if ($type === 'audio') {
            $isHttpUrl = (bool) preg_match('#^https?://#i', $contenu);
            $isLocalUploadedFile = str_starts_with($contenu, 'uploads/ressources/');
            if ($mediaFile === null && !$isHttpUrl && !$isLocalUploadedFile) {
                $form->get('contenu')->addError(new FormError('Pour le type audio, fournissez une URL audio ou uploadez un fichier audio.'));
            }
        }
    }

    private function handleMediaUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $mediaFile, Ressource $ressource): bool
    {
        $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $mediaFile->guessExtension() ?: $mediaFile->getClientOriginalExtension();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        try {
            $dir = $this->getParameter('uploads_ressources_directory');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $mediaFile->move($dir, $newFilename);
            $ressource->setContenu('uploads/ressources/' . $newFilename);
            return true;
        } catch (\Throwable) {
            $this->addFlash('error', "Erreur lors de l'upload du media.");
            return false;
        }
    }
}
