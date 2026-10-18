<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Locale\App;

use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;

/**
 * Host env vars holding a locale list, as %env(json:…)% and %env(csv:…)% read them at runtime.
 */
final class LocaleEnvVarLoader implements EnvVarLoaderInterface
{
    public const JSON = 'NOWO_ROUTING_KIT_TEST_LOCALES_JSON';

    public const CSV = 'NOWO_ROUTING_KIT_TEST_LOCALES_CSV';

    public function loadEnvVars(): array
    {
        return [self::JSON => '["pt","en"]', self::CSV => 'it,en'];
    }
}
