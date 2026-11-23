<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\TestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class WebTestCaseHelper extends WebTestCase
{
    use ClockSensitiveTrait;

    protected KernelBrowser $client;
    protected TestData $data;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::mockTime('2026-11-22 10:00:00');
        // Rate limiters (login throttling, registration) persist in the cache: start each test clean.
        static::getContainer()->get('cache.rate_limiter')->clear();

        $container = static::getContainer();
        $this->data = new TestData(
            $container->get(EntityManagerInterface::class),
            $container->get(UserPasswordHasherInterface::class),
        );
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
