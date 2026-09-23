---
name: school-assistant
description: Build and verify the school assistant capability safely.
version: 0.1.0
author: Gilang Pratama, Hermes Agent
license: MIT
platforms: [linux]
metadata:
  hermes:
    tags: [laravel, assistant, llm, tools, inventory]
    related_skills: []
---

# School Assistant Skill

Use this skill when changing or debugging the Sistem Sekolah assistant widget, Laravel gateway, provider adapter, or read-only inventory capabilities. Keep Laravel as the security boundary and treat the provider as an untrusted reasoning service.

## When to Use

- Add or change an assistant capability or tool contract.
- Debug provider, tool-call, response-format, or chat-widget behavior.
- Verify assistant authorization, ephemeral history, or mobile chat presentation.

Do not use this skill to grant arbitrary SQL, code execution, database credentials, or unrestricted provider access.

## Prerequisites

- Read repository `AGENTS.md` and inspect the current working tree.
- Check KITAB through `kitab-operator` before material changes.
- Identify the current provider adapter, controller, widget, config, and focused tests.
- Keep credentials in environment/config only; never print or commit them.

## Procedure

1. Inspect routes, controller, provider client, widget, model relations, schema, and nearby tests. Record the bounded scope before editing.
2. Keep capability contracts composable. Prefer one broad read-only query capability with allowlisted fields, filters, sorting, grouping, and bounded pagination over one tool for every wording of a question.
3. Validate every provider argument in Laravel. Use explicit Eloquent queries; never interpolate provider input as SQL, PHP, URLs, or model class names.
4. Scope capabilities by the authenticated user's server-side permission. Recheck authorization during every tool execution.
5. Serialize JSON Schema correctly. An empty object must be encoded as `{}`; add a regression test for every changed provider schema.
6. Process tool calls until a final provider message, but stop on provider/tool failure, request timeout, or repeated identical tool signature. User conversation count is not the loop limit.
7. Keep history ephemeral and within the request validation contract. Do not add persistence without explicit scope.
8. Render provider output safely. Normalize unsupported Markdown such as `**bold**` before display or use a sanitized restricted renderer; never inject raw provider HTML.
9. Keep mobile bubbles content-sized: user messages use `ml-auto w-fit`, assistant messages use `mr-auto w-fit`, and both have a responsive maximum width.
10. Run focused tests first, then changed-file lint/type checks, `npm run build`, `php artisan optimize:clear` when config/routes changed, and `git diff --check`.

## Capability Contract

For `inventory_query`, keep these controls explicit and allowlisted:

- fields: permitted inventory columns only;
- filters: known searchable fields and typed values only;
- sort field: permitted columns only;
- direction: `asc` or `desc`;
- page: minimum 1;
- per-page: bounded maximum;
- output: selected data plus pagination metadata only.

Use aggregation or server-side grouping when the result set would exceed the provider context. Do not send unrelated sensitive columns merely because the LLM can process them.

## Pitfalls

- A provider HTTP 503 can wrap an invalid tool schema; inspect the safe provider error before blaming connectivity.
- PHP empty arrays can serialize JSON Schema `properties` as `[]`, which providers reject when they require an object.
- A successful provider response with empty `content` may be a tool-call intermediate message, not a final answer.
- Markdown markers appearing in the browser mean the served widget is rendering plain text or serving an older bundle; verify both source and served asset separately.
- Passing local tests does not prove the dev domain uses the same checkout, config, build, or PHP process.
- Do not add a generic `category_id`; the inventory schema uses `inventory_category_id`.

## Verification

Required evidence for an assistant change:

```text
php artisan test tests/Feature/AssistantTest.php
npx eslint <changed-widget-or-page>
npx tsc --noEmit
npm run build
git diff --check
```

For provider/tool changes, also run a safe direct contract smoke test with the real credential hidden and record only status, model, response shape, and sanitized error code. Report source/build and served-domain observations separately.
