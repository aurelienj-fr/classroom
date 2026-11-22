<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Rebuild the test database schema from the migrations once per run.
// Each test then runs inside a transaction rolled back by DAMADoctrineTestBundle.
if ('1' !== ($_SERVER['SKIP_TEST_DB_SETUP'] ?? '')) {
    foreach ([
        ['doctrine:database:drop', '--force', '--if-exists'],
        ['doctrine:database:create'],
        ['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'],
    ] as $command) {
        $process = new Process([\PHP_BINARY, dirname(__DIR__).'/bin/console', ...$command, '--env=test', '--quiet']);
        $process->mustRun();
    }
}
