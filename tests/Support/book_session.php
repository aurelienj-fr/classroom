<?php

declare(strict_types=1);

/*
 * Books a session from a separate PHP process (used by BookingConcurrencyTest).
 *
 * Usage: php tests/Support/book_session.php <sessionId> <memberEmail>
 * Prints "ready" once the kernel is booted, then "booked" or the BookingException key.
 */

use App\Booking\BookingException;
use App\Booking\BookingManager;
use App\Entity\ClassSession;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

$kernel = new Kernel('test', false);
$kernel->boot();
// The test container exposes private services; it only exists in the "test" environment.
$container = $kernel->getContainer()->get('test.service_container'); // @phpstan-ignore symfonyContainer.serviceNotFound
\assert($container instanceof ContainerInterface);

/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get(EntityManagerInterface::class); // @phpstan-ignore symfonyContainer.privateService (test container)
$session = $entityManager->find(ClassSession::class, (int) $argv[1]);
$member = $entityManager->getRepository(User::class)->findOneBy(['email' => $argv[2]]);
if (null === $session || null === $member) {
    fwrite(\STDERR, "Unknown session or member\n");
    exit(2);
}

echo "ready\n";
flush();

try {
    $container->get(BookingManager::class)->book($member, $session); // @phpstan-ignore symfonyContainer.privateService (test container)
    echo "booked\n";
} catch (BookingException $exception) {
    echo $exception->getMessage()."\n";
}
