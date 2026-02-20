<?php
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
