# AGENTS.md — Sistem Sekolah

## Scope

This file contains repository-local working rules for the Sistem Sekolah application. KITAB remains the authoritative source for universal engineering governance and lifecycle rules.

Relevant KITAB references:

- `STD-023` — Universal Application Engineering Standard
- `SOP-001` — Universal Application Feature Delivery
- `WF-001` — Universal Application Feature Delivery Workflow
- `SK-APP-001` — Application Codebase Inspection
- `SK-APP-002` — Application Quality Gate
- `TPL-APP-001` — Feature Delivery Record

Do not copy or override KITAB governance here. Add only project-specific constraints and verified commands.

## Project Context

- Application: Sistem Sekolah
- Backend: Laravel 13, PHP 8.3+
- Frontend: React 19, Inertia 3, TypeScript
- UI: Tailwind CSS 4 and shadcn/ui
- Database: PostgreSQL
- Supporting infrastructure: Redis, Nginx, PHP-FPM, Docker
- Authorization: Laravel Fortify, Sanctum, and Spatie Permission

Before changing code, inspect the current repository, relevant project files, nearby tests, and current revision. Do not infer APIs, symbols, dependencies, or conventions that have not been verified in the repository.

## Domain and Database Rules

- Keep domain ownership explicit before adding or changing an entity.
- For new domain-owned schema, use the project convention `m_<domain>_<entity>` for master/reference data and `tr_<domain>_<entity>` for transactional data, after confirming compatibility with the current project schema.
- Use explicit domain foreign keys when a generic name could be ambiguous, for example `inventory_category_id`.
- Keep Laravel, package, and system tables compatible with their package contracts; do not rename them by convention alone.
- Schema changes require migrations. Inspect existing migrations, foreign keys, consumers, factories, seeders, and tests before editing.
- Never run `migrate:fresh` against shared or production data. Development reset requires explicit authorization and must be followed by seed and schema verification.

Current Inventory namespace examples:

```text
m_inventory_categories
m_inventory_types
m_inventory_tangible_asset_types
m_inventory_intangible_asset_types
tr_inventory_items
tr_inventory_units
inventory_category_id
```

## Authorization and Contracts

- Permission names must carry domain context when the resource is domain-owned, using `<domain>.<resource>.<action>`.
- Inventory category permissions use `inventory.category.*`, including `inventory.category.assign` where assignment is controlled.
- Enforce authorization server-side at route/API/controller/policy/service boundaries. Frontend checks only control visibility and user experience.
- When changing schema, permission, route, API, event, model, or Inertia prop names, audit all consumers, validation, seeders, factories, and regression tests.
- Preserve established URL and Inertia contracts unless a compatibility or deprecation path is explicitly designed.

## UI and UX

- Design forms around the user's decision order, not database column order.
- Keep dependent fields grouped and clear stale dependent state when context changes.
- Verify responsive behavior for desktop and mobile layouts.
- Keep labels, errors, loading states, keyboard behavior, and touch targets understandable and accessible.
- Do not claim visual served-surface verification when the surface was not actually observed.

## Development Workflow

Use this sequence for material changes:

```text
inspect → design → implement → test → review → commit
```

Before editing:

1. Check `git status` and current branch.
2. Read relevant project rules and source definitions/usages.
3. Define included and excluded scope, acceptance criteria, and verification plan.
4. Identify schema, authorization, contract, and consumer impact.

During editing:

- Keep the change bounded and avoid drive-by refactors.
- Do not overwrite unrelated working-tree changes.
- Do not read, print, commit, or expose secrets, credentials, tokens, or `.env` values.
- Do not commit generated output unless repository conventions require it.
- Treat editing a migration/configuration as artifact work; applying it to production is a separate operational action.

## Quality Gates

Run the gates relevant to the changed surface. The baseline project commands are:

```bash
npm run lint:check
npm run format:check
npm run types:check
npm run build
composer test
```

In this repository, `composer test` is an aggregate wrapper that clears config, runs Pint, runs PHPStan, and runs the Artisan test suite; it is not an independent test-only command. Run the individual commands when isolating a failure. Commands may be `PARTIAL` or `UNKNOWN` when required dependencies, generated files, services, or environment access are unavailable.

For PHP/schema changes, also run the applicable project checks, such as:

```bash
vendor/bin/pint --test
php artisan migrate --pretend --no-interaction
php artisan migrate:status --no-interaction
php artisan test
```

For a focused change, run focused tests first and then the relevant full suite. Record commands and observed results. Classify each gate as `PASS`, `PARTIAL`, `FAIL`, or `UNKNOWN`; do not convert a missing observation into a pass.

## Git and Delivery

- Do not force-push, rewrite history, or delete branches unless explicitly authorized.
- Review `git diff --check` and `git status` before committing.
- Use a focused commit message that describes the change.
- A commit or push is delivery evidence, not proof that release or production deployment is approved.
- Do not deploy, publish externally, migrate production, or change external runtime state as a side effect of ordinary implementation work.

## Definition of Done

A change is complete only when:

- scope, target, and owner are clear;
- relevant domain, schema, contracts, authorization, and consumers are audited;
- implementation is bounded and source changes are reviewable;
- acceptance criteria are verified with observed evidence;
- relevant tests and quality gates pass or limitations are documented;
- known limitations, unverified surfaces, and follow-up work are recorded;
- working-tree and resulting revision are clear;
- commit/push status is reported accurately when applicable.

## Reporting

Report separately:

```text
implemented
verified
partial or unknown
intentionally excluded
blocked
resulting revision / commit
```

Do not claim production readiness, browser verification, deployment success, or absence of all defects without direct evidence.

## School Assistant Rules

- Laravel is the assistant's security and data boundary. The LLM may select only server-registered, read-only capabilities; it must never receive database credentials, arbitrary SQL, arbitrary PHP, or unrestricted HTTP execution.
- Keep assistant capability authorization server-side and permission-scoped. Revalidate the user permission and every tool name, field, filter, sort field, sort direction, page, and page size in Laravel before querying.
- Prefer broad, composable read-only capabilities such as `inventory_query` over one tool per natural-language question. Capability contracts must expose only allowlisted fields and bounded pagination; implementation queries remain explicit Eloquent code.
- Keep tool execution bounded per request: stop on a final provider message, provider/tool failure, request timeout, or repeated identical tool call. Do not limit the number of user messages; do prevent unbounded internal loops.
- Treat provider tool schemas as JSON Schema. Empty object properties must serialize as `{}`, not `[]`; add a provider contract test when changing a tool schema.
- Normalize or safely render LLM Markdown before displaying it. Never inject unsanitized provider HTML into the page. The chat widget uses `w-fit` with user bubbles aligned right and assistant bubbles aligned left, each bounded by a responsive maximum width.
- Keep chat history ephemeral and bounded by the request contract; do not add conversation persistence without a separate approved scope.
- For assistant changes, run the focused assistant PHPUnit test, changed-file lint/type checks, `npm run build`, and `git diff --check`; distinguish source/build evidence from served-domain evidence.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
