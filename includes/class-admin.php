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
