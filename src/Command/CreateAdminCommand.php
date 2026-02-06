<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Admin;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur (ex. pour accéder à l’interface /admin).',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email de l’admin', 'admin@auticare.fr')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Mot de passe (sinon demandé en interactif)')
            ->addOption('nom', null, InputOption::VALUE_OPTIONAL, 'Nom', 'Admin')
            ->addOption('prenom', null, InputOption::VALUE_OPTIONAL, 'Prénom', 'Admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $nom = $input->getOption('nom');
        $prenom = $input->getOption('prenom');

        if ($email === null || $email === '') {
            $io->error('L’email est requis.');
            return Command::FAILURE;
        }

        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing !== null) {
            $io->warning(sprintf('Un utilisateur avec l’email "%s" existe déjà. Connectez-vous avec ce compte pour accéder à /admin.', $email));
            return Command::SUCCESS;
        }

        if ($password === null || $password === '') {
            $password = $io->askHidden('Mot de passe pour l’admin');
            if ($password === null || $password === '') {
                $io->error('Le mot de passe est requis.');
                return Command::FAILURE;
            }
        }

        $admin = new Admin();
        $admin->setNom($nom);
        $admin->setPrenom($prenom);
        $admin->setEmail($email);
        $admin->setTelephone(0);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
        $admin->setIsActive(true);
        $admin->setRole(UserRole::ADMIN);
        $now = new \DateTimeImmutable();
        $admin->setCreatedAt($now);
        $admin->setUpdatedAt($now);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Compte admin créé : %s. Vous pouvez vous connecter sur /connexion puis accéder à /admin.', $email));
        return Command::SUCCESS;
    }
}
