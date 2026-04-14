<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:demo-admins',
    description: 'Create demo admin accounts for local development.'
)]
class SeedDemoAdminsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seedAdmins = [
            ['email' => 'admin.one@demo.local', 'password' => 'admin1234'],
            ['email' => 'admin.two@demo.local', 'password' => 'admin1234'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($seedAdmins as $adminData) {
            $existing = $this->userRepository->findOneBy(['email' => $adminData['email']]);
            if ($existing instanceof User) {
                ++$skipped;

                continue;
            }

            $admin = (new User())
                ->setEmail($adminData['email'])
                ->setPassword('')
                ->setRoleType(User::ROLE_TYPE_ADMIN);

            $admin->setPassword($this->passwordHasher->hashPassword($admin, $adminData['password']));
            $this->userRepository->save($admin);
            ++$created;
        }

        $output->writeln(sprintf('Demo admins created: %d', $created));
        $output->writeln(sprintf('Already existing: %d', $skipped));
        $output->writeln('Default password for seeded admins: admin1234');

        return Command::SUCCESS;
    }
}
