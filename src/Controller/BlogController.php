<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BlogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/blog')]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
    ) {
    }

    #[Route('', name: 'admin_blog_index', methods: ['GET'])]
    public function index(): Response
    {
        $blogs = $this->blogRepository->findBy([], ['dateCreation' => 'DESC']);
        return $this->render('admin/blog/index.html.twig', ['blogs' => $blogs]);
    }
}
