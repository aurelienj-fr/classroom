<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Booking;
use App\Entity\User;
use App\Enum\OwnerRequestStatus;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds and sends the platform e-mails, each in the recipient's language.
 *
 * Messages are queued (Messenger "async" transport) and sent by the worker.
 */
final readonly class EmailNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private UserRepository $userRepository,
    ) {
    }

    public function bookingConfirmed(Booking $booking): void
    {
        $this->send($booking->getMember(), 'booking_confirmed', [
            'booking' => $booking,
            'session' => $booking->getSession(),
        ], ['%activity%' => $booking->getSession()->getActivity()->getName()]);
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->send($booking->getMember(), 'booking_cancelled', [
            'booking' => $booking,
            'session' => $booking->getSession(),
        ], ['%activity%' => $booking->getSession()->getActivity()->getName()]);
    }

    public function sessionCancelledByStudio(Booking $booking): void
    {
        $this->send($booking->getMember(), 'session_cancelled', [
            'booking' => $booking,
            'session' => $booking->getSession(),
        ], ['%activity%' => $booking->getSession()->getActivity()->getName()]);
    }

    /**
     * Tells every administrator that a member asked to become a studio owner.
     */
    public function ownerRequestSubmitted(User $applicant): void
    {
        foreach ($this->userRepository->findAdministrators() as $administrator) {
            $this->send($administrator, 'owner_request_submitted', ['applicant' => $applicant], ['%name%' => $applicant->getFullName()]);
        }
    }

    public function ownerRequestReviewed(User $applicant): void
    {
        $approved = OwnerRequestStatus::Approved === $applicant->getOwnerRequestStatus();
        $this->send($applicant, $approved ? 'owner_request_approved' : 'owner_request_rejected', []);
    }

    public function ownerRoleRelinquished(User $user, int $unpublishedStudios, int $cancelledSessions): void
    {
        $this->send($user, 'owner_role_relinquished', [
            'unpublished_studios' => $unpublishedStudios,
            'cancelled_sessions' => $cancelledSessions,
        ]);
    }

    /**
     * @param array<string, mixed>      $context           template variables
     * @param array<string, int|string> $subjectParameters
     */
    private function send(User $recipient, string $name, array $context, array $subjectParameters = []): void
    {
        $locale = $recipient->getLocale();

        $email = (new TemplatedEmail())
            ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
            ->subject($this->translator->trans('email.'.$name.'.subject', $subjectParameters, locale: $locale))
            ->htmlTemplate('emails/'.$name.'.html.twig')
            ->locale($locale)
            ->context($context + ['recipient' => $recipient, 'locale' => $locale]);

        $this->mailer->send($email);
    }
}
