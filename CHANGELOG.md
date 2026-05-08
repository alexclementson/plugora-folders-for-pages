# Changelog

## 2.4.9
- Sync release version across source, downloads, and update checks

## 2.3.6 — 2026-05-06
- Unified version source of truth

## 2.3.5
- New **Settings → Lightning Folders** admin page with two tabs: **Settings** (general options) and **License** (key activation, previously its own page).
- Settings include: enable folders for which post types, show item counts, show unfiled folder, show folder search, show breadcrumbs, show folder hierarchy in column, include child items, no-reload navigation, context menus, and a custom color palette for folder labels.
- Sidebar now respects the new toggles live (search box, counts, unfiled folder).

## 2.1.0
- Premium folder tree sidebar: nested folders with twisty expand/collapse, persistent expand state, live folder search, color dot labels, and "Collapse all" toolbar action.
- Drag a page row from the Pages list directly onto a folder in the sidebar to assign — instant reload, no dropdown needed.
- Refreshed Plugora-branded styling (purple primary, soft shadows, rounded pill counts) while still blending with WP admin.
- Instant paint via sessionStorage cache so the sidebar appears immediately on subsequent loads (no flash).
- Single REST round-trip; sidebar swaps in place when fresh data arrives.

## 0.5.0
- Added a folder tree sidebar to the Pages list (FileBird-style): click a folder to filter, with live page counts and an inline "+ New folder" button.
- New REST response shape on /folders?with_counts=1 for the sidebar (totals + per-folder counts).

## 0.4.0
- Added a "Folder" meta box to the page editor sidebar so editors can assign a page to a folder while editing it (next to Publish, Page Attributes, Featured image).

## 0.3.0
- Added a top-level "Folders" menu in the admin sidebar so the folder manager is always discoverable (some admin themes were hiding the Pages → Folders submenu).

## 0.2.0
- Folder column on Pages list with inline assignment dropdown.
- Bulk Edit support for moving many pages at once.
- "Unfiled" option in the folder filter.
- REST nonce + root URL middleware so apiFetch authenticates correctly.
- dbDelta-friendly schema (DEFAULT NULL, double space before PRIMARY KEY).
- Versioned schema upgrades via plugins_loaded.

## 0.1.0
- Initial release: folders CRUD, page assignment endpoint, native Pages screen filter.
