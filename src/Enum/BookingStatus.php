<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum BookingStatus: string implements TranslatableInterface
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    /** The studio cancelled the session: the seat is released and the member is to be recredited. */
    case CancelledByStudio = 'cancelled_by_studio';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('booking_status.'.$this->value, locale: $locale);
    }
}
