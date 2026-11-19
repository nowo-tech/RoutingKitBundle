<?php

declare(strict_types=1);

/*
 * One racing worker for FilesystemStoreRaceTest, run as a separate PHP process:
 *   race-worker.php <autoload> <go-file> <mode> <store-file> <worker-number> <iterations>
 * It waits for <go-file> (so every worker starts together), runs <mode> and prints one line per outcome.
 */

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;

[, $autoload, $go, $mode, $file, $worker, $iterations] = $argv;
require $autoload;

$until = microtime(true) + 20;
while (!is_file($go) && microtime(true) < $until) {
    usleep(1_000);
}

$redirects = new FilesystemUrlRedirectStorage($file, 15.0);
$paths     = new FilesystemRoutePathStorage($file, 15.0);

for ($i = 0; $i < (int) $iterations; ++$i) {
    try {
        match ($mode) {
            'hits'        => $redirects->recordHit('/hot', new DateTimeImmutable()),
            'distinct'    => $redirects->save((new UrlRedirect())->setSourcePath('/w' . $worker . '-' . $i)->setTargetUrl('/t')),
            'same-source' => $redirects->save((new UrlRedirect())->setSourcePath('/same')->setTargetUrl('/from-' . $worker)),
            'paths'       => $paths->save(new RoutePathDefinition('route_' . $worker . '_' . $i, 'en', '/p' . $worker . '-' . $i)),
            'same-route'  => $paths->save(new RoutePathDefinition('app_same', 'en', '/from-' . $worker)),
            default       => throw new InvalidArgumentException('mode'),
        };
        echo "ok\n";
    } catch (RuntimeException $e) {
        echo 'refused: ' . $e->getMessage() . "\n";
    }
}
