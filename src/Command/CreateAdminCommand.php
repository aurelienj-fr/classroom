<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates a platform administrator.
 *
 * Without SSH on the production host, run it locally with --sql and import the printed
 * statement with phpMyAdmin: the password is hashed locally, never written in clear.
 */
#[AsCommand(name: 'app:admin:create', description: 'Create a platform administrator (or print the SQL to create it)')]
final readonly class CreateAdminCommand
{
    private const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail address (login)')] string $email,
        #[Argument(description: 'First name')] string $firstName,
        #[Argument(description: 'Last name')] string $lastName,
        #[Option(description: 'Print an SQL INSERT instead of writing to the database')] bool $sql = false,
        #[Option(description: 'Preferred language for e-mails (fr, en, es)')] string $locale = 'fr',
    ): int {
        $password = (string) $io->askHidden('Password (12 characters minimum)', static function (?string $value): string {
            if (null === $value || mb_strlen($value) < self::MIN_PASSWORD_LENGTH) {
                throw new \RuntimeException(\sprintf('The password must be at least %d characters long.', self::MIN_PASSWORD_LENGTH));
            }

            return $value;
        });

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setLocale($locale)
            ->setRoles([User::ROLE_ADMIN]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        // Property constraints only: with --sql the local database is not the production one,
        // so e-mail uniqueness is enforced by the unique index when the SQL is imported.
        $violations = [];
        foreach (['email', 'firstName', 'lastName'] as $property) {
            foreach ($this->validator->validateProperty($user, $property) as $violation) {
                $violations[] = $property.': '.$violation->getMessage();
            }
        }
        if (!$sql && null !== $this->entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()])) {
            $violations[] = 'email: an account already exists with this address.';
        }
        if ([] !== $violations) {
            $io->error($violations);

            return Command::FAILURE;
        }

        if ($sql) {
            $connection = $this->entityManager->getConnection();
            $io->writeln(\sprintf(
                'INSERT INTO `user` (email, roles, password, first_name, last_name, created_at, locale) VALUES (%s, %s, %s, %s, %s, NOW(), %s);',
                $connection->quote($user->getEmail()),
                $connection->quote((string) json_encode([User::ROLE_ADMIN])),
                $connection->quote($user->getPassword()),
                $connection->quote($user->getFirstName()),
                $connection->quote($user->getLastName()),
                $connection->quote($user->getLocale()),
            ));

            return Command::SUCCESS;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $io->success(\sprintf('Administrator %s created.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
