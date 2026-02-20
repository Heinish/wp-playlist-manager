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
