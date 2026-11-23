<?php

declare(strict_types=1);

namespace App\Command;

use App\Messenger\MessageDrainer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the queued messages (e-mails) and exits when the queue is empty.
 * Meant for a cron on shared hosting, where "messenger:consume" cannot run permanently.
 */
#[AsCommand(name: 'app:messages:send', description: 'Send queued messages (e-mails) and stop when the queue is empty')]
final readonly class SendQueuedMessagesCommand
{
    public function __construct(private MessageDrainer $drainer)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Maximum number of messages to process')] int $limit = 500,
        #[Option(description: 'Maximum duration in seconds')] int $timeLimit = 50,
    ): int {
        $this->drainer->drain($limit, $timeLimit);
        $io->success('Queued messages processed.');

        return Command::SUCCESS;
    }
}
