<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Produit;
use App\Entity\Stock;
use App\Entity\User;
use App\Enum\Categorie;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Crée automatiquement un produit à partir d'une proposition (chatbot + API)
 * et télécharge l'image depuis les sites de vente (Amazon, etc.).
 */
final class ProductAutoCreateService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SluggerInterface $slugger,
        private readonly ProductExternalApiService $externalApiService,
        private readonly string $uploadsDirectory,
    ) {
    }

    /**
     * @param array{nom: string, description: ?string, categorie: string, prix_estime: float, donnees_externes: ?array, image_from_search: ?string, price_source: ?string} $proposition
     */
    public function createFromProposition(array $proposition, Stock $stock, ?User $user = null): Produit
    {
        $produit = new Produit();
        $nom = $proposition['nom'] ?? 'Produit';
        $produit->setNom($nom);
        $produit->setDescription($proposition['description'] ?? null);
        $produit->setCategorie(Categorie::from($proposition['categorie'] ?? 'vie_quotidienne'));
        $prix = (float) ($proposition['prix_estime'] ?? 50);
        $produit->setPrix($prix <= 0 ? 50.0 : $prix);
        $produit->setDisponibilite(true);
        $produit->setStock($stock);
        $produit->setQuantite(1);
        $produit->setUser($user);
        $produit->setGenereParIa(true);
        $produit->setValide(false);

        // PRIORITÉ 1 : Image de la recherche de prix (image réelle du produit)
        $imageFromSearch = $proposition['image_from_search'] ?? null;
        if ($imageFromSearch && is_string($imageFromSearch) && file_exists('public/' . $imageFromSearch)) {
            $produit->setImage($imageFromSearch);
            return $produit;
        }

        // PRIORITÉ 2 : Image depuis les données externes (recherche de prix)
        $donneesExt = $proposition['donnees_externes'] ?? null;
        if (is_array($donneesExt) && !empty($donneesExt['image_path'])) {
            $produit->setImage($donneesExt['image_path']);
            return $produit;
        }

        // PRIORITÉ 3 : Télécharger depuis l'URL des données externes
        $imageUrl = is_array($donneesExt) ? ($donneesExt['image_url'] ?? null) : null;
        if (!$imageUrl && is_array($donneesExt) && !empty($donneesExt['results'])) {
            foreach ($donneesExt['results'] as $result) {
                if (!empty($result['image_url'])) {
                    $imageUrl = $result['image_url'];
                    break;
                }
            }
        }
        
        if ($imageUrl && is_string($imageUrl)) {
            $savedPath = $this->downloadImage($imageUrl, $nom);
            if ($savedPath) {
                $produit->setImage($savedPath);
                return $produit;
            }
        }

        // PRIORITÉ 4 : Fallback sur Pexels (si pas d'image trouvée)
        $searchQuery = $this->buildImageSearchQuery($proposition);
        $generatedImagePath = $this->externalApiService->generateImageAI($searchQuery);
        
        if ($generatedImagePath) {
            $produit->setImage($generatedImagePath);
        }

        return $produit;
    }

    private function buildImageSearchQuery(array $proposition): string
    {
        $imageKeywords = trim($proposition['image_keywords'] ?? '');
        if ($imageKeywords !== '') {
            return $imageKeywords;
        }
        
        $nom = trim($proposition['nom'] ?? '');
        $desc = trim($proposition['description'] ?? '');
        
        if ($nom === '') {
            return $desc ? mb_substr($desc, 0, 80) : 'product';
        }
        
        return $nom;
    }

    private function downloadImage(string $url, string $basename): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 60,
            ]);
            $content = $response->getContent();
            
            $ext = 'jpg';
            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            if (str_contains($contentType, 'png')) {
                $ext = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $ext = 'webp';
            } elseif (str_contains($contentType, 'gif')) {
                $ext = 'gif';
            } elseif (preg_match('/\.(jpe?g|png|webp|gif)/i', $url, $m)) {
                $ext = strtolower($m[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }
            
            $safe = $this->slugger->slug($basename);
            $filename = $safe->toString() . '-' . uniqid() . '.' . $ext;
            if (!is_dir($this->uploadsDirectory)) {
                mkdir($this->uploadsDirectory, 0755, true);
            }
            $path = $this->uploadsDirectory . '/' . $filename;
            file_put_contents($path, $content);
            return 'uploads/produits/' . $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }
}