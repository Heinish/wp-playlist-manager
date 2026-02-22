# WP Playlist Manager Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a WordPress plugin that manages image playlists served as fullscreen slideshows at `/playlist/{id}/` for Raspberry Pi display screens.

**Architecture:** Custom Post Type `playlist` provides URL routing. A custom DB table `{prefix}_playlist_items` stores images with sort order, frequency, and duration. A standalone PHP template serves a fullscreen slideshow page that auto-refreshes via REST API polling.

**Tech Stack:** PHP 7.4+, WordPress 6.0+ core APIs (`$wpdb`, CPT, REST API, Media Library), Vanilla JavaScript (no jQuery dependency on frontend), CSS3 transitions.

---

## File Structure to Create

```
wp-playlist-manager/
├── wp-playlist-manager.php
├── includes/
│   ├── class-cpt.php
│   ├── class-db.php
│   ├── class-admin.php
│   ├── class-frontend.php
│   └── class-rest.php
├── assets/
│   ├── admin.js
│   ├── admin.css
│   ├── playlist.js
│   └── playlist.css
└── templates/
    └── playlist.php
```

---

### Task 1: Plugin Bootstrap

**Files:**
- Create: `wp-playlist-manager.php`

**Step 1: Create the main plugin file**

```php
<?php
/**
 * Plugin Name: WP Playlist Manager
 * Description: Image playlist slideshows for Raspberry Pi display screens.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'PPM_VERSION', '1.0.0' );
define( 'PPM_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPM_URL', plugin_dir_url( __FILE__ ) );

require_once PPM_DIR . 'includes/class-db.php';
require_once PPM_DIR . 'includes/class-cpt.php';
require_once PPM_DIR . 'includes/class-admin.php';
require_once PPM_DIR . 'includes/class-frontend.php';
require_once PPM_DIR . 'includes/class-rest.php';

register_activation_hook( __FILE__, [ 'PPM_DB', 'create_table' ] );

add_action( 'init', [ 'PPM_CPT', 'register' ] );
add_action( 'add_meta_boxes', [ 'PPM_Admin', 'add_meta_box' ] );
add_action( 'save_post_playlist', [ 'PPM_Admin', 'save' ], 10, 2 );
add_action( 'admin_enqueue_scripts', [ 'PPM_Admin', 'enqueue' ] );
add_action( 'template_include', [ 'PPM_Frontend', 'override_template' ] );
add_action( 'rest_api_init', [ 'PPM_Rest', 'register_routes' ] );
```

**Step 2: Upload plugin to WordPress**

Place the `wp-playlist-manager/` folder in `/wp-content/plugins/`.

Activate it in WP Admin → Plugins.

**Step 3: Verify**

Go to WP Admin → Plugins. Plugin should appear and activate without errors. Check PHP error log for any issues.

**Step 4: Commit**

```bash
git init
git add wp-playlist-manager.php
git commit -m "feat: add plugin bootstrap"
```

---

### Task 2: Database Table

**Files:**
- Create: `includes/class-db.php`

**Step 1: Create the DB class**

```php
<?php
defined( 'ABSPATH' ) || exit;

class PPM_DB {

    const TABLE = 'playlist_items';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function create_table(): void {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            playlist_id BIGINT(20) UNSIGNED NOT NULL,
            attachment_id BIGINT(20) UNSIGNED NOT NULL,
            sort_order  INT(11)    NOT NULL DEFAULT 0,
            frequency   INT(11)    NOT NULL DEFAULT 1,
            duration    INT(11)    NULL,
            PRIMARY KEY (id),
            KEY playlist_id (playlist_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function get_items( int $playlist_id ): array {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE playlist_id = %d ORDER BY sort_order ASC",
                $playlist_id
            ),
            ARRAY_A
        );
    }

    public static function save_items( int $playlist_id, array $items ): void {
        global $wpdb;
        $table = self::table_name();

        $wpdb->delete( $table, [ 'playlist_id' => $playlist_id ], [ '%d' ] );

        foreach ( $items as $order => $item ) {
            $wpdb->insert( $table, [
                'playlist_id'   => $playlist_id,
                'attachment_id' => (int) $item['attachment_id'],
                'sort_order'    => $order,
                'frequency'     => max( 1, (int) $item['frequency'] ),
                'duration'      => isset( $item['duration'] ) && $item['duration'] !== ''
                    ? (int) $item['duration']
                    : null,
            ], [ '%d', '%d', '%d', '%d', '%d' ] );
        }
    }

    public static function get_content_hash( int $playlist_id ): string {
        global $wpdb;
        $table = self::table_name();
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT attachment_id, sort_order, frequency, duration FROM {$table}
                 WHERE playlist_id = %d ORDER BY sort_order ASC",
                $playlist_id
            )
        );
        $meta = get_post_meta( $playlist_id, '_ppm_global_duration', true )
              . get_post_meta( $playlist_id, '_ppm_global_frequency', true );
        return md5( $meta . wp_json_encode( $rows ) );
    }
}
```

