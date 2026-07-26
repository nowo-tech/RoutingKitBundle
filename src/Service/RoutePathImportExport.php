<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Service;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use RuntimeException;

use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Signed export/import of path definitions (HMAC-SHA256 with panel signing key / kernel.secret).
 *
 * Import always goes through {@see RoutePathManager} (validator, allowlists, conflicts, max rows).
 */
final class RoutePathImportExport
{
    public function __construct(
        private readonly RoutePathManager $manager,
        private readonly string $signingKey,
    ) {
    }

    /**
     * @return array{payload: list<array<string, mixed>>, signature: string, version: int}
     */
    public function export(): array
    {
        $payload = [];
        foreach ($this->manager->all() as $definition) {
            $payload[] = $definition->toArray();
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return [
            'version'   => 1,
            'payload'   => $payload,
            'signature' => hash_hmac('sha256', $json, $this->signingKey),
        ];
    }

    /**
     * @param array{payload?: mixed, signature?: mixed, version?: mixed} $envelope
     *
     * @return list<RoutePathDefinition>
     */
    public function decodeAndVerify(array $envelope): array
    {
        $payload   = $envelope['payload'] ?? null;
        $signature = $envelope['signature'] ?? null;
        if (!is_array($payload) || !is_string($signature) || $signature === '') {
            throw new RuntimeException('Invalid import envelope.');
        }

        $json     = json_encode($payload, JSON_THROW_ON_ERROR);
        $expected = hash_hmac('sha256', $json, $this->signingKey);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Import signature verification failed.');
        }

        $out = [];
        foreach ($payload as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('Invalid import row.');
            }
            /* @var array<string, mixed> $row */
            $out[] = RoutePathDefinition::fromArray($row);
        }

        return $out;
    }

    /**
     * @param array{payload?: mixed, signature?: mixed, version?: mixed} $envelope
     *
     * @return int Number of rows written
     */
    public function import(array $envelope, bool $replaceAll = false): int
    {
        $definitions = $this->decodeAndVerify($envelope);

        return $this->manager->import($definitions, $replaceAll);
    }

    public function describeKeySource(): string
    {
        return sprintf('hmac-sha256 (key length %d)', strlen($this->signingKey));
    }
}
