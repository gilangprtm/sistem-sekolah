# Key Decisions: Laravel Admin Dashboard Parity

## Decision Log

### DEC-001: Port Strategy - Full Feature Parity
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Laravel project is a starter kit skeleton, not a full port.
**Decision**: Implement full feature parity with Next.js/TanStack references (all 17 screens + 4 legacy + full layout controls) rather than minimal port.
**Rationale**: User explicitly wants "same layout with the others". The references are the source of truth.

### DEC-002: Theme Boot Script - Client-Side Cookie Reading
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Next.js uses `ThemeBootScript` running in `<head>` to set `data-*` attributes before paint.
**Decision**: Create equivalent `theme-boot.tsx` that reads cookies via `document.cookie` and sets `document.documentElement.dataset` attributes.
**Rationale**: Prevents flash of wrong theme on initial load. Inertia SSR renders initial HTML but client must apply theme before hydration.

### DEC-003: Preferences Store - Cookie Persistence with isSynced
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References use `isSynced` flag in Zustand store to track when client-side preferences have been read from cookies.
**Decision**: Maintain existing Zustand store with cookie read/write. Use `isSynced` to prevent hydration mismatches for layout-critical prefs.
**Rationale**: Layout-critical prefs (sidebar_variant, sidebar_collapsible) must not use localStorage (no SSR). Client cookies work for SSR hydration.

### DEC-004: Navigation Active State - usePage().url
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Next.js uses `usePathname()`, TanStack uses `useRouterState()`. Laravel/Inertia has `usePage()`.
**Decision**: Use `usePage().url` (split by '?') for active state detection in NavMain.
**Rationale**: Inertia's `usePage()` provides current URL. Must handle query params by splitting.

### DEC-005: Link Component - @inertiajs/react Link
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Next.js uses `next/link`, TanStack uses `@tanstack/react-router Link`.
**Decision**: Use `@inertiajs/react` `Link` component for all navigation. Use `prefetch` prop for hover prefetching.
**Rationale**: Inertia's Link handles SPA navigation with server-side rendering support.

### DEC-006: No "use client" Directives
**Date**: 2026-08-01
**Status**: Accepted
**Context**: TanStack reference explicitly forbids "use client" (SSR only, no RSC).
**Decision**: Do not add `"use client"` directives. Inertia SSR handles client-side interactivity automatically.
**Rationale**: Inertia components render on server and hydrate on client. No need for explicit client directives.

### DEC-007: Sidebar Width - CSS Variable Matching References
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References use `--sidebar-width: calc(var(--spacing) * 68)` (16rem equivalent).
**Decision**: Use exact same CSS variable value in `SidebarProvider` style prop.
**Rationale**: Ensures visual consistency with reference implementations.

### DEC-008: Sidebar Variant Default - "sidebar" (not "inset")
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Laravel starter kit hardcodes `variant="inset"`. References default to "sidebar".
**Decision**: Default to "sidebar" variant, controlled by preferences. "inset" is an option in LayoutControls.
**Rationale**: Matches reference behavior. User can switch via LayoutControls.

### DEC-009: Dashboard Screen Structure - Colocation Pattern
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References use colocation: `page.tsx` + `_components/` per screen.
**Decision**: Port exact same structure: `resources/js/pages/dashboard/<screen>/page.tsx` + `_components/`.
**Rationale**: Maintains consistency with reference architecture. Easier to maintain and navigate.

### DEC-010: Legacy Screens - Separate (legacy) Directory
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References group legacy screens under `(legacy)` route group.
**Decision**: Create `resources/js/pages/dashboard/(legacy)/<screen>/` with same colocation pattern.
**Rationale**: Matches reference structure. Inertia routes will handle the flat URL structure.

### DEC-011: Auth in Sidebar - 4 Links (v1/v2)
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References include Authentication dropdown with 4 links (login/register v1/v2).
**Decision**: Include all 4 auth links in sidebar navigation with `newTab: true` (external routes).
**Rationale**: Auth routes are handled by Laravel Fortify, separate from Inertia pages. Opening in new tab preserves Inertia session.

### DEC-012: Font Options - Port from References
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References have font selector in LayoutControls with multiple font options.
**Decision**: Port `fontOptions` from `lib/fonts/registry.ts` and include font selector in LayoutControls.
**Rationale**: Full parity requires font switching capability.

### DEC-013: SearchDialog - Command Palette
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References include cmd+k search dialog for quick navigation.
**Decision**: Port SearchDialog component. Use `@radix-ui/react-dialog` + `cmdk` package.
**Rationale**: Core UX feature of the admin dashboard.

### DEC-014: Route Registration - Single DashboardController
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Laravel uses single `DashboardController@index` with `$screen` parameter.
**Decision**: Keep single controller, expand `$screens` array to include all 21 screens.
**Rationale**: Existing pattern works. Simpler than 21 separate routes/controllers.

### DEC-015: Theme Preset CSS - Already Present
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Laravel already has 4 theme preset CSS files (brutalist, soft-pop, tangerine, neutral default).
**Decision**: Use existing preset files. Ensure they're loaded based on `data-theme-preset` attribute.
**Rationale**: Files are identical to references. No work needed.

### DEC-016: Shadcn Style - "new-york" (Radix) vs "base-nova"/"radix-nova"
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Laravel uses `style: "new-york"` (Radix). Next.js uses `radix-nova`, TanStack uses `base-nova`.
**Decision**: Keep Laravel's "new-york" style. It's a shadcn configuration difference, not a functional one. Components are Radix-based in all three.
**Rationale**: "new-york" is the Radix-based style. Visual differences are minimal. Rewiring to "radix-nova" would require regenerating all UI components.

### DEC-017: AccountSwitcher - Static User Data
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References use static `users` array from `data/users.ts`.
**Decision**: Port static user data. Laravel auth user can be integrated later if needed.
**Rationale**: Parity first. Dynamic account switching requires backend integration beyond scope.

### DEC-018: SidebarSupportCard - Static Content
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References show GitHub stars/sponsor links statically.
**Decision**: Port static content. GitHub stars would need API integration (out of scope).
**Rationale**: Visual parity achievable with static content.

### DEC-019: NavDocuments/NavSecondary - Commented but Available
**Date**: 2026-08-01
**Status**: Accepted
**Context**: References have these components but comment them out in AppSidebar.
**Decision**: Create components but keep commented out in AppSidebar (matching references exactly).
**Rationale**: Exact parity means matching what's in the reference code, not just what's visible.

### DEC-020: Verification - Build + Lint + Typecheck
**Date**: 2026-08-01
**Status**: Accepted
**Context**: Laravel project uses `npm run build`, `npm run lint`, `npm run types:check`.
**Decision**: All 42 tickets must pass these three checks plus manual screen verification.
**Rationale**: Ensures code quality matches reference standards.