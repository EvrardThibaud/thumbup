<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminUserCommand extends Command
{
    // fallback au cas où l'attribut ne serait pas pris en compte
    protected static $defaultName = 'app:create-admin';
    protected static $defaultDescription = 'Create an admin user';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');

        $userRepo = $this->em->getRepository(User::class);
        if ($userRepo->findOneBy(['email' => $email])) {
            $output->writeln('<error>User already exists.</error>');
            return Command::FAILURE;
        }

        $client = (new Client())
            ->setName('ThumbUp Admin')
            ->setChannelUrl(null);
        $this->em->persist($client);

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setClient($client);

        if (method_exists($user, 'setIsVerified')) {
            $user->setIsVerified(true);
        }
        if (method_exists($user, 'setCreatedAt')) {
            $user->setCreatedAt(new \DateTimeImmutable());
        }

        $user->setPassword(
            $this->hasher->hashPassword($user, $password)
        );

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>Admin created.</info>');

        return Command::SUCCESS;
    }
}
