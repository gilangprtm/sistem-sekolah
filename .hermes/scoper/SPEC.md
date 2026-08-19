# Specification: Laravel Admin Dashboard Parity

## Overview

This specification defines the work required to bring the Laravel + Inertia + React admin dashboard to feature parity with the Next.js 16 and TanStack Start reference implementations.

## Architecture Principles

- **Colocation-based**: Feature code lives with its route (Inertia page component)
- **Preference-driven**: All layout/theme state persisted in cookies, hydrated on client
- **Server-aware**: SSR-compatible, no `"use client"` needed (Inertia SSR)
- **Component reuse**: Use existing shadcn UI components, don't duplicate

## File Structure (Target)

```
resources/js/
├── app.tsx                          # Entry - add ThemeBootScript equivalent
├── layouts/
│   ├── app-layout.tsx               # Main layout wrapper
│   ├── app/
│   │   ├── app-sidebar-layout.tsx   # Dashboard layout (replace with full version)
│   │   ├── app-header-layout.tsx    # Keep
│   │   └── app-sidebar-header.tsx   # Replace with full header
│   ├── auth/
│   │   └── auth-layout.tsx          # Keep
│   └── settings/
│       └── layout.tsx               # Keep
├── components/
│   ├── dashboard/                   # NEW - port from references
│   │   ├── app-sidebar.tsx          # Full sidebar with NavMain
│   │   ├── layout-controls.tsx      # Port from references
│   │   ├── theme-switcher.tsx       # Port from references
│   │   ├── search-dialog.tsx        # Port from references
│   │   ├── account-switcher.tsx     # Port from references
│   │   ├── sidebar-support-card.tsx # Port from references
│   │   ├── nav-main.tsx             # Port from references
│   │   ├── nav-user.tsx             # Port from references
│   │   ├── nav-documents.tsx        # Port from references
│   │   ├── nav-secondary.tsx        # Port from references
│   │   ├── nav-footer.tsx           # Update for support card
│   │   └── app-sidebar-header.tsx   # Full header with controls
│   ├── ui/                          # Existing - verify all needed components exist
│   └── ...existing components...
├── pages/
│   ├── dashboard/                   # NEW - 17 screens + 4 legacy
│   │   ├── default/
│   │   │   ├── page.tsx
│   │   │   └── _components/
│   │   ├── crm/
│   │   ├── finance/
│   │   ├── analytics/
│   │   ├── productivity/
│   │   ├── ecommerce/
│   │   ├── academy/
│   │   ├── logistics/
│   │   ├── infrastructure/
│   │   ├── mail/
│   │   ├── chat/
│   │   ├── calendar/
│   │   ├── kanban/
│   │   ├── tasks/
│   │   ├── invoice/
│   │   ├── users/
│   │   ├── roles/
│   │   ├── coming-soon/
│   │   └── (legacy)/
│   │       ├── default-v1/
│   │       ├── crm-v1/
│   │       ├── finance-v1/
│   │       └── analytics-v1/
│   ├── auth/                        # Existing - verify
│   ├── settings/                    # Existing - verify
│   ├── chat.tsx                     # Existing
│   └── mail.tsx                     # Existing
├── navigation/
│   └── sidebar/
│       └── sidebar-items.ts         # Port from references (220 lines)
├── lib/
│   ├── preferences/
│   │   ├── preferences-config.ts    # Existing - verify
│   │   ├── layout.ts                # Existing - verify
│   │   ├── theme.ts                 # Existing - verify
│   │   └── preferences-runtime.ts   # Existing - verify
│   ├── fonts/
│   │   └── registry.ts              # Existing - verify
│   └── utils.ts                     # Existing
├── stores/
│   └── preferences/
│       └── preferences-provider.tsx # Existing - verify cookie sync
├── hooks/
│   ├── use-appearance.ts            # NEW - ThemeBootScript equivalent
│   └── ...existing hooks...
├── scripts/
│   └── theme-boot.tsx               # NEW - sets data-* attributes on <html>
├── config/
│   └── app-config.ts                # Port from references
└── data/
    └── users.ts                     # Existing - verify
```

## Detailed Requirements

### 1. Theme Boot Script (Critical)

**File**: `resources/js/scripts/theme-boot.tsx`
**Purpose**: Set `data-*` attributes on `<html>` before paint to prevent flicker
**Behavior**:
- Read cookies: `theme_mode`, `theme_preset`, `content_layout`, `navbar_style`, `sidebar_variant`, `sidebar_collapsible`, `font`
- Apply to `document.documentElement.dataset`
- Run inline in `<head>` before React hydration

