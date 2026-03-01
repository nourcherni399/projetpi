<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LibreTranslateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:test-libretranslate', description: 'Teste la connexion à LibreTranslate')]
final class TestLibreTranslateCommand extends Command
{
    public function __construct(
        private readonly LibreTranslateService $libreTranslate,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test LibreTranslate');

        $text = 'Bonjour';
        $io->section('Traduction FR → EN');
        $io->text("Texte source : « {$text} »");

        try {
            $result = $this->libreTranslate->translate($text, 'fr', 'en');
            if ($result === $text) {
                $io->error('La traduction a échoué (texte identique retourné). Vérifiez que le conteneur emna tourne : docker ps --filter name=emna');
                return Command::FAILURE;
            }
            $io->success("Résultat : « {$result} »");
            $io->note('LibreTranslate fonctionne correctement.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Erreur : ' . $e->getMessage());
            $io->text('Assurez-vous que le conteneur emna est démarré : docker start emna');
            return Command::FAILURE;
        }
    }
}
