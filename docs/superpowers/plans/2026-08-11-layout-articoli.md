# Plan: Layout Articoli (registry v1)

> **Spec:** `docs/superpowers/specs/2026-08-11-layout-articoli-design.md`

**Goal:** Seeded JSON layout registry + Angular list/detail read-only under Cataloghi.

## File map

| File | Change |
|------|--------|
| `fant-admin-api/includes/class-faa-layouts.php` | Create: seed, list, get |
| `fant-admin-api/includes/class-faa-api.php` | Routes GET `/layouts`, `/layouts/{code}` |
| `fant-admin-api/fant-admin-api.php` | require + version 0.8.0 |
| `default/.../layout.service.ts` | Create |
| `default/.../fant-layout-articoli/*` | List component |
| `default/.../fant-layout-articoli-dettaglio/*` | Detail component |
| `fant-routing.module.ts` / `fant.module.ts` / `menu.ts` / `it.json` | Wire UI |

## Tasks

- [x] Spec approved
- [x] Task 1: PHP layouts class + routes + version
- [x] Task 2: Angular LayoutService
- [x] Task 3: List + detail pages + menu/routes
- [x] Task 4: Deploy plugin 0.8.0 to shop

No commits unless requested.
