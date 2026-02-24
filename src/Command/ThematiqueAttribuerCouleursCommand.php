<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ThematiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:thematique-attribuer-couleurs',
    description: 'Attribue les couleurs de la palette aux thématiques (Culture & Arts, Famille & Loisirs, Éducation, Sensoriel, Technologie).',
)]
final class ThematiqueAttribuerCouleursCommand extends Command
{
    private const COULEURS_PAR_NOM = [
        'Culture & Arts' => '#845EC2',   // violet
        'Famille & Loisirs' => '#D65DB1', // magenta
        'Éducation' => '#FFC75F',         // jaune-orange
        'Sensoriel' => '#FF6F91',         // corail
        'Technologie' => '#FF9671',       // orange clair
    ];

    public function __construct(
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Attribution des couleurs aux thématiques');

        $count = 0;
        foreach (self::COULEURS_PAR_NOM as $nom => $couleur) {
            $thematique = $this->thematiqueRepository->findOneBy(['nomThematique' => $nom]);
            if ($thematique !== null) {
                $thematique->setCouleur($couleur);
                $this->entityManager->persist($thematique);
                $io->text(sprintf('  ✓ %s → %s', $nom, $couleur));
                ++$count;
            } else {
                $io->warning(sprintf('  Thématique non trouvée : "%s"', $nom));
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('%d thématique(s) mise(s) à jour.', $count));
        } else {
            $io->note('Aucune thématique mise à jour. Vérifiez les noms en base (Culture & Arts, Famille & Loisirs, etc.).');
        }

        return Command::SUCCESS;
    }
}
