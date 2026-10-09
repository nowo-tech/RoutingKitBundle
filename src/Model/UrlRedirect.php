<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Throwable;

use function in_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function ltrim;
use function parse_url;
use function preg_match;
use function rawurldecode;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function trim;

use const PHP_URL_PATH;

/**
 * Operator-managed HTTP redirect: an old public path → a new path or absolute URL (REQ-REDIR-001).
 *
 * Plain model, persisted through {@see UrlRedirectStorageInterface}.
 * Audit fields store the user identifier (string), never a user entity: hosts keep their own User
 * class without resolve_target_entities.
 */
class UrlRedirect
{
    /** @var list<int> Allowed redirect status codes. */
    public const STATUS_CODES = [301, 302, 307, 308];

    public const SOURCE_MAX_LENGTH = 255;

    public const TARGET_MAX_LENGTH = 500;

    public const NOTE_MAX_LENGTH = 255;

    private string $sourcePath = '';

    private string $targetUrl = '';

    private int $statusCode = 301;

    private bool $enabled = true;

    private int $hits = 0;

    private ?DateTimeImmutable $lastHitAt = null;

    private string $note = '';

    private ?DateTimeImmutable $createdAt = null;

    private ?DateTimeImmutable $updatedAt = null;

    private ?string $createdBy = null;

    private ?string $updatedBy = null;

    public function __construct(
        private ?string $id = null,
    ) {
    }

    /**
     * Same-site path target: `/x`, never protocol-relative `//host` or `/\host` (browsers treat a
     * backslash as a slash), raw or percent-encoded, and no control characters.
     */
    public static function isInternalTarget(string $target): bool
    {
        return str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && !str_contains($target, '\\')
            && preg_match('~%(?:5c|2f)~i', $target) !== 1
            && preg_match('/[\x00-\x1f\x7f]/', $target) !== 1;
    }

    /**
     * Absolute http(s) URL with a host and no whitespace / backslash / control characters.
     */
    public static function isExternalTarget(string $target): bool
    {
        return preg_match('#^https?://[^\s/\\\\?\#]+[^\s\\\\]*$#i', $target) === 1
            && preg_match('/[\x00-\x1f\x7f]/', $target) !== 1;
    }

    /**
     * `/Old-Page/?a=1` → `/Old-Page` (query and fragment dropped, trailing slash trimmed, case kept).
     * Percent-decoded, so `/podolog%C3%ADa` and `/podología` are one source.
     */
    public static function normalizePath(string $path): string
    {
        $parsed = parse_url(trim($path), PHP_URL_PATH);
        $path   = trim(rawurldecode(is_string($parsed) ? $parsed : ''));
        if ($path === '') {
            return '';
        }
        $path = '/' . ltrim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(?string $sourcePath): self
    {
        $this->sourcePath = self::normalizePath((string) $sourcePath);

        return $this;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): self
    {
        $this->targetUrl = trim((string) $targetUrl);

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function setHits(int $hits): self
    {
        $this->hits = $hits;

        return $this;
    }

    public function getLastHitAt(): ?DateTimeImmutable
    {
        return $this->lastHitAt;
    }

    public function setLastHitAt(?DateTimeImmutable $lastHitAt): self
    {
        $this->lastHitAt = $lastHitAt;

        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = trim((string) $note);

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** User identifier (e.g. e-mail) of the operator who created the row, when known. */
    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'source_path' => $this->sourcePath,
            'target_url'  => $this->targetUrl,
            'status_code' => $this->statusCode,
            'enabled'     => $this->enabled,
            'hits'        => $this->hits,
            'last_hit_at' => $this->lastHitAt?->format(DateTimeInterface::ATOM),
            'note'        => $this->note,
            'created_at'  => $this->createdAt?->format(DateTimeInterface::ATOM),
            'updated_at'  => $this->updatedAt?->format(DateTimeInterface::ATOM),
            'created_by'  => $this->createdBy,
            'updated_by'  => $this->updatedBy,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id     = $data['id'] ?? null;
        $status = $data['status_code'] ?? 301;
        $hits   = $data['hits'] ?? 0;

        return (new self(is_string($id) && $id !== '' ? $id : null))
            ->setSourcePath(self::stringOrNull($data['source_path'] ?? null))
            ->setTargetUrl(self::stringOrNull($data['target_url'] ?? null))
            ->setStatusCode(is_numeric($status) && in_array((int) $status, self::STATUS_CODES, true) ? (int) $status : 301)
            ->setEnabled(is_bool($data['enabled'] ?? null) ? $data['enabled'] : true)
            ->setHits(is_int($hits) && $hits > 0 ? $hits : 0)
            ->setNote(self::stringOrNull($data['note'] ?? null))
            ->setLastHitAt(self::date($data['last_hit_at'] ?? null))
            ->setCreatedAt(self::date($data['created_at'] ?? null))
            ->setUpdatedAt(self::date($data['updated_at'] ?? null))
            ->setCreatedBy(self::stringOrNull($data['created_by'] ?? null))
            ->setUpdatedBy(self::stringOrNull($data['updated_by'] ?? null));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
