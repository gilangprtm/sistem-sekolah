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
