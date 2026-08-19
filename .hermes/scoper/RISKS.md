# Risk Register: Laravel Admin Dashboard Parity

## High Risks

### RISK-001: Theme Boot Script Hydration Mismatch
**Probability**: High
**Impact**: High
**Description**: Server-rendered HTML has different `data-*` attributes than client-applied theme, causing React hydration mismatch.
**Mitigation**: 
- Use `suppressHydrationWarning` on `<html>` element
- Ensure ThemeBootScript runs before any React code
- Server provides initial defaults matching `PREFERENCE_DEFAULTS`
**Contingency**: If mismatches persist, move all theme prefs to Inertia shared data and render server-side only.

### RISK-002: Preferences Store Cookie Sync Race Condition
**Probability**: Medium
**Impact**: High
**Description**: `isSynced` flag may not correctly track hydration state, causing sidebar to render with wrong variant/collapsible on first paint.
**Mitigation**:
- Read cookies synchronously in store initialization
- Set `isSynced = true` immediately after cookie read
- Use `useSyncExternalStore` for subscription
**Contingency**: Remove `isSynced` logic, always use server-provided defaults for layout-critical prefs.

### RISK-003: NavMain Active State with Inertia URLs
**Probability**: High
**Impact**: Medium
**Description**: `usePage().url` includes full URL with query params. Active matching with `startsWith()` may fail for nested routes or with query params.
**Mitigation**:
- Split URL at `?` before matching: `usePage().url.split('?')[0]`
- Test with all route patterns including legacy routes
- Add unit tests for active state logic
**Contingency**: Use `window.location.pathname` as fallback.

### RISK-004: Missing UI Components
**Probability**: Medium
**Impact**: High
**Description**: Some shadcn UI components used in references may not exist in Laravel's `components/ui/` directory.
**Mitigation**:
- Audit all components used in ported files before starting Phase 2
- Generate missing components with `shadcn add` using `style: "new-york"`
- Test each component renders correctly
**Contingency**: Manually port missing component implementations from references.

### RISK-005: Chart/Calendar Dependencies
**Probability**: Medium
**Impact**: Medium
**Description**: Analytics dashboard uses Recharts, Calendar uses FullCalendar. These may need additional configuration for Inertia/Vite.
**Mitigation**:
- Verify `recharts` and `@fullcalendar/react` work in Vite + Inertia
- Check for SSR issues with FullCalendar
**Contingency**: Use dynamic imports for heavy chart components.

## Medium Risks

### RISK-006: Mobile/Offcanvas Sidebar Behavior
**Probability**: Medium
**Impact**: Medium
**Description**: Sheet-based mobile sidebar may behave differently with Inertia's page transitions.
**Mitigation**:
- Test mobile sidebar open/close during page navigation
- Ensure `SidebarProvider` state resets correctly on page change
**Contingency**: Add page-transition handling to close mobile sidebar.

### RISK-007: SearchDialog cmd+k Conflict
**Probability**: Low
**Impact**: Medium
**Description**: Global cmd+k listener may conflict with browser shortcuts or other components.
**Mitigation**:
- Use `keydown` event with `metaKey || ctrlKey` check
- Prevent default only when dialog should open
- Test on Mac (cmd) and Windows/Linux (ctrl)
**Contingency**: Make shortcut configurable or disableable.

### RISK-008: AccountSwitcher - Static vs Dynamic
**Probability**: Low
**Impact**: Low
**Description**: Static user data doesn't reflect actual authenticated user.
**Mitigation**:
- Accept current approach for parity
- Future: integrate with Laravel auth via Inertia shared data
**Contingency**: N/A (acceptable for parity).

### RISK-009: Legacy Screen Component Differences
**Probability**: Medium
**Impact**: Low
**Description**: Legacy screens may use different component patterns than current screens.
**Mitigation**:
- Port each legacy screen individually, referencing exact Next.js legacy structure
- Don't assume shared components between current and legacy
**Contingency**: Accept minor visual differences if legacy components are too divergent.

### RISK-010: TypeScript Strict Mode Errors
**Probability**: High
**Impact**: Medium
**Description**: Ported code may have type errors due to Inertia vs Next.js/TanStack type differences.
**Mitigation**:
- Run `npm run types:check` after each task
- Use type assertions sparingly
- Fix types at source rather than suppressing
**Contingency**: Adjust tsconfig.json if needed (loosen specific rules).

## Low Risks

### RISK-011: Font Loading in Vite
**Probability**: Low
**Impact**: Low
**Description**: `@tailwindcss/vite` font loading may differ from Next.js font optimization.
**Mitigation**: Use existing `bunny()` font loader in vite.config.ts.

### RISK-012: Tailwind v4 CSS Variables
**Probability**: Low
**Impact**: Low
**Description**: Theme preset CSS variables may not apply correctly with Tailwind v4's new architecture.
**Mitigation**: Verify `data-theme-preset` attribute triggers correct CSS variable overrides.

### RISK-013: Inertia Shared Data for Preferences
**Probability**: Low
**Impact**: Low
**Description**: Server may need to provide initial preferences via Inertia shared data for SSR.
**Mitigation**: Add middleware to share `PREFERENCE_DEFAULTS` merged with user cookies.

## Risk Summary

| Risk Level | Count |
|------------|-------|
| High | 5 |
| Medium | 5 |
| Low | 3 |
| **Total** | **13** |

## Monitoring

- Run `npm run build` and `npm run types:check` after every task
- Manual test all 21 screens after Phase 5-7 completion
- Test mobile/desktop/offcanvas at each layout change
- Verify cookie persistence across browser sessions