# Changelog: Laravel Admin Dashboard Parity Project

## [Unreleased] - Project Initialization

### Added
- `PROJECT.md` - Project overview and gap analysis
- `SPEC.md` - Detailed specification with 39 file requirements
- `TICKETS.md` - 42 implementation tickets across 8 phases
- `HANDOFF.md` - Execution schedule and dependency graph
- `DECISIONS.md` - 20 key architectural decisions
- `RISKS.md` - 13 identified risks with mitigations

### Analysis Completed
- Compared 3 projects: Next.js 16, TanStack Start, Laravel + Inertia
- Identified 16 missing dashboard screens + 4 legacy variants
- Identified missing sidebar navigation (27 items vs 1)
- Identified missing header controls (5 components)
- Identified missing ThemeBootScript for SSR theme hydration
- Identified missing preference cookie sync

### Decisions Made
- Full feature parity (not minimal port)
- ThemeBootScript runs in `<head>` before React hydration
- Preferences store uses client cookies with `isSynced` flag
- Navigation active state uses `usePage().url.split('?')[0]`
- `@inertiajs/react` Link for all navigation
- No `"use client"` directives (Inertia SSR)
- Sidebar width matches references exactly
- Default sidebar variant: "sidebar" (not "inset")
- Colocation pattern for dashboard screens
- Legacy screens in `(legacy)` directory
- Auth dropdown with 4 links (v1/v2)
- Font selector ported from references
- SearchDialog (cmd+k) ported
- Single DashboardController with expanded screens array
- Theme preset CSS files already present
- Shadcn "new-york" style retained (Radix-based)
- Static user data for AccountSwitcher
- NavDocuments/NavSecondary commented out per references
- Verification: build + lint + typecheck + manual test

### Risks Identified (13 total)
- 5 High: Theme hydration, cookie sync race, active state, missing UI components, charts/calendar
- 5 Medium: Mobile sidebar, search shortcut, static account switcher, legacy screens, TS strict
- 3 Low: Font loading, Tailwind v4 CSS vars, Inertia shared data