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
            ], [ '%d', '%d', '%d', '%d', '%s' ] );
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
