<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use InvalidArgumentException;

use function implode;

/**
 * The redirect was rejected by {@see UrlRedirectValidator} (since 1.17.0); nothing was written.
 */
final class InvalidUrlRedirectException extends InvalidArgumentException
{
    /**
     * @param array<string, string> $errors field (source_path, target_url, status_code, note) => translation key
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Invalid URL redirect: ' . implode(', ', $errors));
    }

    /**
     * The values are translation keys the panel renders to the operator (an unknown key is shown as-is), never
     * messages: keep user data, storage messages and internal detail out of them.
     *
     * @return array<string, string> field => translation key (domain NowoRoutingKitBundle)
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
