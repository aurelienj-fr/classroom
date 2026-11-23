<?php

declare(strict_types=1);

namespace App\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

/**
 * Shared hosting mode (MESSENGER_CONSUME_ON_TERMINATE=1): when a request queued messages,
 * they are sent right after the response, so the visitor does not wait for the mail server.
 *
 * With PHP-FPM the response is flushed before kernel.terminate (fastcgi_finish_request()).
 */
final class ConsumeOnTerminateListener
{
    private const int MESSAGE_LIMIT = 50;
    private const int TIME_LIMIT_SECONDS = 20;

    private bool $messagesQueued = false;

    public function __construct(
        private readonly MessageDrainer $drainer,
        #[Autowire(env: 'bool:MESSENGER_CONSUME_ON_TERMINATE')]
        private readonly bool $enabled,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[AsEventListener]
    public function onMessageQueued(SendMessageToTransportsEvent $event): void
    {
        $this->messagesQueued = true;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onTerminate(TerminateEvent $event): void
    {
        if (!$this->enabled || !$this->messagesQueued) {
            return;
        }
        $this->messagesQueued = false;

        try {
            $this->drainer->drain(self::MESSAGE_LIMIT, self::TIME_LIMIT_SECONDS);
        } catch (\Throwable $exception) {
            // Never break a request that already succeeded: the hourly cron will retry.
            $this->logger?->error('Sending queued messages after the response failed.', ['exception' => $exception]);
        }
    }
}
