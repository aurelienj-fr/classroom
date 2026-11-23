<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Tests\Support\TestData;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Process;

/**
 * Exercises the SELECT … FOR UPDATE lock of BookingManager::book() with two real connections.
 *
 * Data must be committed to be visible to the other process, so this test bypasses the
 * per-test rollback and cleans up after itself.
 */
#[SkipDatabaseRollback]
final class BookingConcurrencyTest extends KernelTestCase
{
    private const string EMAIL_DOMAIN = '@concurrency.test';

    protected function tearDown(): void
    {
        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $connection->executeStatement(
            'DELETE b FROM booking b INNER JOIN `user` u ON u.id = b.member_id WHERE u.email LIKE ?',
            ['%'.self::EMAIL_DOMAIN],
        );
        // Children first: MySQL does not order multi-table deletes by foreign keys.
        $connection->executeStatement(
            'DELETE cs FROM class_session cs INNER JOIN activity a ON a.id = cs.activity_id
             INNER JOIN studio s ON s.id = a.studio_id INNER JOIN `user` u ON u.id = s.owner_id WHERE u.email LIKE ?',
            ['%'.self::EMAIL_DOMAIN],
        );
        $connection->executeStatement(
            'DELETE a FROM activity a INNER JOIN studio s ON s.id = a.studio_id
             INNER JOIN `user` u ON u.id = s.owner_id WHERE u.email LIKE ?',
            ['%'.self::EMAIL_DOMAIN],
        );
        $connection->executeStatement(
            'DELETE s FROM studio s INNER JOIN `user` u ON u.id = s.owner_id WHERE u.email LIKE ?',
            ['%'.self::EMAIL_DOMAIN],
        );
        $connection->executeStatement('DELETE FROM `user` WHERE email LIKE ?', ['%'.self::EMAIL_DOMAIN]);

        parent::tearDown();
    }

    public function testConcurrentBookingWaitsForTheLockAndSeesTheLastSeatTaken(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $data = new TestData($entityManager, $container->get(UserPasswordHasherInterface::class));

        $owner = $data->user('owner'.self::EMAIL_DOMAIN, [User::ROLE_OWNER]);
        // Real clock here: the other process is not affected by mocked time.
        $session = $data->session($data->studio($owner), new \DateTimeImmutable('+30 days'), capacity: 1);
        $first = $data->user('first'.self::EMAIL_DOMAIN);
        $data->user('second'.self::EMAIL_DOMAIN);

        // Connection A holds the session lock, as a concurrent book() call would.
        $lockConnection = $this->openSeparateConnection($entityManager->getConnection());
        $lockConnection->beginTransaction();
        $lockConnection->executeQuery('SELECT id FROM class_session WHERE id = ? FOR UPDATE', [$session->getId()]);

        // Process B tries to book the same (last) seat and must block on the lock.
        $process = new Process(
            [\PHP_BINARY, __DIR__.'/../Support/book_session.php', (string) $session->getId(), 'second'.self::EMAIL_DOMAIN],
            \dirname(__DIR__, 2),
            ['APP_ENV' => 'test'],
        );
        $process->setTimeout(60);
        $process->start();
        $process->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'ready'));
        usleep(500_000);
        self::assertTrue($process->isRunning(), 'The booking must wait for the lock held by connection A.');

        // Connection A takes the last seat, then releases the lock.
        $lockConnection->insert('booking', [
            'member_id' => $first->getId(),
            'session_id' => $session->getId(),
            'status' => 'confirmed',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $lockConnection->commit();
        $lockConnection->close();

        $process->wait();
        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        self::assertStringContainsString('booking.error.session_full', $process->getOutput());

        $confirmed = (int) $entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM booking WHERE session_id = ? AND status = 'confirmed'",
            [$session->getId()],
        );
        self::assertSame(1, $confirmed, 'No overbooking.');
    }

    private function openSeparateConnection(Connection $connection): Connection
    {
        $params = $connection->getParams();
        unset($params['wrapperClass']);

        return DriverManager::getConnection($params);
    }
}
