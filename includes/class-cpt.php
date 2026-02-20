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
