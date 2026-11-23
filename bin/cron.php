<?php

/*
 * Hourly cron entry point for shared hosting (OVHcloud, IONOS…), whose cron panels run a PHP
 * file without arguments. Sends the queued e-mails left over (retries, failures).
 *
 * Equivalent to: php bin/console app:messages:send --time-limit=50 --env=prod
 */

declare(strict_types=1);

$console = __DIR__.'/console';

$_SERVER['argv'] = [$console, 'app:messages:send', '--time-limit=50', '--no-interaction'];
$_SERVER['argc'] = \count($_SERVER['argv']);
// Symfony Runtime executes the script named here: run the console, not this file.
$_SERVER['SCRIPT_FILENAME'] = $console;

require $console;
