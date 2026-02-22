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

---

## Raspberry Pi setup

Point the Pi's browser (Chromium) to the playlist URL in kiosk mode:

```bash
chromium-browser --kiosk --noerrdialogs --disable-infobars https://your-site.com/playlist/your-playlist/
```

---

## Development

Clone the repo and work directly in the `wp-playlist-manager/` folder. To build a deployable zip:

```bash
python -c "
import zipfile, os
src = 'wp-playlist-manager'
dst = 'wp-playlist-manager.zip'
include = ['wp-playlist-manager.php', 'includes', 'assets', 'templates']
with zipfile.ZipFile(dst, 'w', zipfile.ZIP_DEFLATED) as zf:
    for item in include:
        full = os.path.join(src, item)
        if os.path.isfile(full):
            zf.write(full, 'wp-playlist-manager/' + item)
        elif os.path.isdir(full):
            for root, dirs, files in os.walk(full):
                for file in files:
                    abs_path = os.path.join(root, file)
                    rel = os.path.relpath(abs_path, src).replace(os.sep, '/')
                    zf.write(abs_path, 'wp-playlist-manager/' + rel)
"
```

> **Note:** Use Python's `zipfile` module (not PowerShell `Compress-Archive`) — Python writes forward-slash paths which Linux servers require.

---

## License

MIT
