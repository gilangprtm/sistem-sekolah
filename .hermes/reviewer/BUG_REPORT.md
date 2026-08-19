# Bug Report

## 2026-08-02 — Phase 3 Review

No bugs discovered. All defects noted are outside AC scope or are observations only.

### Observation: `"use client"` leftover in `nav-user.tsx`
- **File:** `resources/js/components/dashboard/nav-user.tsx:1`
- **Description:** Next.js `"use client"` directive present. Harmless in Vite/Inertia but inconsistent with `nav-documents.tsx` which had it removed during Phase 2.
- **Severity:** Low
- **Action:** Optional cleanup. Not a functional defect.

## 2026-08-02 — Phase 4 Review

No bugs discovered. No out-of-scope defects found during Phase 4 review.

---

## BUG-003: Auth v1/v2 Sidebar Links Return 404

- **Bug ID:** BUG-003
- **Title:** Authentication sidebar links (/auth/v1/login, /auth/v2/login, /auth/v1/register, /auth/v2/register) return 404
- **Severity:** High
- **Related Task:** TASK-004 (Sidebar Navigation Data), TASK-041 (Route Registration Verification)
- **Description:** The sidebar navigation contains 4 Authentication links that open in new tab. The reference implementations (Next.js, TanStack) have actual pages at those paths. Laravel has no equivalent routes — only Fortify's `/login` and `/register` exist. Clicking any auth v1/v2 link produces a 404 response.
- **Evidence:**
  - `resources/js/navigation/sidebar/sidebar-items.ts:185-204` — links to `/auth/v1/login`, `/auth/v2/login`, `/auth/v1/register`, `/auth/v2/register` with `newTab: true`
  - `php artisan route:list` — no routes matching `/auth/v1/*` or `/auth/v2/*` registered
  - Next.js reference has pages: `src/app/(main)/auth/v1/login/page.tsx`, `src/app/(main)/auth/v1/register/page.tsx`, `src/app/(main)/auth/v2/login/page.tsx`, `src/app/(main)/auth/v2/register/page.tsx`
  - Laravel `resources/js/pages/auth/` — only `login.tsx`, `register.tsx` (no v1/v2 variants)
- **Reproduction Steps:**
  1. Navigate to `/dashboard` (authenticated)
  2. In sidebar, expand "Authentication" group
  3. Click "Login v1", "Login v2", "Register v1", or "Register v2"
  4. New tab opens → 404 Not Found
- **Expected Behavior:** Auth v1/v2 links open working pages matching the reference implementations' authentication UI variants
- **Actual Behavior:** All 4 links return 404 — routes not registered, pages not created
- **Affected Modules:**
  - `resources/js/navigation/sidebar/sidebar-items.ts`
  - `routes/web.php` (missing auth v1/v2 routes)
  - `resources/js/pages/auth/` (missing v1/v2 page variants)
