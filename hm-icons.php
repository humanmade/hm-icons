<?php
/**
 * Plugin Name: HM SVG Font Awesome
 * Description: Adds and API endpoint for fetching font awesome SVGs or PNGs with image fallback
 */

namespace HM_Icons;

defined( 'HM_ICONS_DIR' ) or define( 'HM_ICONS_DIR', __DIR__ );
defined( 'HM_ICONS_URL' ) or define( 'HM_ICONS_URL', plugins_url( '', __FILE__ ) );

add_action( 'parse_request', function ( \WP $wp ) {

	if ( false === strpos( $wp->request, 'svg-icon' ) ) {
		return;
	}

	// parse icon request
	$icon = $wp->request;

	// $name/$size/$colour/(inline)?.(svg|png)

	preg_match( '#^svg-icon/([a-z0-9\-]+)(?:/(\d+))?(?:/([a-zA-Z0-9]{3,6}))?(?:/(inline))?\.(svg|png)$#', $icon, $params );

	$name   = sanitize_text_field( $params[1] );
	$size   = intval( $params[2] );
	$colour = sanitize_text_field( $params[3] );
	$inline = 'inline' === $params[4];
	$type   = sanitize_text_field( $params[5] );

	if ( ! $name || ! $type ) {
		return;
	}

	// generate & output the svg
	if ( 'svg' === $type ) {

		$svg = get_svg( $name, $size, $colour, $inline );

		header( "Content-type: image/svg+xml" );
		echo $svg;
		exit;

	}

	// generate & store png
	if ( 'png' === $type ) {

		$upload_dir = wp_upload_dir();

		defined( 'HM_ICONS_STORE' ) or define( 'HM_ICONS_STORE', $upload_dir['basedir'] . '/svg-icons/' );

		if ( ! file_exists( HM_ICONS_STORE ) ) {
			wp_mkdir_p( HM_ICONS_STORE );
		}

		$svg = get_svg( $name, $size, $colour, false );

		if ( ! class_exists( 'Imagick' ) ) {
			return;
		}

		$image_path = HM_ICONS_STORE .
			implode( "-", array_filter( array( $name, $size, $colour ) ) ) .
			".{$type}";

		$imagick_error = false;

		if ( ! file_exists( $image_path ) ) {

			try {

				$im = new \Imagick();

				$im->readImageBlob( $svg );

				$im->setImageFormat( "png32" );
				$im->resizeImage( $size, $size, imagick::FILTER_LANCZOS, 1 );

				$im->writeImage( $image_path );
				$im->clear();
				$im->destroy();

			} catch ( \ImagickException $error ) {

				error_log( $error->getMessage() );

				$imagick_error = true;

			}

		}

		if ( ! $imagick_error ) {
			wp_safe_redirect( "{$upload_dir['baseurl']}/svg-icons/" .
				implode( "-", array_filter( array( $name, $size, $colour ) ) ) .
				".{$type}", 301 );
			exit;
		}

	}

}, 11 );

/**
 * Return the SVG XML for a given icon name.
 *
 * @param  string  $name     The name of an available SVG icon. See the `icons/{colour}/svg` directory for available
 *                           icons.
 * @param  int     $size     Optional. The size of the icon in pixels. Default 64.
 * @param  string  $colour   Optional. A hex colour code (minus the hash symbol) for the icon's foreground colour.
 *                           Default '000'.
 * @param  boolean $fallback Optional. Whether a PNG fallback should also be included in the XML. Default true.
 * @return string            A string of XML for the requested SVG icon.
 */
function get_svg( $name, $size = 64, $colour = '000', $fallback = true ) {

	// set some sensible defaults around which base colour to use so it's not so jarring in IE8
	$base_colour_path = 'black';
	$colour           = strtolower( $colour );
	if ( in_array( $colour, array( 'white', 'fff', 'ffffff' ) ) ) {
		$base_colour_path = 'white';
	}

	if ( ! file_exists( HM_ICONS_DIR . "/icons/{$base_colour_path}/svg/{$name}.svg" ) ) {
		return '<!-- SVG not found -->';
	}

	// fetch & cache the svg file string
	$svg = wp_cache_get( $name, 'hm-icons', true );
	if ( ! $svg ) {
		$svg = file_get_contents( HM_ICONS_DIR . "/icons/{$base_colour_path}/svg/{$name}.svg" );
		wp_cache_set( $name, $svg, 'hm-icons', YEAR_IN_SECONDS );
	}

	// add a class name
	$svg = str_replace( '<svg ', "<svg class=\"icon icon-{$name} icon-size-{$size}\" ", $svg );

	// set size
	if ( $size ) {
		$svg = str_replace( array(
			'width="1792"',
			'height="1792"',
		), array(
			"width=\"{$size}\"",
			"height=\"{$size}\"",
		), $svg );
	}

	// set colour
	if ( $colour ) {
		$svg = str_replace( '<path ', "<path fill=\"#{$colour}\" ", $svg );
	}

	// add fallback
	if ( $fallback ) {
		if ( ! $size ) {
			$max_size = 256;
		} else {
			$max_size = min( $size, 256 );
			foreach ( array( 16, 22, 24, 32, 48, 64, 128, 256 ) as $valid_size ) {
				if ( $max_size <= $valid_size ) {
					$max_size = $valid_size;
					break;
				}
			}
		}

		$svg = str_replace( '</svg>', sprintf( '<image class="icon icon-fallback icon-%4$s" width="%1$s" height="%1$s" xlink:href="" src="%3$s" /></svg>',
			$size,
			$colour,
			HM_ICONS_URL . "/icons/{$base_colour_path}/png/{$max_size}/{$name}.png",
			$name
		), $svg );

	}

	return $svg;
}

/**
 * Echo the SVG XML for a given icon name.
 *
 * @see `get_svg()`
 *
 * @param  string  $name     The name of an available SVG icon. See the `icons/{colour}/svg` directory for available
 *                           icons.
 * @param  int     $size     Optional. The size of the icon in pixels. Default 64.
 * @param  string  $colour   Optional. A hex colour code (minus the hash symbol) for the icon's foreground colour.
 *                           Default '000'.
 * @param  boolean $fallback Optional. Whether a PNG fallback should also be included in the XML. Default true.
 * @return string            A string of XML for the requested SVG icon.
 */
function svg( $name, $size = 64, $colour = '000', $fallback = true ) {
	echo get_svg( $name, $size, $colour, $fallback );
}
