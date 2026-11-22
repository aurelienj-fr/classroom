<?php

declare(strict_types=1);

namespace App\Booking;

/**
 * A booking rule was violated. The message is safe to display to the member.
 */
final class BookingException extends \DomainException
{
    public static function sessionCancelled(): self
    {
        return new self('Cette séance a été annulée par le studio.');
    }

    public static function sessionStarted(): self
    {
        return new self('Cette séance a déjà commencé ou est terminée.');
    }

    public static function studioNotPublished(): self
    {
        return new self('Ce studio n\'accepte pas de réservations pour le moment.');
    }

    public static function alreadyBooked(): self
    {
        return new self('Vous avez déjà réservé cette séance.');
    }

    public static function sessionFull(): self
    {
        return new self('Cette séance est complète.');
    }

    public static function notConfirmed(): self
    {
        return new self('Cette réservation est déjà annulée.');
    }

    public static function cancellationDeadlinePassed(int $hours): self
    {
        return new self(\sprintf(
            'Le délai d\'annulation est dépassé : annulation possible jusqu\'à %d h avant le début de la séance.',
            $hours,
        ));
    }

    public static function sessionNotFound(): self
    {
        return new self('Séance introuvable.');
    }
}