**Reference**: Next.js `ThemeBootScript` in `src/scripts/theme-boot.ts`

### 2. Sidebar Navigation Data

**File**: `resources/js/navigation/sidebar/sidebar-items.ts`
**Port from**: Next.js/TanStack `src/navigation/sidebar/sidebar-items.ts` (identical)
**Structure**: 4 groups (Dashboards, Pages, Legacy, Misc) with 27 items total
**Types**: `NavGroup`, `NavMainItem`, `NavMainLinkItem`, `NavMainParentItem`, `NavSubItem`, `NavBadge`

### 3. AppSidebar (Full)

**File**: `resources/js/components/dashboard/app-sidebar.tsx`
**Port from**: Next.js `src/app/(main)/dashboard/_components/sidebar/app-sidebar.tsx`
**Key differences from current**:
- Accept `variant` and `collapsible` props (from preferences)
- Sync with `usePreferencesStore` for `isSynced` state
- Render `NavMain` with full `sidebarItems`
- Include `NavDocuments` (commented) and `NavSecondary` (commented)
- Footer: `SidebarSupportCard` + `NavUser`

### 4. AppSidebarHeader (Full)

**File**: `resources/js/components/dashboard/app-sidebar-header.tsx`
**Port from**: Next.js `src/app/(main)/dashboard/_components/sidebar/` header section in layout
**Contains**:
- `SidebarTrigger`
- `Separator` (vertical)
- `SearchDialog`
- `LayoutControls`
- `ThemeSwitcher`
- GitHub link button
- `AccountSwitcher`

### 5. LayoutControls

**File**: `resources/js/components/dashboard/layout-controls.tsx`
**Port from**: Next.js `src/app/(main)/dashboard/_components/sidebar/layout-controls.tsx`
**Controls**: Theme Preset, Fonts, Theme Mode, Page Layout, Navbar Behavior, Sidebar Style, Sidebar Collapse Mode, Restore Defaults
**Uses**: `usePreferencesStore`, `THEME_PRESET_OPTIONS`, `fontOptions`, toggle/select components

### 6. ThemeSwitcher

**File**: `resources/js/components/dashboard/theme-switcher.tsx`
**Port from**: Next.js reference
**Behavior**: Light/Dark/System toggle group

### 7. SearchDialog

**File**: `resources/js/components/dashboard/search-dialog.tsx`
**Port from**: Next.js reference
**Behavior**: Command palette search (cmd+k)

### 8. AccountSwitcher

**File**: `resources/js/components/dashboard/account-switcher.tsx`
**Port from**: Next.js reference
**Props**: `users` array
**Behavior**: Dropdown to switch between user accounts

### 9. SidebarSupportCard

**File**: `resources/js/components/dashboard/sidebar-support-card.tsx`
**Port from**: Next.js reference
**Content**: GitHub stars, sponsor link

### 10. NavMain

**File**: `resources/js/components/dashboard/nav-main.tsx`
**Port from**: Next.js `src/app/(main)/dashboard/_components/sidebar/nav-main.tsx`
**Logic**:
- Active state from `usePage().url` (Inertia) instead of `usePathname()` or `useRouterState()`
- Quick Create + Mail icon buttons
- Collapsible groups with `Collapsible`
- Dropdown for collapsed desktop state
- Badge support (new/soon)

### 11. NavUser

**File**: `resources/js/components/dashboard/nav-user.tsx`
**Port from**: Next.js reference
**Shows**: User avatar, name, role, sign out

### 12. NavDocuments / NavSecondary / NavFooter

**Files**: Port from Next.js references
**NavDocuments**: Commented out in sidebar (Data Library, Reports, Word Assistant)
**NavSecondary**: Commented out (Settings, Get Help, Search)
**NavFooter**: Update to include support card

### 13. Dashboard Layout (Replace)

**File**: `resources/js/layouts/app/app-sidebar-layout.tsx`
**Replace current with**: Full layout matching Next.js `layout.tsx`
**Structure**:
```tsx
<SidebarProvider defaultOpen={fromCookie} style={{ '--sidebar-width': 'calc(var(--spacing) * 68)' }}>
  <AppSidebar variant={variant} collapsible={collapsible} />
  <SidebarInset className={...}>
    <header className={...}>
      <AppSidebarHeader />
    </header>
    <div className="min-h-0 min-w-0 flex-1 overflow-x-hidden p-4 has-data-[content-padding=false]:p-0 md:p-6 md:has-data-[content-padding=false]:p-0">
      {children}
    </div>
  </SidebarInset>
</SidebarProvider>
```

