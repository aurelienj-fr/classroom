<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Messenger\ConsumeOnTerminateListener;
use App\Messenger\MessageDrainer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Mime\Email;

final class MessageDrainerTest extends KernelTestCase
{
    public function testDrainSendsQueuedEmailsAndStopsWhenTheQueueIsEmpty(): void
    {
        self::bootKernel();
        $transport = $this->asyncTransport();
        $this->queueEmails(3);
        self::assertCount(3, $transport->getSent());

        $start = microtime(true);
        static::getContainer()->get(MessageDrainer::class)->drain(50, 20);

        self::assertCount(3, $transport->getAcknowledged());
        self::assertSame([], $transport->get(), 'Queue empty.');
        self::assertLessThan(5, microtime(true) - $start, 'Stops when idle instead of waiting for the time limit.');
    }

    public function testDrainHonoursTheMessageLimit(): void
    {
        self::bootKernel();
        $transport = $this->asyncTransport();
        $this->queueEmails(3);

        static::getContainer()->get(MessageDrainer::class)->drain(2, 20);

        self::assertCount(2, $transport->getAcknowledged());
    }

    public function testTerminateListenerSendsOnlyWhenEnabledAndMessagesWereQueued(): void
    {
        $kernel = self::bootKernel();
        $transport = $this->asyncTransport();
        $drainer = static::getContainer()->get(MessageDrainer::class);
        $terminate = new TerminateEvent($kernel, Request::create('/'), new Response());

        // Disabled (default in .env): queued messages wait for the worker / cron.
        $disabled = new ConsumeOnTerminateListener($drainer, false);
        $this->queueEmails(1);
        $disabled->onMessageQueued(new SendMessageToTransportsEvent(new Envelope(new \stdClass()), []));
        $disabled->onTerminate($terminate);
        self::assertCount(0, $transport->getAcknowledged());

        // Enabled but nothing queued during this request: no work.
        $enabled = new ConsumeOnTerminateListener($drainer, true);
        $enabled->onTerminate($terminate);
        self::assertCount(0, $transport->getAcknowledged());

        // Enabled and a message was queued: sent after the response.
        $enabled->onMessageQueued(new SendMessageToTransportsEvent(new Envelope(new \stdClass()), []));
        $enabled->onTerminate($terminate);
        self::assertCount(1, $transport->getAcknowledged());
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function queueEmails(int $count): void
    {
        $mailer = static::getContainer()->get(MailerInterface::class);
        for ($i = 1; $i <= $count; ++$i) {
            $mailer->send((new Email())->to('member@example.test')->subject('Test '.$i)->text('Hello'));
        }
    }
}
