<?php

namespace App\Command;

use App\Entity\User;
use App\Service\AuthManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote', description: 'Donne (ou retire avec --revoke) le rôle ROLE_ADMIN à un utilisateur')]
class PromoteUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail du compte')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Retire le rôle ROLE_ADMIN au lieu de l\'ajouter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = AuthManager::normalizeEmail($input->getArgument('email'));

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $io->error(sprintf('Aucun utilisateur avec l\'e-mail "%s".', $email));

            return Command::FAILURE;
        }

        $roles = array_diff($user->getRoles(), ['ROLE_USER', 'ROLE_ADMIN']);
        if (!$input->getOption('revoke')) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);
        $this->em->flush();

        $io->success(sprintf('%s : %s', $email, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
