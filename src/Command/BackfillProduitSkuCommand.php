<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:produit:backfill-sku',
    description: 'Attribue un SKU auto-incrémenté aux produits existants qui n\'en ont pas.',
)]
final class BackfillProduitSkuCommand extends Command
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $produits = $this->produitRepository->findBy([], ['id' => 'ASC']);
        $updated = 0;
        foreach ($produits as $p) {
            if ($p->getSku() === null || $p->getSku() === '') {
                $p->setSku('PRD-' . str_pad((string) $p->getId(), 6, '0', STR_PAD_LEFT));
                $this->entityManager->persist($p);
                $updated++;
            }
        }
        if ($updated > 0) {
            $this->entityManager->flush();
        }
        $io->success($updated . ' produit(s) mis à jour avec un SKU.');
        return Command::SUCCESS;
    }
}