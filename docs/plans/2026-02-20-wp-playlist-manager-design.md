# WP Playlist Manager — Design Document

**Date**: 2026-02-20

## Overview

A WordPress plugin that manages image playlists for Raspberry Pi display screens. Each playlist is accessible at a clean URL (e.g. `/playlist/1/`) and serves a fullscreen auto-rotating slideshow. The Pi browser loads the URL and displays the rotating content indefinitely, automatically picking up changes without manual intervention.

---

## Architecture

### Custom Post Type: `playlist`

WordPress registers a `playlist` CPT. This gives each playlist:
- A clean URL: `/playlist/{id}/`
- Standard WP admin management (list, create, delete)
- Post meta storage for global settings

**Post meta fields per playlist:**
- `_ppm_global_duration` — default seconds each slide is shown (integer)
- `_ppm_global_frequency` — default times per 10 minutes a slide appears (integer)

### Custom DB Table: `{prefix}_playlist_items`

Created on plugin activation.

| Column | Type | Description |
|---|---|---|
| `id` | INT, PK, AUTO_INCREMENT | Primary key |
| `playlist_id` | INT, NOT NULL | WP post ID of the parent playlist |
| `attachment_id` | INT, NOT NULL | WP media library image attachment ID |
| `sort_order` | INT, NOT NULL | Manual ordering (0-based) |
| `frequency` | INT, NOT NULL, DEFAULT 1 | Times per 10 min this image appears |
| `duration` | INT, NULLABLE | Seconds on screen; NULL = use global |

---

## Admin UI

A custom meta box is rendered inside the `playlist` CPT post editor.

**Global settings section:**
- Default slide duration (seconds)
- Default frequency (times per 10 minutes)

**Image list section:**
- Each uploaded image appears as a row containing:
  - Drag handle (⠿) for drag-and-drop reordering
  - Thumbnail preview
  - Duration override field (blank = inherit global)
  - Frequency field
  - Delete button
- "Add Images" button opens the WordPress media library picker (supports multi-select)

On post save, the plugin:
1. Saves global settings to post meta
2. Deletes existing items for this playlist from the DB table
3. Re-inserts all items in the submitted order

---

## Frontend — Playlist Page

When WordPress routes `/playlist/{id}/`, the plugin intercepts with a custom template that bypasses the active theme entirely. The page is a standalone HTML document.

### Sequence Generation

The rotation sequence is built from the item list using frequency weights:

- An image with `frequency = 3` appears 3 times per cycle
- An image with `frequency = 1` appears once per cycle
- The sequence is distributed evenly (not grouped) so high-frequency images are spread throughout

Example: items A(freq=1), B(freq=3), C(freq=1) → sequence: B, A, B, C, B

### Slideshow Behavior

- Fullscreen, black background, no UI chrome
- Each slide is displayed for its `duration` seconds (falls back to global if null)
- Transitions to next slide by toggling CSS `active` class
- Cycles infinitely through the sequence

### Auto-Refresh

- Every 30 seconds, the page polls a lightweight REST API endpoint: `GET /wp-json/ppm/v1/playlist/{id}/hash`
- The endpoint returns a hash of the playlist's current content
- If the hash differs from the one loaded at page start, the page reloads
- This ensures the Pi always displays up-to-date content without manual intervention

---

## REST API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/wp-json/ppm/v1/playlist/{id}/hash` | Returns content hash for change detection |

---

## No External Dependencies

The plugin has no required dependencies on other plugins (no ACF, no page builders). It uses only WordPress core APIs:
- Custom Post Types
- Media Library / attachment handling
- `$wpdb` for custom table
- WP REST API
- `wp_enqueue_scripts` for frontend assets

---

## Plugin File Structure

```
wp-playlist-manager/
├── wp-playlist-manager.php     # Main plugin file, bootstraps everything
├── includes/
│   ├── class-cpt.php           # Registers playlist CPT
│   ├── class-db.php            # DB table creation and queries
│   ├── class-admin.php         # Meta box, save handler
│   ├── class-frontend.php      # Template override, sequence builder
│   └── class-rest.php          # REST API endpoint
├── assets/
│   ├── admin.js                # Drag-and-drop, media picker
│   ├── admin.css
│   ├── playlist.js             # Slideshow engine, auto-refresh
│   └── playlist.css
└── templates/
    └── playlist.php            # Fullscreen slideshow template
```
