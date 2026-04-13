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
    name: 'app:seed:demo-doctors',
    description: 'Create demo doctor accounts for local development.'
)]
class SeedDemoDoctorsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seedDoctors = [
            ['email' => 'dr.alvarez@demo.local', 'password' => 'doctor1234'],
            ['email' => 'dr.chen@demo.local', 'password' => 'doctor1234'],
            ['email' => 'dr.owens@demo.local', 'password' => 'doctor1234'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($seedDoctors as $doctorData) {
            $existing = $this->userRepository->findOneBy(['email' => $doctorData['email']]);
            if ($existing instanceof User) {
                ++$skipped;

                continue;
            }

            $doctor = (new User())
                ->setEmail($doctorData['email'])
                ->setPassword('')
                ->setRoleType(User::ROLE_TYPE_DOCTOR);

            $doctor->setPassword($this->passwordHasher->hashPassword($doctor, $doctorData['password']));
            $this->userRepository->save($doctor);
            ++$created;
        }

        $output->writeln(sprintf('Demo doctors created: %d', $created));
        $output->writeln(sprintf('Already existing: %d', $skipped));
        $output->writeln('Default password for seeded doctors: doctor1234');

        return Command::SUCCESS;
    }
}
