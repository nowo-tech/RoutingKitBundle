<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;

use function in_array;
use function mb_strlen;
use function preg_match;
use function str_starts_with;
use function trim;

/**
 * Panel-side rules for a redirect row (REQ-REDIR-006). Returns translation keys
 * (domain NowoRoutingKitBundle) per form field; an empty array means valid.
 *
 * Order: format → protected source → live page → self-loop → chain → duplicate source.
 */
final class UrlRedirectValidator
{
    public function __construct(
        private readonly UrlRedirectStorageInterface $storage,
        private readonly ProtectedPaths $protectedPaths,
        private readonly LivePublicPaths $livePaths,
    ) {
    }

    /**
     * @param string $rawSource as typed by the operator (validated before normalization)
     *
     * @return array<string, string> field name (`source_path`, `target_url`, `status_code`, `note`) => message key
     */
    public function validate(string $rawSource, string $rawTarget, int $statusCode, string $note = '', ?string $currentId = null): array
    {
        $errors = $this->validateFormat(trim($rawSource), trim($rawTarget), $statusCode, $note);
        if ($errors !== []) {
            return $errors;
        }

        $source     = UrlRedirect::normalizePath($rawSource);
        $target     = trim($rawTarget);
        $targetPath = str_starts_with($target, '/') ? UrlRedirect::normalizePath($target) : null;

        $error = match (true) {
            $this->protectedPaths->isProtected($source)                           => ['source_path', 'redirects.error.source_protected'],
            $this->livePaths->isLive($source)                                     => ['source_path', 'redirects.error.source_live_page'],
            $targetPath === $source                                               => ['target_url', 'redirects.error.target_loop'],
            $targetPath !== null && $this->isOtherSource($targetPath, $currentId) => ['target_url', 'redirects.error.target_chain'],
            $this->isOtherSource($source, $currentId)                             => ['source_path', 'redirects.error.source_duplicate'],
            default                                                               => null,
        };

        return $error === null ? [] : [$error[0] => $error[1]];
    }

    /**
     * @return array<string, string>
     */
    private function validateFormat(string $source, string $target, int $statusCode, string $note): array
    {
        $errors = [];
        if ($source === '' || mb_strlen($source) > UrlRedirect::SOURCE_MAX_LENGTH || preg_match('~^/[^\s?#\\\\]*$~u', $source) !== 1
            || preg_match('/[\x00-\x1f\x7f]/', $source) === 1
        ) {
            $errors['source_path'] = 'redirects.error.source_invalid';
        }
        if ($target === '' || mb_strlen($target) > UrlRedirect::TARGET_MAX_LENGTH
            || !UrlRedirect::isInternalTarget($target) && !UrlRedirect::isExternalTarget($target)
        ) {
            $errors['target_url'] = 'redirects.error.target_invalid';
        }
        if (!in_array($statusCode, UrlRedirect::STATUS_CODES, true)) {
            $errors['status_code'] = 'redirects.error.status_invalid';
        }
        if (mb_strlen($note) > UrlRedirect::NOTE_MAX_LENGTH) {
            $errors['note'] = 'redirects.error.note_too_long';
        }

        return $errors;
    }

    /**
     * Another row (not the one being edited) already redirects $path.
     */
    private function isOtherSource(string $path, ?string $currentId): bool
    {
        $other = $this->storage->findBySource($path);

        return $other instanceof UrlRedirect && $other->getId() !== $currentId;
    }
}
