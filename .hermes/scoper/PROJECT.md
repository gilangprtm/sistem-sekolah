# Laravel Shadcn Admin Dashboard - Parity Project

## Project Overview

Bring the Laravel + Inertia + React admin dashboard to full feature parity with the Next.js 16 and TanStack Start reference implementations.

## Reference Projects

| Project | Path | Framework | Status |
|---------|------|-----------|--------|
| Next.js Reference | `D:\Project\next-shadcn-admin-dashboard` | Next.js 16, React 19, Tailwind v4 | **Complete** |
| TanStack Reference | `D:\Project\tanstack-shadcn-admin-dashboard` | TanStack Start, React 19, Tailwind v4 | **Complete** |
| Laravel Target | `D:\Project\laravel-shadcn-admin-dashboard` | Laravel 13, Inertia v3, React 19, Tailwind v4 | **Incomplete** |

## Goal

The Laravel project must match the reference implementations in:
- All 17 dashboard screens + 4 legacy variants
- Full sidebar navigation with collapsible groups
- Layout controls (theme, font, content layout, navbar, sidebar)
- Theme presets (Neutral, Tangerine, Neo Brutalism, Soft Pop)
- Preference persistence via cookies
- Header with search, account switcher, theme switcher, layout controls

## Current State Analysis

### What Exists (Laravel)
- ✅ Shadcn UI components (new-york style, Radix primitives)
- ✅ Theme preset CSS files (4 presets)
- ✅ Basic sidebar structure (AppSidebar, AppShell)
- ✅ Inertia + Laravel routing
- ✅ Auth via Fortify (login, register, 2FA, passkeys)
- ✅ Settings pages (profile, security, appearance)
- ✅ Preferences store (Zustand) with cookie persistence

### What's Missing (Gap Analysis)
| Category | Next.js/TanStack | Laravel | Gap |
|----------|-----------------|---------|-----|
| Dashboard Screens | 17 + 4 legacy | 1 (default) | **16 screens + 4 legacy** |
| Sidebar Navigation | 27 items grouped | 1 item | **Full nav structure** |
| Header Controls | 6 components | 1 (trigger) | **5 components** |
| Theme/Layout Prefs | HTML data attributes | None | **ThemeBootScript equivalent** |
| Preference Sync | Client cookies ↔ store | Server props only | **Cookie hydration** |
| Auth in Sidebar | 4 links (v1/v2) | None | **Auth dropdown** |

## Success Criteria

- [ ] All 17 dashboard screens implemented with same components as references
- [ ] 4 legacy dashboard variants implemented
- [ ] Full sidebar navigation matching `sidebar-items.ts` structure
- [ ] Header with LayoutControls, ThemeSwitcher, SearchDialog, AccountSwitcher, GitHub link
- [ ] Theme presets applied via `data-theme-preset` on `<html>`
- [ ] All layout preferences persisted to cookies and hydrated on load
- [ ] Sidebar variant/collapsible driven by preferences
- [ ] Mobile/offcanvas behavior identical to references

## Login Account

Email address : gilangprtm210498@gmail.com
Password : @Dejavuman123456

## WebApp Link

http://localhost:8000/login
