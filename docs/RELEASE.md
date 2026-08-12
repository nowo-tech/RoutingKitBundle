# Release

Current stable target: **v1.4.0**.

## Checklist

1. `make release-check` (Cursor trailer check, open-PRs, composer-sync, cs-fix, cs-check, rector-dry, phpstan, validate-translations, coverage-check, demos).
2. Update [CHANGELOG.md](CHANGELOG.md) with user-facing notes (Keep a Changelog + SemVer).
3. Update [UPGRADING.md](UPGRADING.md) when there are migration steps or requirement changes.
4. Commit the release on the default branch (`main`).
5. Before pushing, run `make check-no-cursor-coauthor` (REQ-GIT-001). If a Cursor trailer was injected, strip it before push.
6. Tag the commit `vX.Y.Z`.
7. Push the branch and the tag to `git@github.com:nowo-tech/RoutingKitBundle.git` — `.github/workflows/release.yml` creates the GitHub Release from the tag + changelog entry.
8. Confirm [Packagist](https://packagist.org/packages/nowo-tech/routing-kit-bundle) picks up the tag (submit the GitHub repo once if the package is new).

## Example: v1.4.0

```bash
git tag -a v1.4.0 -m "Release v1.4.0: Symfony forms for panel index actions (REQ-TWIG-005)."
git push origin main
git push origin v1.4.0
```

## Example: v1.3.1

```bash
git tag -a v1.3.1 -m "Release v1.3.1: sync composer.lock content-hash for CI validate."
git push origin main
git push origin v1.3.1
```

## Example: v1.3.0

```bash
git tag -a v1.3.0 -m "Release v1.3.0: FormKit panel form and UiKit pagination."
git push origin main
git push origin v1.3.0
```

## Versioning

Follow semantic versioning. Breaking changes to config keys, route names, or public interfaces require a major bump and an [UPGRADING.md](UPGRADING.md) section.