**Step 2: Deactivate and reactivate the plugin**

WP Admin → Plugins → Deactivate → Activate. This fires the activation hook which calls `create_table`.

**Step 3: Verify the table exists**

Check your database (phpMyAdmin or wp-cli):
```bash
wp db query "SHOW TABLES LIKE '%playlist_items%';"
```
Expected: one row showing `{prefix}_playlist_items`.

**Step 4: Commit**

```bash
git add includes/class-db.php
git commit -m "feat: add playlist_items DB table with CRUD methods"
```

---

### Task 3: Custom Post Type

**Files:**
- Create: `includes/class-cpt.php`

**Step 1: Create the CPT class**

```php
<?php
defined( 'ABSPATH' ) || exit;

class PPM_CPT {

    public static function register(): void {
        register_post_type( 'playlist', [
            'labels' => [
                'name'          => 'Playlists',
                'singular_name' => 'Playlist',
                'add_new_item'  => 'Add New Playlist',
                'edit_item'     => 'Edit Playlist',
            ],
            'public'        => true,
            'show_in_menu'  => true,
            'menu_icon'     => 'dashicons-format-gallery',
            'supports'      => [ 'title' ],
            'has_archive'   => false,
            'rewrite'       => [ 'slug' => 'playlist' ],
            'show_in_rest'  => false,
        ] );
    }
}
```

**Step 2: Flush rewrite rules**

Go to WP Admin → Settings → Permalinks and click Save (no changes needed — just saving flushes rules).

**Step 3: Verify**

- WP Admin sidebar should show a "Playlists" menu item with a gallery icon.
- Create a test playlist, publish it. Visit `/playlist/your-test-slug/` in browser.
- Should load (404 is fine for now — template override comes later).

**Step 4: Commit**

```bash
git add includes/class-cpt.php
git commit -m "feat: register playlist custom post type"
```

---

### Task 4: Admin Meta Box — Render

**Files:**
- Create: `includes/class-admin.php`
- Create: `assets/admin.css`

**Step 1: Create the admin class with meta box render**