### 14. AppShell Update

**File**: `resources/js/components/app-shell.tsx`
**Changes**:
- Read `sidebar_open` cookie for `defaultOpen`
- Pass `variant` from preferences (via Inertia shared data)

### 15. App Entry Update

**File**: `resources/js/app.tsx`
**Add**:
- Import `ThemeBootScript` equivalent
- Set `data-*` attributes on `<html>` from `PREFERENCE_DEFAULTS` (server-side)
- Include `ThemeBootScript` in `<head>`

### 16-32. Dashboard Screens (17 screens)

**Directory**: `resources/js/pages/dashboard/<screen>/`
**Each screen**: `page.tsx` + `_components/` (colocated)

**Screens to port**:
1. `default` - Default dashboard
2. `crm` - CRM dashboard
3. `finance` - Finance dashboard
4. `analytics` - Analytics dashboard
5. `productivity` - Productivity dashboard
6. `ecommerce` - E-commerce dashboard
6. `academy` - Academy dashboard
7. `logistics` - Logistics dashboard
8. `infrastructure` - Infrastructure dashboard
9. `mail` - Email page
10. `chat` - Chat page
11. `calendar` - Calendar page
12. `kanban` - Kanban board
13. `tasks` - Tasks page
14. `invoice` - Invoice page
15. `users` - Users management
16. `roles` - Roles management
17. `coming-soon` - Coming soon placeholder

**Reference structure**: Each screen follows colocation pattern with `_components/` subdirectory

### 33-36. Legacy Dashboard Screens (4 screens)

**Directory**: `resources/js/pages/dashboard/(legacy)/<screen>/`
1. `default-v1`
2. `crm-v1`
3. `finance-v1`
4. `analytics-v1`

### 37. Config & Data

**Files**: 
- `resources/js/config/app-config.ts` - Port from references
- `resources/js/data/users.ts` - Verify matches

### 38. Preferences Store Cookie Sync

**File**: `resources/js/stores/preferences/preferences-provider.tsx`
**Verify**: 
- Reads cookies on initialization
- Writes cookies on preference change
- Syncs with `PREFERENCE_DEFAULTS`

### 39. Route Registration

**File**: `routes/dashboard.php`
**Verify**: All 21 screens registered in `DashboardController::$screens` array

## Technical Constraints

1. **No `"use client"` directives** - Inertia SSR handles this
2. **Use Inertia Link** - `@inertiajs/react` `Link` component for navigation
3. **Active state from `usePage()`** - `usePage().url` for current route
4. **Server-provided defaults** - Initial preferences via Inertia shared data
5. **Cookie-based persistence** - Client writes cookies, server reads on SSR
6. **Tailwind v4** - Use `@theme` inline, CSS variables for theming
7. **Shadcn new-york style** - Radix UI primitives, already configured
8. **TypeScript strict** - Follow existing type patterns

## Acceptance Criteria

### Layout Parity
- [ ] Sidebar shows all 27 navigation items in 4 groups
- [ ] Sidebar variant (sidebar/inset/floating) changes via LayoutControls
- [ ] Sidebar collapsible (icon/offcanvas/none) changes via LayoutControls
- [ ] Header shows all 6 controls (Search, Layout, Theme, GitHub, Account)
- [ ] Theme presets switch correctly (4 presets)
- [ ] Theme mode toggle works (Light/Dark/System)
- [ ] Font selector works
- [ ] Page layout toggle (Centered/Full Width) works
- [ ] Navbar behavior toggle (Sticky/Scroll) works
- [ ] Preferences persist across reloads (cookies)
- [ ] No flash of wrong theme on load (ThemeBootScript)

### Dashboard Screens
- [ ] All 17 screens render without errors
- [ ] All 4 legacy screens render without errors
- [ ] Each screen uses colocated `_components/`
- [ ] Charts, tables, forms work (TanStack Table, Recharts, React Hook Form)
- [ ] Responsive behavior matches references

### Navigation
- [ ] Active state highlights correct item
- [ ] Collapsible groups expand/collapse
- [ ] Dropdown works in collapsed desktop mode
- [ ] Mobile offcanvas works
- [ ] Auth dropdown links work (v1/v2 login/register)
- [ ] External links open in new tab

## Non-Functional

- **Performance**: Initial load < 3s, navigation < 200ms
- **Accessibility**: Semantic HTML, keyboard nav, ARIA labels, focus states
- **Browser support**: Chrome, Firefox, Safari, Edge (last 2 versions)
- **Responsive**: Mobile (320px+), tablet, desktop (1024px+)