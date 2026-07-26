## AI contribution guidelines (Nowo Symfony bundle)

Use this when suggesting code, tests, documentation, or CI changes for this repository.

### Scope

- This is a **Symfony bundle** published under `nowo-tech/*` on Packagist (`nowo-tech/routing-kit-bundle`).
- Respect the **PHP** and **Symfony** version ranges declared in `composer.json`.
- Prefer **PHP 8 attributes** for configuration and metadata. Do not introduce `doctrine/annotations` for new code.

### Code

- Follow **PSR-12** and project conventions in `.php-cs-fixer.dist.php`.
- Use **strict comparison** (`===`) where appropriate.
- Keep changes **minimal** and consistent with existing patterns in `src/` and `tests/`.
- Align with `composer cs-check`, `composer phpstan`, and `composer test` expectations.
- Every hand-written PHP file must use `declare(strict_types=1);`.

### Documentation

- User-facing documentation is **English** under `docs/` per Nowo bundle standards.
- Only `README.md` (plus `CODE_OF_CONDUCT.md` / `LICENSE`) at repository root for markdown.
- When changing production `src/` behaviour, update `specs/001-baseline/` and integrator docs as needed.

### Security

- Never commit real secrets, production password hashes, or bypass tokens.
- Panel password hashes belong in env/config placeholders only; see `docs/SECURITY.md`.

### Git commits (REQ-GIT-001)

- Never add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.
- Commit messages list human authors only; tooling attribution belongs in PR descriptions or release notes.

### Tests

- Add or update tests for new behaviour; keep PHP line coverage at 100% (see README and CI).