```php
<?php
defined( 'ABSPATH' ) || exit;

class PPM_Admin {

    public static function add_meta_box(): void {
        add_meta_box(
            'ppm_playlist_items',
            'Playlist Settings',
            [ self::class, 'render' ],
            'playlist',
            'normal',
            'high'
        );
    }

    public static function render( WP_Post $post ): void {
        wp_nonce_field( 'ppm_save', 'ppm_nonce' );

        $global_duration  = get_post_meta( $post->ID, '_ppm_global_duration', true ) ?: 10;
        $global_frequency = get_post_meta( $post->ID, '_ppm_global_frequency', true ) ?: 1;
        $items            = PPM_DB::get_items( $post->ID );
        ?>
        <div id="ppm-meta-box">

            <div class="ppm-global-settings">
                <h4>Global Defaults</h4>
                <label>
                    Default slide duration (seconds):
                    <input type="number" name="ppm_global_duration" min="1"
                           value="<?php echo esc_attr( $global_duration ); ?>">
                </label>
                &nbsp;&nbsp;
                <label>
                    Default frequency (per 10 min):
                    <input type="number" name="ppm_global_frequency" min="1"
                           value="<?php echo esc_attr( $global_frequency ); ?>">
                </label>
            </div>

            <div class="ppm-items-header">
                <h4>Images</h4>
                <button type="button" class="button" id="ppm-add-images">Add Images</button>
            </div>

            <ul id="ppm-items-list">
                <?php foreach ( $items as $item ) : ?>
                    <?php self::render_item_row( $item ); ?>
                <?php endforeach; ?>
            </ul>

        </div>
        <?php
    }

    public static function render_item_row( array $item ): void {
        $thumb = wp_get_attachment_image_url( (int) $item['attachment_id'], 'thumbnail' );
        ?>
        <li class="ppm-item" data-id="<?php echo esc_attr( $item['attachment_id'] ); ?>">
            <span class="ppm-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
            <img src="<?php echo esc_url( $thumb ); ?>" class="ppm-thumb" alt="">
            <input type="hidden" name="ppm_items[][attachment_id]"
                   value="<?php echo esc_attr( $item['attachment_id'] ); ?>">
            <label>
                Duration (s):
                <input type="number" name="ppm_items[][duration]" min="1"
                       placeholder="Global"
                       value="<?php echo esc_attr( $item['duration'] ?? '' ); ?>">
            </label>
            <label>
                Frequency:
                <input type="number" name="ppm_items[][frequency]" min="1"
                       value="<?php echo esc_attr( $item['frequency'] ); ?>">
            </label>
            <button type="button" class="button-link ppm-remove-item">&#10005;</button>
        </li>
        <?php
    }

    public static function enqueue( string $hook ): void {
        global $post;
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        if ( ! $post || $post->post_type !== 'playlist' ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'ppm-admin',
            PPM_URL . 'assets/admin.js',
            [],
            PPM_VERSION,
            true
        );
        wp_enqueue_style( 'ppm-admin', PPM_URL . 'assets/admin.css', [], PPM_VERSION );
    }

    public static function save( int $post_id, WP_Post $post ): void {
        if (
            ! isset( $_POST['ppm_nonce'] ) ||
            ! wp_verify_nonce( $_POST['ppm_nonce'], 'ppm_save' ) ||
            defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
        ) {
            return;
        }

        update_post_meta( $post_id, '_ppm_global_duration',
            absint( $_POST['ppm_global_duration'] ?? 10 ) );
        update_post_meta( $post_id, '_ppm_global_frequency',
            absint( $_POST['ppm_global_frequency'] ?? 1 ) );

        $raw_items = $_POST['ppm_items'] ?? [];
        PPM_DB::save_items( $post_id, is_array( $raw_items ) ? $raw_items : [] );
    }
}
```

**Step 2: Create admin CSS**

```css
/* assets/admin.css */
#ppm-meta-box { padding: 8px 0; }

.ppm-global-settings { margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #ddd; }
.ppm-global-settings input[type="number"] { width: 70px; }

.ppm-items-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.ppm-items-header h4 { margin: 0; }

#ppm-items-list { list-style: none; margin: 0; padding: 0; }

.ppm-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    margin-bottom: 4px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 3px;
}

.ppm-drag-handle { cursor: grab; color: #999; font-size: 18px; }
.ppm-drag-handle:active { cursor: grabbing; }

.ppm-thumb { width: 60px; height: 40px; object-fit: cover; border-radius: 2px; }

.ppm-item label { font-size: 12px; }
.ppm-item input[type="number"] { width: 60px; }

.ppm-remove-item { color: #a00; margin-left: auto; font-size: 16px; text-decoration: none; }
.ppm-remove-item:hover { color: #f00; }

.ppm-item.ppm-dragging { opacity: 0.4; border: 2px dashed #2271b1; }
.ppm-item.ppm-drag-over { border-top: 3px solid #2271b1; }
```

**Step 3: Verify meta box renders**

