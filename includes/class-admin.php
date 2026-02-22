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

        $global_duration    = get_post_meta( $post->ID, '_ppm_global_duration', true ) ?: 10;
        $global_frequency   = get_post_meta( $post->ID, '_ppm_global_frequency', true ) ?: 1;
        $fade_duration      = get_post_meta( $post->ID, '_ppm_fade_duration', true ) ?: 0.8;
        $fade_type          = get_post_meta( $post->ID, '_ppm_fade_type', true ) ?: 'fade';
        $orientation        = get_post_meta( $post->ID, '_ppm_orientation', true ) ?: 'landscape';
        $items              = PPM_DB::get_items( $post->ID );
        ?>
        <div id="ppm-meta-box">

            <div class="ppm-global-settings">

                <div class="ppm-field">
                    <span class="ppm-field-label">Slide duration (s)</span>
                    <input type="number" name="ppm_global_duration" min="1"
                           value="<?php echo esc_attr( $global_duration ); ?>">
                </div>

                <div class="ppm-field">
                    <span class="ppm-field-label">Times per cycle</span>
                    <input type="number" name="ppm_global_frequency" min="1"
                           value="<?php echo esc_attr( $global_frequency ); ?>">
                </div>

                <div class="ppm-field">
                    <span class="ppm-field-label">Fade duration (s)</span>
                    <input type="number" name="ppm_fade_duration" min="0" max="5" step="0.1"
                           value="<?php echo esc_attr( $fade_duration ); ?>">
                </div>

                <div class="ppm-field">
                    <span class="ppm-field-label">Transition</span>
                    <select name="ppm_fade_type">
                        <option value="fade"      <?php selected( $fade_type, 'fade' ); ?>>Fade</option>
                        <option value="fade-zoom" <?php selected( $fade_type, 'fade-zoom' ); ?>>Fade + Zoom</option>
                        <option value="none"      <?php selected( $fade_type, 'none' ); ?>>None</option>
                    </select>
                </div>

                <div class="ppm-field">
                    <span class="ppm-field-label">Orientation</span>
                    <div class="ppm-orientation-toggle">
                        <label class="ppm-orient-btn <?php echo $orientation === 'landscape' ? 'active' : ''; ?>">
                            <input type="radio" name="ppm_orientation" value="landscape" <?php checked( $orientation, 'landscape' ); ?> hidden>
                            <span class="ppm-orient-icon">&#9644;</span> Horizontal
                        </label>
                        <label class="ppm-orient-btn <?php echo $orientation === 'portrait' ? 'active' : ''; ?>">
                            <input type="radio" name="ppm_orientation" value="portrait" <?php checked( $orientation, 'portrait' ); ?> hidden>
                            <span class="ppm-orient-icon">&#9646;</span> Vertical
                        </label>
                    </div>
                </div>

            </div>

            <div class="ppm-items-header">
                <p class="ppm-section-title" style="margin:0">Images</p>
                <button type="button" class="button" id="ppm-add-images">+ Add Images</button>
                <button type="button" class="button" id="ppm-force-reload"
                        data-post-id="<?php echo (int) $post->ID; ?>">&#8635; Force Reload</button>
            </div>

            <ul id="ppm-items-list">
                <?php foreach ( $items as $index => $item ) : ?>
                    <?php self::render_item_row( $item, $index ); ?>
                <?php endforeach; ?>
            </ul>

        </div>
        <?php
    }

    public static function render_item_row( array $item, int $index = 0 ): void {
        $thumb = wp_get_attachment_image_url( (int) $item['attachment_id'], 'thumbnail' );
        ?>
        <li class="ppm-item" data-id="<?php echo esc_attr( $item['attachment_id'] ); ?>">
            <span class="ppm-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>
            <img src="<?php echo esc_url( $thumb ); ?>" class="ppm-thumb" alt="">
            <input type="hidden" name="ppm_items[<?php echo $index; ?>][attachment_id]"
                   value="<?php echo esc_attr( $item['attachment_id'] ); ?>">
            <div class="ppm-item-fields">
                <label>
                    Duration (s)
                    <input type="number" name="ppm_items[<?php echo $index; ?>][duration]" min="1"
                           placeholder="Global"
                           value="<?php echo esc_attr( $item['duration'] ?? '' ); ?>">
                </label>
                <label>
                    Frequency
                    <input type="number" name="ppm_items[<?php echo $index; ?>][frequency]" min="1"
                           value="<?php echo esc_attr( $item['frequency'] ); ?>">
                </label>
            </div>
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
            [ 'media-editor' ],
            PPM_VERSION,
            true
        );
        wp_localize_script( 'ppm-admin', 'ppmData', [
            'globalFrequency' => (int) ( get_post_meta( $post->ID, '_ppm_global_frequency', true ) ?: 1 ),
            'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
            'forceNonce'      => wp_create_nonce( 'ppm_force_reload' ),
        ] );
        wp_enqueue_style( 'ppm-admin', PPM_URL . 'assets/admin.css', [], PPM_VERSION );
    }

    public static function save( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['ppm_nonce'] ) || ! wp_verify_nonce( $_POST['ppm_nonce'], 'ppm_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, '_ppm_global_duration',
            max( 1, absint( $_POST['ppm_global_duration'] ?? 10 ) ) );
        update_post_meta( $post_id, '_ppm_global_frequency',
            max( 1, absint( $_POST['ppm_global_frequency'] ?? 1 ) ) );

        $allowed_fade_types = [ 'fade', 'fade-zoom', 'none' ];
        $fade_type = $_POST['ppm_fade_type'] ?? 'fade';
        update_post_meta( $post_id, '_ppm_fade_type',
            in_array( $fade_type, $allowed_fade_types, true ) ? $fade_type : 'fade' );
        update_post_meta( $post_id, '_ppm_fade_duration',
            max( 0, min( 5, (float) ( $_POST['ppm_fade_duration'] ?? 0.8 ) ) ) );

        $orientation = $_POST['ppm_orientation'] ?? 'landscape';
        update_post_meta( $post_id, '_ppm_orientation',
            in_array( $orientation, [ 'landscape', 'portrait' ], true ) ? $orientation : 'landscape' );

        $raw_items = $_POST['ppm_items'] ?? [];
        PPM_DB::save_items( $post_id, is_array( $raw_items ) ? $raw_items : [] );
    }

    public static function ajax_force_reload(): void {
        check_ajax_referer( 'ppm_force_reload', 'nonce' );
        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( 'Unauthorized', 403 );
        }
        update_post_meta( $post_id, '_ppm_force_reload', time() );
        wp_send_json_success();
    }
}
