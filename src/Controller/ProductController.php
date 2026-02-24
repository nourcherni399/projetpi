<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\FuzzyProductSearchTrait;
use App\Entity\DemandeProduit;
use App\Entity\Notification;
use App\Entity\Produit;
use App\Enum\Categorie;
use App\Repository\DemandeProduitRepository;
use App\Repository\ProduitRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;
use App\Service\ChatApiService;
use App\Service\ImageAnalysisService;
use App\Service\ProductAutoCreateService;
use App\Service\ProductPriceSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/produits')]
final class ProductController extends AbstractController
{
    use FuzzyProductSearchTrait;
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly DemandeProduitRepository $demandeProduitRepository,
        private readonly UserRepository $userRepository,
        private readonly ChatApiService $chatApiService,
        private readonly ProductAutoCreateService $productAutoCreateService,
        private readonly StockRepository $stockRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductPriceSearchService $priceSearchService,
        private readonly ImageAnalysisService $imageAnalysisService,
        private readonly SluggerInterface $slugger,
        private readonly string $uploadsDirectory,
    ) {
    }

    #[Route('', name: 'user_products_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderProductsList($request);
    }

    #[Route('/creer', name: 'user_products_create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('front/products/create.html.twig');
    }

    private function renderProductsList(Request $request): Response
    {
        $categorie = $request->query->get('categorie');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sortBy', 'nom'); // 'nom' ou 'prix'
        $sortOrder = $request->query->get('sortOrder', 'asc'); // 'asc' ou 'desc'
        
        $criteria = [];
        
        if ($categorie) {
            try {
                $categorieEnum = Categorie::from($categorie);
                $criteria['categorie'] = $categorieEnum;
            } catch (\ValueError $e) {
                // Invalid category, ignore
            }
        }
        
        // Définir l'ordre de tri
        $orderBy = [];
        if ($sortBy === 'prix') {
            $orderBy['prix'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        } elseif ($sortBy === 'note') {
            $orderBy['noteMoyenne'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
            $orderBy['nbAvis'] = 'DESC';
        } else {
            $orderBy['nom'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        }
        
        $produits = $this->produitRepository->findBy($criteria, $orderBy);

        $minPriceVal = $minPrice !== null && $minPrice !== '' ? (int) $minPrice : null;
        $maxPriceVal = $maxPrice !== null && $maxPrice !== '' ? (int) $maxPrice : null;
        $searchTerm = $search !== null && $search !== '' ? strtolower(trim((string) $search)) : '';

        if ($minPriceVal !== null || $maxPriceVal !== null || $searchTerm !== '') {
            $minFilter = $minPriceVal ?? 0;
            $maxFilter = $maxPriceVal ?? PHP_INT_MAX;
            $searchTermTrimmed = trim((string) $search);

            $produits = array_filter($produits, function ($produit) use ($minFilter, $maxFilter, $searchTerm, $searchTermTrimmed) {
                $priceMatch = $produit->getPrix() !== null && (float) $produit->getPrix() >= $minFilter && (float) $produit->getPrix() <= $maxFilter;

                if ($searchTermTrimmed === '') {
                    return $priceMatch;
                }

                return $priceMatch && $this->fuzzySearchMatch($searchTermTrimmed, $produit);
            });
        }

        return $this->render('front/products/index.html.twig', [
            'produits' => $produits,
            'categories' => Categorie::cases(),
            'selectedCategorie' => $categorie,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'cart_add' => true,
        ]);
    }

    private const CHAT_QUESTIONS = [
        1 => "Quel type de produit cherchez-vous ? (ex: coussin sensoriel, casque anti-bruit, jeu éducatif...)",
        2 => "Décrivez votre besoin ou l'usage prévu (ex: pour calmer mon enfant, pour la concentration...)",
        3 => "Avez-vous un budget en dinars tunisiens ? (répondez par le montant, ex: 80, ou 'non' si pas de budget)",
    ];

    /** Questions guidées alignées sur le formulaire d'ajout de produit. */
    private const CHAT_FORM_QUESTIONS = [
        1 => "Quel est le nom du produit ? (ex: Coussin sensoriel lesté)",
        2 => "Décrivez le produit : à quoi sert-il, pour qui ?",
        3 => "Quelle catégorie ? Décrivez en quelques mots (ex: relaxation, jeux, sensoriel, calme, éducation, communication...)",
        4 => "Quel est le prix en dinars tunisiens ? (ex: 50 ou 80.50)",
    ];

    private const CHAT_FORM_CATEGORIES = [
        '1' => 'sensoriels',
        '2' => 'bruit_et_environnement',
        '3' => 'education_apprentissage',
        '4' => 'communication_langage',
        '5' => 'jeux_therapeutiques_developpement',
        '6' => 'bien_etre_relaxation',
        '7' => 'vie_quotidienne',
    ];

    /**
     * Chat unifié : répond à TOUTES les questions via l'API + crée automatiquement un produit si données complètes.
     * POST: message, history (optionnel), price_search (optionnel - contexte de recherche de prix)
     */
    #[Route('/chat-api', name: 'user_products_chat_api', methods: ['POST'])]
    public function chatApi(Request $request): JsonResponse
    {
        $body = $request->request->all();
        if (empty($body) && $request->getContent() !== '') {
            $body = json_decode($request->getContent(), true) ?? [];
        }
        $message = trim((string) ($body['message'] ?? ''));
        $historyRaw = $body['history'] ?? [];
        $history = is_string($historyRaw) ? (json_decode($historyRaw, true) ?? []) : (is_array($historyRaw) ? $historyRaw : []);
        
        // Récupérer le contexte de recherche de prix existant
        $priceSearchRaw = $body['price_search'] ?? null;
        $priceSearch = is_string($priceSearchRaw) ? (json_decode($priceSearchRaw, true) ?? null) : $priceSearchRaw;

        if ($message === '') {
            return new JsonResponse([
                'reply' => 'Bonjour ! Je suis l\'assistant AutiCare. Posez-moi une question ou demandez-moi de créer un produit.',
                'product_data' => [],
                'ready' => false,
                'products' => [],
                'price_search' => null,
            ]);
        }

        // Détecter si c'est une description (après demande de description)
        $shouldSearchPrice = $this->shouldSearchForPrice($message, $history, $priceSearch);
        
        if ($shouldSearchPrice) {
            // Récupérer le nom du produit depuis l'historique
            $productName = $this->extractProductNameFromHistory($history);
            $description = $message;
            
            // Construire la requête de recherche : nom + description
            $searchQuery = $productName ? ($productName . ' ' . $description) : $description;
            
            // Rechercher le prix sur les sites e-commerce
            $priceSearch = $this->priceSearchService->searchProduct($searchQuery);
            
            if ($priceSearch['found']) {
                // Ajouter un message système sur les prix trouvés
                $priceMessage = $this->formatPriceSearchMessage($priceSearch);
                $result = $this->chatApiService->sendMessage($message, $history, $priceSearch);
                
                // Ajouter l'info de prix dans la réponse
                if ($result['reply']) {
                    $result['reply'] = $priceMessage . "\n\n" . $result['reply'];
                }
            } else {
                $result = $this->chatApiService->sendMessage($message, $history);
            }
        } else {
            $result = $this->chatApiService->sendMessage($message, $history, $priceSearch);
        }
        
        if ($result['reply'] === null) {
            return new JsonResponse([
                'reply' => "Salut ! Comment puis-je t'aider aujourd'hui ?",
                'product_data' => [],
                'ready' => false,
                'products' => [],
                'price_search' => null,
            ]);
        }

        $response = [
            'reply' => $result['reply'],
            'product_data' => $result['product_data'],
            'ready' => $result['ready'],
            'products' => [],
            'price_search' => $priceSearch,
        ];

        if ($result['ready'] && !empty($result['product_data'])) {
            $user = $this->getUser();
            
            // TOUJOURS rechercher le produit sur les sites e-commerce pour avoir une vraie image
            $productName = $result['product_data']['nom'] ?? 'Produit';
            $productSearch = $this->priceSearchService->searchProduct($productName);
            
            // Utiliser l'image de la recherche si trouvée
            $imageFromSearch = null;
            if ($productSearch['found'] && !empty($productSearch['image_path'])) {
                $imageFromSearch = $productSearch['image_path'];
            }
            
            $proposition = [
                'nom' => $productName,
                'description' => $result['product_data']['description'] ?? null,
                'categorie' => $result['product_data']['categorie'] ?? 'vie_quotidienne',
                'prix_estime' => (float) ($result['product_data']['prix'] ?? 50),
                'image_keywords' => $result['product_data']['image_keywords'] ?? null,
                'image_from_search' => $imageFromSearch,
                'price_source' => $result['product_data']['price_source'] ?? null,
                'donnees_externes' => $productSearch['found'] ? $productSearch : $priceSearch,
            ];

            $demande = $this->createProduitAuto($proposition, $user);
            if ($demande) {
                $card = $this->buildDemandeCard($demande);
                $response['products'] = [$card];
                
                $sourceInfo = '';
                if (!empty($result['product_data']['price_source'])) {
                    $sourceInfo = " (prix trouvé sur {$result['product_data']['price_source']})";
                }
                
                $response['reply'] .= "\n\n✅ Votre demande pour « " . $proposition['nom'] . " » a été envoyée !{$sourceInfo}\n⏳ Un administrateur validera votre produit sous peu.";
                $response['product_data'] = [];
                $response['ready'] = false;
                $response['price_search'] = null;
            } else {
                $response['reply'] .= "\n\n⚠️ Désolé, une erreur est survenue. Réessayez plus tard.";
            }
        }

        return new JsonResponse($response);
    }

    /**
     * Analyse une image uploadée, extrait les infos produit, cherche le prix et crée le produit
     */
    #[Route('/analyze-image', name: 'user_products_analyze_image', methods: ['POST'])]
    public function analyzeImage(Request $request): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('image');
        
        if (!$file || !$file->isValid()) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Veuillez envoyer une image valide.',
            ], 400);
        }

        // Vérifier le type MIME
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Format d\'image non supporté. Utilisez JPG, PNG, GIF ou WebP.',
            ], 400);
        }

        // Sauvegarder l'image temporairement
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
        
        $uploadPath = $this->uploadsDirectory . '/produits';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $file->move($uploadPath, $newFilename);
        $imagePath = $uploadPath . '/' . $newFilename;
        $imageRelativePath = 'uploads/produits/' . $newFilename;

        // Analyser l'image avec l'IA
        $analysis = $this->imageAnalysisService->analyzeProductImage($imagePath);
        
        if (!$analysis['success']) {
            return new JsonResponse([
                'success' => false,
                'error' => $analysis['error'] ?? 'Impossible d\'analyser l\'image.',
                'image_path' => $imageRelativePath,
            ], 400);
        }

        // Rechercher le prix sur les sites e-commerce
        $searchQuery = ($analysis['nom'] ?? '') . ' ' . ($analysis['description'] ?? '');
        $priceSearch = $this->priceSearchService->searchProduct($searchQuery);

        // Déterminer le prix
        $prix = 50.0; // Prix par défaut
        $priceSource = null;
        if ($priceSearch['found'] && !empty($priceSearch['suggested_price_tnd'])) {
            $prix = $priceSearch['suggested_price_tnd'];
            $priceSource = $priceSearch['best_price']['source'] ?? 'en ligne';
        }

        // Créer le produit automatiquement
        $user = $this->getUser();
        $proposition = [
            'nom' => $analysis['nom'],
            'description' => $analysis['description'],
            'categorie' => $analysis['categorie'] ?? 'vie_quotidienne',
            'prix_estime' => $prix,
            'image_keywords' => $analysis['image_keywords'],
            'image_from_search' => $imageRelativePath, // Utiliser l'image uploadée par le client
            'price_source' => $priceSource,
            'donnees_externes' => $priceSearch['found'] ? $priceSearch : null,
        ];

        $demande = $this->createProduitAuto($proposition, $user);
        
        if (!$demande) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Impossible de créer la demande.',
                'analysis' => $analysis,
                'price_search' => $priceSearch,
            ], 400);
        }

        $card = $this->buildDemandeCard($demande);
        
        $message = "✅ **Demande envoyée !**\n\n";
        $message .= "📦 **Nom:** {$analysis['nom']}\n";
        $message .= "📝 **Description:** {$analysis['description']}\n";
        $message .= "💰 **Prix estimé:** " . number_format($prix, 2) . " DT";
        $message .= "\n\n⏳ Un administrateur validera votre produit sous peu.";
        
        if ($priceSource) {
            $message .= " (trouvé sur {$priceSource})";
        }
        
        if ($priceSearch['found'] && !empty($priceSearch['results'])) {
            $message .= "\n\n🔍 **Prix trouvés en ligne:**\n";
            $shown = 0;
            foreach ($priceSearch['results'] as $result) {
                if ($shown >= 3) break;
                $source = $result['source'];
                $price = number_format($result['price_tnd'], 2);
                $message .= "• {$source}: {$price} DT\n";
                $shown++;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'analysis' => $analysis,
            'price_search' => $priceSearch,
            'product' => $card,
            'prix' => $prix,
            'price_source' => $priceSource,
        ]);
    }

    /**
     * Détermine si on doit rechercher le prix du produit (après la description)
     */
    private function shouldSearchForPrice(string $message, array $history, ?array $existingPriceSearch): bool
    {
        // Ne pas rechercher si on a déjà un contexte de prix
        if ($existingPriceSearch !== null && isset($existingPriceSearch['found']) && $existingPriceSearch['found']) {
            return false;
        }

        // Si pas d'historique, pas de recherche
        if (empty($history)) {
            return false;
        }

        $lastAssistantMessage = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'assistant') {
                $lastAssistantMessage = mb_strtolower($history[$i]['content'] ?? '');
                break;
            }
        }

        // Patterns qui indiquent qu'on attend une DESCRIPTION (pas un nom)
        $askingForDescription = (
            str_contains($lastAssistantMessage, 'description') ||
            str_contains($lastAssistantMessage, 'décri') ||
            str_contains($lastAssistantMessage, 'à quoi') ||
            str_contains($lastAssistantMessage, 'sert') ||
            str_contains($lastAssistantMessage, 'usage') ||
            str_contains($lastAssistantMessage, 'utilité') ||
            str_contains($lastAssistantMessage, 'pour quoi')
        );

        // Le message ressemble à une description (plus long qu'un simple nom)
        $msg = mb_strtolower(trim($message));
        $looksLikeDescription = (
            mb_strlen($message) >= 5 &&
            mb_strlen($message) <= 500 &&
            !str_contains($message, '?') &&
            !preg_match('/^(oui|non|ok|salut|bonjour|bonsoir|merci|slt|cc|cava|cv|nn|ui|ouais|yes|ya|super|parfait|d\'accord|daccord|\d+)/i', $msg)
        );

        return $askingForDescription && $looksLikeDescription;
    }

    /**
     * Extrait le nom du produit depuis l'historique
     */
    private function extractProductNameFromHistory(array $history): ?string
    {
        // Chercher le nom du produit dans les réponses précédentes
        // Le nom est généralement donné juste après que le bot ait demandé le nom
        for ($i = 0; $i < count($history) - 1; $i++) {
            $current = $history[$i] ?? [];
            $next = $history[$i + 1] ?? [];
            
            if (($current['role'] ?? '') === 'assistant' && ($next['role'] ?? '') === 'user') {
                $assistantMsg = mb_strtolower($current['content'] ?? '');
                if (str_contains($assistantMsg, 'nom') || str_contains($assistantMsg, 'quel produit')) {
                    return trim($next['content'] ?? '');
                }
            }
        }
        
        return null;
    }

    /**
     * Formate le message de résultat de recherche de prix
     */
    private function formatPriceSearchMessage(array $priceSearch): string
    {
        if (!$priceSearch['found'] || empty($priceSearch['results'])) {
            return "";
        }

        $message = "🔍 **J'ai recherché ce produit en ligne !**\n\n";
        
        $shown = 0;
        foreach ($priceSearch['results'] as $result) {
            if ($shown >= 3) break;
            $source = $result['source'];
            $price = number_format($result['price_tnd'], 2);
            $message .= "• **{$source}** : {$price} DT\n";
            $shown++;
        }

        $suggested = number_format($priceSearch['suggested_price_tnd'], 2);
        $bestSource = $priceSearch['best_price']['source'] ?? 'en ligne';
        $message .= "\n💡 **Prix suggéré : {$suggested} DT** (meilleur prix trouvé sur {$bestSource})";

        return $message;
    }

    #[Route('/suggest-chat', name: 'user_products_suggest_chat', methods: ['POST'])]
    public function suggestChat(Request $request): JsonResponse
    {
        $body = $request->request->all();
        if (empty($body) && $request->getContent() !== '') {
            $body = json_decode($request->getContent(), true) ?? [];
        }
        $message = trim((string) ($body['message'] ?? ''));
        $chatStep = (int) ($body['chat_step'] ?? 0);
        $chatDataRaw = $body['chat_data'] ?? null;
        $chatData = is_string($chatDataRaw) ? (json_decode($chatDataRaw, true) ?? []) : (is_array($chatDataRaw) ? $chatDataRaw : []);
        $historyRaw = $body['history'] ?? null;
        $history = is_string($historyRaw) ? (json_decode($historyRaw, true) ?? []) : (is_array($historyRaw) ? $historyRaw : []);

        $user = $this->getUser();

        if ($message === '') {
            $reply = $chatStep >= 1 && $chatStep <= 4
                ? self::CHAT_FORM_QUESTIONS[$chatStep]
                : 'Bonjour ! Je vais vous guider pour ajouter un produit. ' . self::CHAT_FORM_QUESTIONS[1];
            return new JsonResponse([
                'reply' => $reply,
                'product_data' => $chatData,
                'ready' => false,
                'products' => [],
                'chat_step' => $chatStep ?: 1,
                'chat_data' => $chatData,
            ]);
        }

        return $this->handleChatFormFlow($message, $chatStep, $chatData, $history, $user);
    }

    private function handleChatFormFlow(string $message, int $chatStep, array $chatData, array $history, mixed $user): JsonResponse
    {
        $result = $this->productAiService->handleGuidedChatStep($message, $chatStep, $chatData, $history);
        $chatData = $result['chat_data'];
        $nextStep = (int) $result['chat_step'];
        $reply = $result['reply'];

        if ($nextStep <= 4) {
            return new JsonResponse([
                'reply' => $reply,
                'product_data' => $chatData,
                'ready' => false,
                'products' => [],
                'chat_step' => $nextStep,
                'chat_data' => $chatData,
            ]);
        }

        $proposition = [
            'nom' => $chatData['nom'] ?? 'Produit',
            'description' => $chatData['description'] ?? null,
            'categorie' => $chatData['categorie'] ?? 'vie_quotidienne',
            'prix_estime' => (float) ($chatData['prix_estime'] ?? 50),
            'donnees_externes' => null,
        ];

        $demande = $this->createProduitAuto($proposition, $user);
        if (!$demande) {
            return new JsonResponse([
                'reply' => "Désolé, une erreur est survenue. Réessayez plus tard.",
                'product_data' => [],
                'ready' => false,
                'products' => [],
                'chat_step' => 0,
                'chat_data' => [],
            ]);
        }

        $card = $this->buildDemandeCard($demande);
        return new JsonResponse([
            'reply' => "Merci ! Votre demande pour « " . ($proposition['nom']) . " » a été envoyée.\n⏳ Un administrateur validera votre produit sous peu.",
            'product_data' => [],
            'ready' => true,
            'products' => [$card],
            'chat_step' => 0,
            'chat_data' => [],
        ]);
    }

    #[Route('/suggest', name: 'user_products_suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        $body = $request->request->all();
        if (empty($body) && $request->getContent() !== '') {
            $body = json_decode($request->getContent(), true) ?? [];
        }
        $message = trim((string) ($body['message'] ?? $body['text'] ?? ''));
        $chatStep = (int) ($body['chat_step'] ?? 0);
        $chatDataRaw = $body['chat_data'] ?? null;
        if (is_string($chatDataRaw)) {
            $chatData = json_decode($chatDataRaw, true);
            $chatData = is_array($chatData) ? $chatData : [];
        } else {
            $chatData = is_array($chatDataRaw) ? $chatDataRaw : [];
        }

        $user = $this->getUser();
        $userId = $user && method_exists($user, 'getId') ? $user->getId() : null;

        // Mode guidé : le chatbot pose une question à la fois
        if ($chatStep >= 1 && $chatStep <= 3) {
            return $this->handleStepFlow($message, $chatStep, $chatData, $userId, $user);
        }

        // "Nouvelle demande" ou "recommencer" → relancer le flux guidé
        $m = mb_strtolower($message);
        if (str_contains($m, 'nouvelle demande') || str_contains($m, 'recommencer') || str_contains($m, 'autre produit')) {
            return new JsonResponse([
                'reply' => self::CHAT_QUESTIONS[1],
                'products' => [],
                'type' => 'question',
                'chat_step' => 1,
                'chat_data' => [],
            ]);
        }

        // Mode libre : message direct (salutations, ou demande complète)
        $result = $this->productAiService->analyzeAndRespond($message, $userId);

        if ($result['type'] === 'general') {
            return new JsonResponse([
                'reply' => $result['reply'],
                'products' => [],
                'type' => 'general',
                'chat_step' => 0,
                'chat_data' => [],
            ]);
        }

        $proposition = $result['data'];
        $demande = $this->createProduitAuto($proposition, $user);
        if ($demande === null) {
            return new JsonResponse([
                'reply' => 'Désolé, une erreur est survenue. Réessayez plus tard.',
                'products' => [],
                'type' => 'error',
                'chat_step' => 0,
                'chat_data' => [],
            ], 400);
        }

        $card = $this->buildDemandeCard($demande);
        return new JsonResponse([
            'reply' => ($result['reply'] ?? '') . "\n\n⏳ Votre demande a été envoyée ! Un administrateur validera votre produit sous peu.",
            'products' => [$card],
            'type' => 'proposition',
            'demande_id' => $demande->getId(),
            'chat_step' => 0,
            'chat_data' => [],
        ]);
    }

    /**
     * Gère le flux guidé : une question à la fois.
     */
    private function handleStepFlow(string $message, int $chatStep, array $chatData, ?int $userId, mixed $user): JsonResponse
    {
        if ($message === '') {
            return new JsonResponse([
                'reply' => self::CHAT_QUESTIONS[$chatStep] ?? 'Comment puis-je vous aider ?',
                'products' => [],
                'type' => 'question',
                'chat_step' => $chatStep,
                'chat_data' => $chatData,
            ]);
        }

        $m = mb_strtolower(trim($message));
        if ($chatStep === 1 && in_array($m, ['bonjour', 'salut', 'bonsoir', 'hello', 'hi', 'cava', 'ça va', 'coucou'], true)) {
            return new JsonResponse([
                'reply' => 'Bonjour ! ' . self::CHAT_QUESTIONS[1],
                'products' => [],
                'type' => 'question',
                'chat_step' => 1,
                'chat_data' => [],
            ]);
        }

        switch ($chatStep) {
            case 1:
                $chatData['nom'] = $message;
                return new JsonResponse([
                    'reply' => self::CHAT_QUESTIONS[2],
                    'products' => [],
                    'type' => 'question',
                    'chat_step' => 2,
                    'chat_data' => $chatData,
                ]);
            case 2:
                $chatData['description'] = $message;
                return new JsonResponse([
                    'reply' => self::CHAT_QUESTIONS[3],
                    'products' => [],
                    'type' => 'question',
                    'chat_step' => 3,
                    'chat_data' => $chatData,
                ]);
            case 3:
                $chatData['budget'] = strtolower($message) === 'non' ? null : (preg_match('/[\d.,]+/', $message, $m) ? (float) str_replace(',', '.', $m[0]) : null);
                break;
            default:
                return new JsonResponse([
                    'reply' => self::CHAT_QUESTIONS[1],
                    'products' => [],
                    'type' => 'question',
                    'chat_step' => 1,
                    'chat_data' => [],
                ]);
        }

        // Step 3 terminé : construire le message complet et générer la fiche
        $parts = ['produit : ' . ($chatData['nom'] ?? '')];
        if (!empty($chatData['description'])) {
            $parts[] = 'besoin : ' . $chatData['description'];
        }
        if (isset($chatData['budget']) && $chatData['budget'] > 0) {
            $parts[] = 'budget ' . $chatData['budget'] . ' DT';
        }
        $fullMessage = implode('. ', $parts);

        $result = $this->productAiService->analyzeAndRespond($fullMessage, $userId);

        if ($result['type'] === 'general') {
            return new JsonResponse([
                'reply' => $result['reply'] . ' Je relance : ' . self::CHAT_QUESTIONS[1],
                'products' => [],
                'type' => 'question',
                'chat_step' => 1,
                'chat_data' => [],
            ]);
        }

        $proposition = $result['data'];
        $demande = $this->createProduitAuto($proposition, $user);
        if ($demande === null) {
            return new JsonResponse([
                'reply' => 'Désolé, une erreur est survenue. Réessayez plus tard.',
                'products' => [],
                'type' => 'question',
                'chat_step' => 1,
                'chat_data' => [],
            ], 400);
        }

        $card = $this->buildDemandeCard($demande);
        return new JsonResponse([
            'reply' => ($result['reply'] ?? '') . "\n\n⏳ Votre demande a été envoyée ! Un administrateur validera votre produit sous peu.",
            'products' => [$card],
            'type' => 'proposition',
            'demande_id' => $demande->getId(),
            'chat_step' => 0,
            'chat_data' => [],
        ]);
    }

    private function createProduitAuto(array $proposition, mixed $user): ?DemandeProduit
    {
        $demande = new DemandeProduit();
        $demande->setDemandeClient($proposition['demande_client'] ?? $proposition['nom'] ?? 'Demande produit');
        $demande->setNom($proposition['nom'] ?? 'Produit');
        $demande->setDescription($proposition['description'] ?? null);
        
        $categorie = $proposition['categorie'] ?? null;
        if ($categorie instanceof Categorie) {
            $demande->setCategorie($categorie);
        } elseif (is_string($categorie)) {
            try {
                $demande->setCategorie(Categorie::from($categorie));
            } catch (\ValueError $e) {
                $demande->setCategorie(Categorie::SENSORIELS);
            }
        } else {
            $demande->setCategorie(Categorie::SENSORIELS);
        }
        
        $demande->setPrixEstime((float) ($proposition['prix_estime'] ?? $proposition['prix'] ?? 50.0));
        
        $donneesExternes = $proposition['donnees_externes'] ?? [];
        if (!is_array($donneesExternes)) {
            $donneesExternes = [];
        }
        
        $imageOptions = [];
        
        if (!empty($donneesExternes['results'])) {
            foreach ($donneesExternes['results'] as $result) {
                if (!empty($result['image_url'])) {
                    $imageOptions[] = [
                        'url' => $result['image_url'],
                        'source' => $result['source'] ?? 'Inconnu',
                        'price' => $result['price_tnd'] ?? null,
                    ];
                }
            }
        }
        
        if (!empty($proposition['image_from_search'])) {
            $imageOptions[] = ['url' => $proposition['image_from_search'], 'source' => 'Téléchargée', 'price' => null, 'local' => true];
        }
        
        if (!empty($donneesExternes['image_url'])) {
            $exists = false;
            foreach ($imageOptions as $opt) {
                if ($opt['url'] === $donneesExternes['image_url']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $imageOptions[] = ['url' => $donneesExternes['image_url'], 'source' => 'Recherche', 'price' => null];
            }
        }
        
        if (!empty($donneesExternes['image_path'])) {
            $imageOptions[] = ['url' => $donneesExternes['image_path'], 'source' => 'Local', 'price' => null, 'local' => true];
        }
        
        $uniqueUrls = [];
        $uniqueOptions = [];
        foreach ($imageOptions as $option) {
            if (!in_array($option['url'], $uniqueUrls)) {
                $uniqueUrls[] = $option['url'];
                $uniqueOptions[] = $option;
            }
        }
        
        $donneesExternes['image_options'] = array_slice($uniqueOptions, 0, 8);
        $donneesExternes['selected_image'] = $donneesExternes['image_options'][0]['url'] ?? null;
        
        $demande->setDonneesExternes($donneesExternes);
        
        if ($user instanceof \App\Entity\User) {
            $demande->setDemandeur($user);
        }
        
        $this->entityManager->persist($demande);
        
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        foreach ($admins as $admin) {
            $notif = new Notification();
            $notif->setDestinataire($admin);
            $notif->setType(Notification::TYPE_DEMANDE_PRODUIT_IA);
            $notif->setDemandeProduit($demande);
            $this->entityManager->persist($notif);
        }
        
        $this->entityManager->flush();
        
        return $demande;
    }

    private function buildProductCard(Produit $produit): array
    {
        return [
            'id' => $produit->getId(),
            'nom' => $produit->getNom(),
            'description' => $produit->getDescription() ? mb_substr($produit->getDescription(), 0, 120) . '…' : null,
            'prix' => $produit->getPrix(),
            'categorie' => $produit->getCategorie()?->label(),
            'image' => $produit->getImage(),
            'image_url' => null,
            'disponibilite' => $produit->isDisponibilite(),
            'en_attente' => !$produit->isValide(),
        ];
    }

    private function buildDemandeCard(DemandeProduit $demande): array
    {
        $donneesExternes = $demande->getDonneesExternes();
        $imageUrl = $donneesExternes['image_url'] ?? $donneesExternes['image_from_search'] ?? null;
        
        return [
            'id' => $demande->getId(),
            'nom' => $demande->getNom(),
            'description' => $demande->getDescription() ? mb_substr($demande->getDescription(), 0, 120) . '…' : null,
            'prix' => $demande->getPrixEstime(),
            'categorie' => $demande->getCategorie()?->label(),
            'image' => null,
            'image_url' => $imageUrl,
            'disponibilite' => true,
            'en_attente' => true,
        ];
    }
}
