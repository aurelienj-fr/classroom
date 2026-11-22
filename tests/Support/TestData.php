<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Activity;
use App\Entity\ClassSession;
use App\Entity\Discipline;
use App\Entity\Studio;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Small builders for test data persisted in the (transaction-wrapped) test database.
 */
final readonly class TestData
{
    public const string PASSWORD = 'correct-horse-battery';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function user(string $email, array $roles = []): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function discipline(string $name = 'Yoga'): Discipline
    {
        $discipline = $this->entityManager->getRepository(Discipline::class)->findOneBy(['name' => $name]);
        if (null !== $discipline) {
            return $discipline;
        }

        $discipline = (new Discipline())->setName($name)->setIcon('spa');
        $this->entityManager->persist($discipline);
        $this->entityManager->flush();

        return $discipline;
    }

    public function studio(User $owner, string $name = 'Studio Test', string $city = 'Paris', bool $published = true, int $cancellationDeadlineHours = 2): Studio
    {
        $studio = (new Studio($owner))
            ->setName($name)
            ->setAddress('1 rue du Test')
            ->setPostalCode('75001')
            ->setCity($city)
            ->setPublished($published)
            ->setCancellationDeadlineHours($cancellationDeadlineHours)
            ->addDiscipline($this->discipline());
        $this->entityManager->persist($studio);
        $this->entityManager->flush();

        return $studio;
    }

    public function session(Studio $studio, \DateTimeImmutable $startsAt, int $capacity = 10, string $name = 'Hatha yoga'): ClassSession
    {
        $activity = (new Activity($studio))
            ->setName($name)
            ->setDiscipline($this->discipline())
            ->setDurationMinutes(60)
            ->setDefaultCapacity($capacity);
        $session = new ClassSession($activity, $startsAt);
        $this->entityManager->persist($activity);
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }
}
