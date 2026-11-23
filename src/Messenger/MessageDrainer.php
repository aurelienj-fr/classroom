<?php

declare(strict_types=1);

namespace App\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Processes the queued messages (e-mails) of the "async" transport, then stops as soon as the
 * queue is empty or a limit is reached.
 *
 * Shared hosting has no long-running worker: this is called at the end of web requests
 * (ConsumeOnTerminateListener) and by an hourly cron (app:messages:send). It reuses Symfony's
 * Worker, so retries and the failure transport behave as with "messenger:consume".
 */
final readonly class MessageDrainer
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.async')]
        private ReceiverInterface $receiver,
        private MessageBusInterface $bus,
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function drain(int $messageLimit, int $timeLimitInSeconds): void
    {
        $stopWhenIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $limits = [
            new StopWorkerOnMessageLimitListener($messageLimit, $this->logger),
            new StopWorkerOnTimeLimitListener($timeLimitInSeconds, $this->logger),
        ];

        $this->eventDispatcher->addListener(WorkerRunningEvent::class, $stopWhenIdle);
        foreach ($limits as $limit) {
            $this->eventDispatcher->addSubscriber($limit);
        }

        try {
            $worker = new Worker(['async' => $this->receiver], $this->bus, $this->eventDispatcher, $this->logger);
            $worker->run(['sleep' => 0]);
        } finally {
            $this->eventDispatcher->removeListener(WorkerRunningEvent::class, $stopWhenIdle);
            foreach ($limits as $limit) {
                $this->eventDispatcher->removeSubscriber($limit);
            }
        }
    }
}
