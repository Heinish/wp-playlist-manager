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
        $fade_duration    = get_post_meta( $post->ID, '_ppm_fade_duration', true ) ?: 0.8;
        $fade_type        = get_post_meta( $post->ID, '_ppm_fade_type', true ) ?: 'fade';
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
                &nbsp;&nbsp;
                <label>
                    Fade duration (seconds):
                    <input type="number" name="ppm_fade_duration" min="0" max="5" step="0.1"
                           value="<?php echo esc_attr( $fade_duration ); ?>">
                </label>
                &nbsp;&nbsp;
                <label>
                    Transition type:
                    <select name="ppm_fade_type">
                        <option value="fade"      <?php selected( $fade_type, 'fade' ); ?>>Fade</option>
                        <option value="fade-zoom" <?php selected( $fade_type, 'fade-zoom' ); ?>>Fade + Zoom</option>
                        <option value="none"      <?php selected( $fade_type, 'none' ); ?>>None (instant)</option>
                    </select>
                </label>
            </div>

            <div class="ppm-items-header">
                <h4>Images</h4>
                <button type="button" class="button" id="ppm-add-images">Add Images</button>
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
            <label>
                Duration (s):
                <input type="number" name="ppm_items[<?php echo $index; ?>][duration]" min="1"
                       placeholder="Global"
                       value="<?php echo esc_attr( $item['duration'] ?? '' ); ?>">
            </label>
            <label>
                Frequency:
                <input type="number" name="ppm_items[<?php echo $index; ?>][frequency]" min="1"
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
            [ 'media-editor' ],
            PPM_VERSION,
            true
        );
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

        $raw_items = $_POST['ppm_items'] ?? [];
        PPM_DB::save_items( $post_id, is_array( $raw_items ) ? $raw_items : [] );
    }
}
