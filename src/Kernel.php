<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Session times are stored as local wall-clock time (DATETIME without zone):
     * the application timezone must not depend on the PHP configuration.
     */
    public const string TIMEZONE = 'Europe/Paris';

    public function boot(): void
    {
        date_default_timezone_set(self::TIMEZONE);

        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
