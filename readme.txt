=== Plugora Folders for Pages ===
Contributors: plugora
Tags: pages, organization, folders
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 2.4.2
License: GPLv2 or later

A featherweight folder system for the WordPress Pages screen.

== Description ==
Flat data model, instant rendering, no recursive queries. Adds a Folder column and bulk-edit field to Pages → All Pages so you can move pages without leaving the list.

== Installation ==
1. Upload the zip via Plugins → Add New → Upload Plugin.
2. Activate.
3. Go to Pages → Folders to create folders.
4. Assign pages from the Folder column on Pages → All Pages (per row, or via Bulk Edit).

== Changelog ==
= 2.4.1 =
* Smoother drag UX: rows snap back with a subtle animation if you drop outside any folder, or on the same folder.
* Optimistic UI on drop — the row's folder updates instantly, then reverts (with a red shake) if the server rejects the assignment.
* Green flash on successful drop before the list refreshes.

= 2.4.0 =
* New: drag pages from the Pages list directly onto a folder in the sidebar to assign instantly — no dropdown needed.
* Drop a page on "Unfiled" to remove it from its current folder.
* Visual feedback while dragging (row dims, sidebar highlights, folders show a drop ring).
* Rows stay draggable after Quick Edit re-renders them.

= 2.3.5 =
* Performance: preload all folder assignments for the Pages list in a single query (eliminates N+1 lookups on large sites).
* Performance: per-request cache for folder list — used by the column, filter, meta box and bulk edit without re-querying.
* Refactor: centralised table-name + cache helpers in a new Plugora_Folders_Data class.
* Hardening: properly unslash + sanitize all admin request params; added explicit edit_page capability check on the assign REST endpoint.
* Fix: corrected `plugora_skip_view` query arg (previously a typo prevented bypassing the remembered folder view).
* i18n: text-domain aligned with plugin slug `plugora-folders-for-pages` across all strings.

= 2.3.4 =
* Folder column on Pages list with inline assignment.
* Bulk Edit "Folder" field.
* "Unfiled" filter option.
* REST nonce middleware wired correctly.
* Schema fixes for dbDelta.

= 0.1.0 =
* Initial release.
