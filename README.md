# WP Playlist Manager

A WordPress plugin for managing fullscreen image slideshows served to Raspberry Pi display screens — or any browser pointed at a playlist URL.

---

## Features

- **Fullscreen image slideshows** served at `/playlist/{slug}/` — no theme, no header, no footer
- **Drag-and-drop reordering** of images in the admin
- **Per-image settings** — custom duration and frequency (how often an image appears relative to others)
- **Smooth transitions** — Fade, Fade + Zoom, or instant
- **Landscape & portrait** orientation support (1920×1080 or 1080×1920), scales to fit any screen
- **Automatic content refresh** — checks for changes at the end of every cycle and reloads if the playlist has been updated
- **Force Reload button** — instantly triggers a reload on all connected screens without touching the playlist content
- **No build step, no Composer** — plain PHP and vanilla JS

---

## Requirements

| | |
|---|---|
| WordPress | 6.0 or higher |
| PHP | 7.4 or higher |

---

## Installation

1. Download `wp-playlist-manager.zip` from the [latest release](https://github.com/Heinish/wp-playlist-manager/releases/latest)
2. In WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**, then **Activate**

---

## Usage

### Creating a playlist

1. Go to **Playlists → Add New** in the WordPress admin sidebar
2. Give it a title (this becomes the URL slug, e.g. `your-site.com/playlist/lobby-screen`)
3. Configure the global settings in the **Playlist Settings** meta box:

| Setting | Description |
|---|---|
| Slide duration (s) | Default time each image is shown |
| Times per cycle | Default number of times each image appears per full cycle |
| Fade duration (s) | Length of the transition animation |
| Transition | Fade / Fade + Zoom / None |
| Orientation | Horizontal (1920×1080) or Vertical (1080×1920) |

4. Click **+ Add Images** to pick images from the Media Library
5. Optionally override duration and frequency per image
6. Drag rows to reorder
7. Click **Publish** / **Update**

### Viewing the playlist

Navigate to `your-site.com/playlist/{slug}/` — this is what you point the Raspberry Pi browser at.

### Force reloading all screens

Click the **↻ Force Reload** button in the admin meta box. All screens running that playlist will reload at the end of their current cycle (within ~9 seconds for a typical playlist).

---

## How auto-refresh works

At the end of every full cycle the slideshow silently fetches a content hash from the REST API (`/wp-json/ppm/v1/playlist/{id}/hash`). If the hash has changed since the page loaded — because images were added, removed, reordered, or Force Reload was triggered — the page navigates to a fresh URL, bypassing all caches and loading the updated playlist.

## Works great with CSS

**[CSS — Cheap Signage Solutions](https://github.com/Heinish/css)** is a companion app for managing all your Raspberry Pi screens from a single Windows dashboard.

Instead of SSH-ing into each Pi to point it at a playlist URL, CSS lets you:

- Change what's displayed on any Pi in one click
- Monitor CPU, memory, temperature, and uptime per Pi
- Restart browsers or reboot Pis remotely
- Organise screens into rooms
- Schedule daily reboots and auto-updates

**Typical workflow:**
1. Create a playlist in WP Playlist Manager and publish it
2. Copy the playlist URL (e.g. `your-site.com/playlist/lobby`)
3. Open the CSS dashboard and push that URL to whichever Pi(s) should show it
4. Update the playlist content in WordPress whenever you like — screens refresh automatically

---



© 2026 Heinish. All rights reserved.

You may use this software but may not copy, modify, distribute, or sell it without written permission from the author.