- Go to WP Admin → Playlists → Add New
- You should see the "Playlist Settings" meta box with global settings and an "Add Images" button
- No JS errors in browser console yet (Add Images won't work until Task 5)

**Step 4: Commit**

```bash
git add includes/class-admin.php assets/admin.css
git commit -m "feat: add admin meta box render and save handler"
```

---

### Task 5: Admin JavaScript (Media Picker + Drag-and-Drop)

**Files:**
- Create: `assets/admin.js`

**Step 1: Create admin JS**

```js
/* assets/admin.js */
(function () {
    'use strict';

    /* ── Media Picker ─────────────────────────────────────────── */
    document.getElementById('ppm-add-images').addEventListener('click', function () {
        const frame = wp.media({
            title: 'Select Playlist Images',
            button: { text: 'Add to Playlist' },
            multiple: true,
        });

        frame.on('select', function () {
            const attachments = frame.state().get('selection').toJSON();
            const list = document.getElementById('ppm-items-list');
            attachments.forEach(function (att) {
                const row = buildRow({
                    attachment_id: att.id,
                    thumb: att.sizes?.thumbnail?.url || att.url,
                    duration: '',
                    frequency: 1,
                });
                list.appendChild(row);
            });
        });

        frame.open();
    });

    /* ── Row Builder ──────────────────────────────────────────── */
    function buildRow(item) {
        const li = document.createElement('li');
        li.className = 'ppm-item';
        li.draggable = true;
        li.dataset.id = item.attachment_id;
        li.innerHTML = `
            <span class="ppm-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
            <img src="${escHtml(item.thumb)}" class="ppm-thumb" alt="">
            <input type="hidden" name="ppm_items[][attachment_id]" value="${escHtml(String(item.attachment_id))}">
            <label>Duration (s): <input type="number" name="ppm_items[][duration]" min="1" placeholder="Global" value="${escHtml(String(item.duration))}"></label>
            <label>Frequency: <input type="number" name="ppm_items[][frequency]" min="1" value="${escHtml(String(item.frequency))}"></label>
            <button type="button" class="button-link ppm-remove-item">&#10005;</button>
        `;
        bindRowEvents(li);
        return li;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Remove Button ────────────────────────────────────────── */
    function bindRowEvents(li) {
        li.querySelector('.ppm-remove-item').addEventListener('click', function () {
            li.remove();
        });
        bindDragEvents(li);
    }

    // Bind remove on existing rows
    document.querySelectorAll('.ppm-item').forEach(function (li) {
        li.draggable = true;
        bindRowEvents(li);
    });

    /* ── Drag-and-Drop Reordering ─────────────────────────────── */
    let dragSrc = null;

    function bindDragEvents(li) {
        li.addEventListener('dragstart', function (e) {
            dragSrc = li;
            li.classList.add('ppm-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        li.addEventListener('dragend', function () {
            li.classList.remove('ppm-dragging');
            document.querySelectorAll('.ppm-item').forEach(function (el) {
                el.classList.remove('ppm-drag-over');
            });
        });

        li.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (li !== dragSrc) {
                document.querySelectorAll('.ppm-item').forEach(function (el) {
                    el.classList.remove('ppm-drag-over');
                });
                li.classList.add('ppm-drag-over');
            }
        });

        li.addEventListener('drop', function (e) {
            e.preventDefault();
            if (dragSrc && dragSrc !== li) {
                const list = li.parentNode;
                const items = Array.from(list.children);
                const srcIdx = items.indexOf(dragSrc);
                const tgtIdx = items.indexOf(li);
                if (srcIdx < tgtIdx) {
                    list.insertBefore(dragSrc, li.nextSibling);
                } else {
                    list.insertBefore(dragSrc, li);
                }
            }
            li.classList.remove('ppm-drag-over');
        });
    }
})();
```

**Step 2: Verify drag-and-drop works**

- Go to WP Admin → Playlists → Add New
- Click "Add Images", select 3+ images from media library
- Confirm rows appear with thumbnail, duration, frequency fields
- Drag rows up and down — order should change
- Click ✕ button — row should disappear
- Save the post and reload — images should persist in saved order

**Step 3: Commit**

```bash
git add assets/admin.js
git commit -m "feat: add media picker and drag-and-drop reordering"
```

---

### Task 6: Frontend Template

**Files:**
- Create: `includes/class-frontend.php`
- Create: `templates/playlist.php`
- Create: `assets/playlist.css`

**Step 1: Create the frontend class**

```php
<?php
defined( 'ABSPATH' ) || exit;

class PPM_Frontend {

    public static function override_template( string $template ): string {
        if ( ! is_singular( 'playlist' ) ) {
            return $template;
        }
        return PPM_DIR . 'templates/playlist.php';
    }

    public static function build_sequence( int $playlist_id ): array {
        $items            = PPM_DB::get_items( $playlist_id );
        $global_duration  = (int) ( get_post_meta( $playlist_id, '_ppm_global_duration', true ) ?: 10 );
        $global_frequency = (int) ( get_post_meta( $playlist_id, '_ppm_global_frequency', true ) ?: 1 );

        // Build pool with frequency weighting
        $pool = [];
        foreach ( $items as $item ) {
            $freq     = max( 1, (int) $item['frequency'] );
            $duration = isset( $item['duration'] ) && $item['duration'] !== null
                ? (int) $item['duration']
                : $global_duration;
            $url      = wp_get_attachment_image_url( (int) $item['attachment_id'], 'full' );
            for ( $i = 0; $i < $freq; $i++ ) {
                $pool[] = [
                    'url'      => $url,
                    'duration' => $duration,
                ];
            }
        }

        // Distribute evenly: interleave high-frequency items
        // Sort by original item order, then spread duplicates
        return self::distribute( $pool, $items, $global_duration );
    }

    private static function distribute( array $pool, array $items, int $global_duration ): array {
        // Build per-item slot arrays, then interleave
        $slots = [];
        foreach ( $items as $item ) {
            $freq     = max( 1, (int) $item['frequency'] );
            $duration = isset( $item['duration'] ) && $item['duration'] !== null
                ? (int) $item['duration']
                : $global_duration;
            $url      = wp_get_attachment_image_url( (int) $item['attachment_id'], 'full' );
            $slots[]  = array_fill( 0, $freq, [ 'url' => $url, 'duration' => $duration ] );
        }

        // Round-robin interleave
        $result = [];
        $max    = max( array_map( 'count', $slots ) );
        for ( $i = 0; $i < $max; $i++ ) {
            foreach ( $slots as $s ) {
                if ( isset( $s[ $i ] ) ) {
                    $result[] = $s[ $i ];
                }
            }
        }

        return $result;
    }
}
```

**Step 2: Create the playlist template**

```php
<?php
/* templates/playlist.php */
defined( 'ABSPATH' ) || exit;

global $post;
$playlist_id = $post->ID;
$sequence    = PPM_Frontend::build_sequence( $playlist_id );
$hash        = PPM_DB::get_content_hash( $playlist_id );
$rest_url    = rest_url( 'ppm/v1/playlist/' . $playlist_id . '/hash' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_the_title() ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( PPM_URL . 'assets/playlist.css?v=' . PPM_VERSION ); ?>">
</head>
<body>

<div id="ppm-slideshow">
    <div id="ppm-splash"></div>
    <?php foreach ( $sequence as $i => $slide ) : ?>
        <div class="ppm-slide<?php echo $i === 0 ? ' active' : ''; ?>"
             data-duration="<?php echo esc_attr( $slide['duration'] ); ?>">
            <img src="<?php echo esc_url( $slide['url'] ); ?>" alt="">
        </div>
    <?php endforeach; ?>
</div>

<script>
    window.PPM = {
        hash:    <?php echo wp_json_encode( $hash ); ?>,
        restUrl: <?php echo wp_json_encode( $rest_url ); ?>,
    };
</script>
<script src="<?php echo esc_url( PPM_URL . 'assets/playlist.js?v=' . PPM_VERSION ); ?>"></script>
</body>
</html>
```

**Step 3: Create playlist CSS**

```css
/* assets/playlist.css */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    width: 100%; height: 100%;
    background: #000;
    overflow: hidden;
}

#ppm-slideshow {
    position: relative;
    width: 100%; height: 100vh;
}

#ppm-splash {
    position: absolute; inset: 0;
    background: #000;
    z-index: 10;
    transition: opacity 0.5s ease;
}

#ppm-splash.hidden { opacity: 0; pointer-events: none; }

.ppm-slide {
    position: absolute; inset: 0;
    opacity: 0;
    transition: opacity 0.8s ease;
}

.ppm-slide.active { opacity: 1; }

.ppm-slide img {
    width: 100%; height: 100%;
    object-fit: contain;
}
```

**Step 4: Verify the template loads**

- Go to WP Admin → Playlists → Add New playlist
- Add some images, save, then visit `/playlist/your-slug/`
- Should see a black fullscreen page (JS not wired yet — images won't rotate)
- No WordPress header/footer should appear

**Step 5: Commit**

```bash
git add includes/class-frontend.php templates/playlist.php assets/playlist.css
git commit -m "feat: add playlist frontend template and sequence builder"
```

---

### Task 7: Slideshow JavaScript + Auto-refresh

**Files:**
- Create: `assets/playlist.js`

**Step 1: Create the slideshow engine**

```js
/* assets/playlist.js */
(function () {
    'use strict';

    const slides   = Array.from( document.querySelectorAll( '.ppm-slide' ) );
    const splash   = document.getElementById( 'ppm-splash' );
    let current    = 0;
    let timer      = null;

    if ( slides.length === 0 ) return;

    function showSlide( index ) {
        slides[ current ].classList.remove( 'active' );
        current = index % slides.length;
        slides[ current ].classList.add( 'active' );
    }

    function getDuration( slide ) {
        return ( parseInt( slide.dataset.duration, 10 ) || 10 ) * 1000;
    }

    function advance() {
        const nextIndex = ( current + 1 ) % slides.length;
        showSlide( nextIndex );
        timer = setTimeout( advance, getDuration( slides[ current ] ) );
    }

    function start() {
        // Hide splash once first image is loaded
        const firstImg = slides[ 0 ].querySelector( 'img' );
        function onReady() {
            splash.classList.add( 'hidden' );
            timer = setTimeout( advance, getDuration( slides[ 0 ] ) );
        }
        if ( firstImg.complete ) {
            onReady();
        } else {
            firstImg.addEventListener( 'load', onReady, { once: true } );
        }
    }

    start();

    /* ── Auto-refresh ─────────────────────────────────────────── */
    const { hash: initHash, restUrl } = window.PPM;

    function checkForUpdates() {
        fetch( restUrl, { cache: 'no-store' } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( data ) {
                if ( data.hash && data.hash !== initHash ) {
                    window.location.reload();
                }
            } )
            .catch( function () { /* ignore network errors */ } );
    }

    setInterval( checkForUpdates, 30000 );

})();
```

**Step 2: Verify the slideshow works**

- Visit `/playlist/your-slug/`
- Splash screen should fade after first image loads
- Slides should rotate automatically, each staying for its configured duration
- Open browser console — no errors

**Step 3: Test auto-refresh**

- Open the playlist page in the browser
- In another tab, go to WP Admin and change an image in the playlist, save
- Within 30 seconds the Pi page should reload automatically

**Step 4: Commit**

```bash
git add assets/playlist.js
git commit -m "feat: add slideshow engine with auto-refresh polling"
```

---

### Task 8: REST API — Content Hash Endpoint

**Files:**
- Create: `includes/class-rest.php`

**Step 1: Create the REST class**

```php
<?php
defined( 'ABSPATH' ) || exit;

class PPM_Rest {

    public static function register_routes(): void {
        register_rest_route( 'ppm/v1', '/playlist/(?P<id>\d+)/hash', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ self::class, 'get_hash' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
                ],
            ],
        ] );
    }

    public static function get_hash( WP_REST_Request $request ): WP_REST_Response {
        $playlist_id = (int) $request->get_param( 'id' );

        if ( get_post_type( $playlist_id ) !== 'playlist' ) {
            return new WP_REST_Response( [ 'error' => 'Not found' ], 404 );
        }

        return new WP_REST_Response( [
            'hash' => PPM_DB::get_content_hash( $playlist_id ),
        ], 200 );
    }
}
```

**Step 2: Verify the endpoint**

In your browser or with curl, hit:
```
GET https://yoursite.com/wp-json/ppm/v1/playlist/123/hash
```
Replace `123` with your playlist's post ID (visible in WP Admin URL when editing).

Expected response:
```json
{ "hash": "d41d8cd98f00b204e9800998ecf8427e" }
```

**Step 3: Commit**

```bash
git add includes/class-rest.php
git commit -m "feat: add REST endpoint for playlist content hash"
```

---

### Task 9: End-to-End Verification

**Step 1: Create a complete test playlist**

- WP Admin → Playlists → Add New
- Title: "Test Playlist"
- Add 4 images: set image 1 frequency=3, others frequency=1
- Set a per-image duration on one image (e.g. 5s), leave others blank
- Global defaults: duration=10, frequency=1
- Save

**Step 2: Verify the playlist page**

Visit `/playlist/test-playlist/`. Confirm:
- [ ] Fullscreen black background, no WP chrome
- [ ] Splash fades after first image loads
- [ ] Images rotate automatically
- [ ] Image with frequency=3 appears ~3x more often than others
- [ ] Timed slide stays on for correct duration (count seconds)
- [ ] Cycle repeats infinitely

**Step 3: Verify auto-refresh**

- Open playlist page in browser
- In WP Admin, add or remove an image and save
- Within 30 seconds, page should reload

**Step 4: Verify drag-and-drop saves correctly**

- WP Admin → Edit playlist
- Drag image from position 3 to position 1
- Save
- Visit playlist URL — image should now appear first in rotation

**Step 5: Final commit**

```bash
git add .
git commit -m "feat: wp-playlist-manager complete"
```

---

## Edge Cases Already Handled

- Empty playlist: `build_sequence` returns empty array; template renders no slides and JS exits early
- Image deleted from media: `wp_get_attachment_image_url` returns `false`; slide will show broken image (acceptable for v1)
- REST endpoint: returns 404 for invalid or non-playlist post IDs
- Frequency < 1: clamped to 1 in `save_items` and `build_sequence`
