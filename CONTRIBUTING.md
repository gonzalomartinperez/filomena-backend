# Maintenance guide

Filomena is a historical portfolio reference maintained by [Gonzalo Martin Perez](https://github.com/gonzalomartinperez). The public code documents the original implementation and team achievement. It is not an invitation to change institutional deployments or a promise of community support.

## Scope

Documentation corrections and reproducible bug reports are welcome as suggestions. The maintainer develops and reviews changes; unsolicited feature work, architecture migrations and dependency modernization should not be assumed to be in scope. Publishing the source does not grant a reuse license; see the README's authorship and licensing statement.

Before proposing a correction, read the [README](README.md) and [security policy](SECURITY.md). Describe the concrete problem, affected revision, expected behavior and smallest reproduction using synthetic records. Do not attach real student information, exam responses, import workbooks, credentials, logs with tokens or environment files. Report security issues privately.

## Snapshot and branch policy

Application source and dependency versions are retained as historical university-project evidence. Automatic dependency updates and routine modernization are outside this snapshot's maintenance scope. Security advisories remain relevant; preserving the snapshot does not imply that its dependencies are currently secure or suitable for a new deployment.

Keep `main` and `develop` as the permanent branches. Use a documentation task branch and PR into `develop`, then a reviewed documentation promotion into `main`; synchronize the branches afterward. This flow is for documentation corrections and does not authorize application or dependency changes.

## Working safely

Use a separate branch and a local environment you are authorized to use. Start from the documented example environment files, keep credentials outside Git, and review Docker port bindings before starting a stack. Do not run reset commands against data that must be retained.

For documentation-only edits, check relative links, image paths, code examples and any Mermaid diagram. Describe the original implementation accurately; distinguish case-study outcomes from tests executed for the change. Preserve team attribution and the public-snapshot boundaries.

## Available validation

```bash
composer install
npm ci
./vendor/bin/pint --test
php artisan test
npm run build
```

The PHP checks require a configured disposable local environment. See the README before migrations or seeding; never run them against institutional data.

Run checks appropriate to the files changed and report actual results and limitations. Do not present historical platform metrics as a benchmark reproduced by these commands. A dependency audit describes the advisory database at the time of execution and does not prove the absence of vulnerabilities.

## Change descriptions

Use a clear English title and explain the concrete correction, why it matters, verification performed and relevant limits. Keep documentation changes separate from functional changes. Do not propose routine dependency-update PRs as part of documentation maintenance. A bot author or passing checks does not authorize changes to the preserved implementation.

No repository automation or deployment authority is granted by this guide.
