<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * State of a member's request to become a studio owner (validated by a platform administrator).
 */
enum OwnerRequestStatus: string implements TranslatableInterface
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('owner_request.status.'.$this->value, locale: $locale);
    }
}
