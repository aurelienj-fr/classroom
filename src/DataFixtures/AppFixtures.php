<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\Booking;
use App\Entity\ClassSession;
use App\Entity\Discipline;
use App\Entity\Studio;
use App\Entity\User;
use App\Enum\Level;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Demo data: fictional studios, a weekly timetable over the past week and the next three weeks.
 *
 * Accounts (password "monstudio-demo"): admin@monstudio.test, gerant@monstudio.test, membre@monstudio.test.
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'monstudio-demo';

    private const array DISCIPLINES = [
        'Yoga' => 'spa',
        'Pilates' => 'child-reaching',
        'Fitness' => 'dumbbell',
        'Danse' => 'music',
        'Boxe' => 'hand-fist',
        'Vélo indoor' => 'person-biking',
        'Méditation' => 'leaf',
        'Cardio HIIT' => 'heart-pulse',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $disciplines = [];
        foreach (self::DISCIPLINES as $name => $icon) {
            $discipline = (new Discipline())->setName($name)->setIcon($icon);
            $manager->persist($discipline);
            $disciplines[$name] = $discipline;
        }

        $admin = $this->createUser($manager, 'admin@monstudio.test', 'Alex', 'Martin', [User::ROLE_ADMIN]);
        $owner = $this->createUser($manager, 'gerant@monstudio.test', 'Camille', 'Durand', [User::ROLE_OWNER]);
        $member = $this->createUser($manager, 'membre@monstudio.test', 'Léa', 'Bernard');
        $otherMembers = [
            $this->createUser($manager, 'hugo@monstudio.test', 'Hugo', 'Petit'),
            $this->createUser($manager, 'ines@monstudio.test', 'Inès', 'Moreau'),
        ];

        $studios = [
            $this->createStudio(
                $manager,
                $owner,
                'Atelier Lumière',
                '12 rue des Lilas',
                '75011',
                'Paris',
                'Un studio lumineux au cœur du 11e, dédié au yoga et au pilates en petits groupes.',
                [$disciplines['Yoga'], $disciplines['Pilates'], $disciplines['Méditation']]
            ),
            $this->createStudio(
                $manager,
                $owner,
                'Le Hangar Fitness',
                '48 quai Perrache',
                '69002',
                'Lyon',
                'Cross-training, boxe et vélo indoor dans un ancien entrepôt réhabilité.',
                [$disciplines['Fitness'], $disciplines['Boxe'], $disciplines['Vélo indoor'], $disciplines['Cardio HIIT']]
            ),
            $this->createStudio(
                $manager,
                $admin,
                'Studio Garonne',
                '5 cours de l\'Intendance',
                '33000',
                'Bordeaux',
                'Danse contemporaine, yoga vinyasa et ateliers bien-être face aux quais.',
                [$disciplines['Danse'], $disciplines['Yoga']]
            ),
        ];
        $draft = $this->createStudio(
            $manager,
            $owner,
            'Projet Marseille',
            '1 La Canebière',
            '13001',
            'Marseille',
            null,
            [$disciplines['Yoga']]
        );
        $draft->setPublished(false);

        $timetable = [
            // studio index, name, discipline, level, duration, capacity, weekday (1 = Monday), hour, instructor
            [0, 'Hatha yoga', 'Yoga', Level::All, 60, 14, 1, '18:30', 'Claire'],
            [0, 'Vinyasa flow', 'Yoga', Level::Intermediate, 75, 12, 3, '19:00', 'Claire'],
            [0, 'Pilates matwork', 'Pilates', Level::Beginner, 50, 10, 2, '12:30', 'Julien'],
            [0, 'Méditation guidée', 'Méditation', Level::All, 30, 20, 6, '10:00', 'Sophie'],
            [1, 'Cross-training', 'Fitness', Level::Intermediate, 60, 16, 1, '07:00', 'Karim'],
            [1, 'Boxe anglaise', 'Boxe', Level::All, 60, 12, 4, '19:30', 'Nadia'],
            [1, 'RPM vélo', 'Vélo indoor', Level::All, 45, 20, 2, '18:00', 'Karim'],
            [1, 'HIIT 30', 'Cardio HIIT', Level::Advanced, 30, 3, 5, '12:15', 'Nadia'],
            [2, 'Danse contemporaine', 'Danse', Level::Beginner, 90, 15, 3, '20:00', 'Élodie'],
            [2, 'Yoga du dimanche', 'Yoga', Level::All, 60, 18, 7, '10:30', 'Marc'],
        ];

        $now = $this->clock->now();
        $mondayThisWeek = $now->modify('monday this week')->setTime(0, 0);
        $sessions = [];

        foreach ($timetable as [$studioIndex, $name, $disciplineName, $level, $duration, $capacity, $weekday, $time, $instructor]) {
            $activity = (new Activity($studios[$studioIndex]))
                ->setName($name)
                ->setDiscipline($disciplines[$disciplineName])
                ->setLevel($level)
                ->setDurationMinutes($duration)
                ->setDefaultCapacity($capacity)
                ->setDescription(\sprintf('Cours de %s de %d minutes, encadré par %s.', mb_strtolower($disciplineName), $duration, $instructor));
            $manager->persist($activity);

            [$hour, $minute] = array_map('intval', explode(':', $time));
            // From last week (history) to three weeks ahead.
            for ($week = -1; $week <= 3; ++$week) {
                $startsAt = $mondayThisWeek
                    ->modify(\sprintf('%+d weeks', $week))
                    ->modify(\sprintf('+%d days', $weekday - 1))
                    ->setTime($hour, $minute);
                $session = (new ClassSession($activity, $startsAt))->setInstructorName($instructor);
                $manager->persist($session);
                $sessions[] = $session;
            }
        }

        // A few bookings for the demo member and others (respecting capacity).
        $bookers = [$member, ...$otherMembers];
        foreach ($sessions as $index => $session) {
            if (0 !== $index % 3) {
                continue;
            }
            foreach ($bookers as $position => $user) {
                if ($position >= $session->getCapacity()) {
                    break;
                }
                $booking = new Booking($user, $session, $session->getStartsAt()->modify('-3 days'));
                if ($session->hasStarted($now) && 0 === $position) {
                    $booking->toggleCheckIn($session->getStartsAt());
                }
                $manager->persist($booking);
            }
        }

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(ObjectManager $manager, string $email, string $firstName, string $lastName, array $roles = []): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
        $manager->persist($user);

        return $user;
    }

    /**
     * @param list<Discipline> $disciplines
     */
    private function createStudio(
        ObjectManager $manager,
        User $owner,
        string $name,
        string $address,
        string $postalCode,
        string $city,
        ?string $description,
        array $disciplines,
    ): Studio {
        $studio = (new Studio($owner))
            ->setName($name)
            ->setAddress($address)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setDescription($description)
            ->setPhone('01 23 45 67 89')
            ->setEmail('contact@'.(new AsciiSlugger())->slug($name)->lower()->toString().'.test')
            ->setPublished(true);
        foreach ($disciplines as $discipline) {
            $studio->addDiscipline($discipline);
        }
        $manager->persist($studio);

        return $studio;
    }
}
