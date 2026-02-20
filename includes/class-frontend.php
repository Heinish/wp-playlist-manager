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

        if ( empty( $items ) ) {
            return [];
        }

        return self::distribute( $items, $global_duration );
    }

    private static function distribute( array $items, int $global_duration ): array {
        $slots = [];
        foreach ( $items as $item ) {
            $freq     = max( 1, (int) $item['frequency'] );
            $duration = isset( $item['duration'] ) && $item['duration'] !== null
                ? (int) $item['duration']
                : $global_duration;
            $url      = wp_get_attachment_image_url( (int) $item['attachment_id'], 'full' );
            $slots[]  = array_fill( 0, $freq, [ 'url' => $url, 'duration' => $duration ] );
        }

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
