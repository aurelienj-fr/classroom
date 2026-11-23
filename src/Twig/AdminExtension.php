<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Attribute\AsTwigFunction;

final readonly class AdminExtension
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    /**
     * Number of owner requests awaiting an administrator (navigation badge).
     */
    #[AsTwigFunction('pending_owner_requests')]
    public function pendingOwnerRequests(): int
    {
        return $this->userRepository->countPendingOwnerRequests();
    }
}
