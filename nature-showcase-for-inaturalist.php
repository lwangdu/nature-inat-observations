<?php
/**
 * Plugin Name: Nature Showcase for iNaturalist by LWangdu
 * Description: Nature Showcase for iNaturalist by LWangdu lets organizations display observations from their iNaturalist projects on their WordPress website using cached API requests and a Gutenberg block. It provides a fast, easy way to showcase project observations while minimizing API calls.
 * Version: 0.2.7
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Lobsang Wangdu
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nature-showcase-for-inaturalist
 *
 * @package Nature_Showcase_For_INaturalist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NATURE_SHOWCASE_FOR_INATURALIST_VERSION', '0.2.7' );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_PATH', plugin_dir_path( __FILE__ ) );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_URL', plugin_dir_url( __FILE__ ) );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_PAGE_OPTION', 'nature_showcase_for_inaturalist_page_id' );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_MAP_PAGE_OPTION', 'nature_showcase_for_inaturalist_map_page_id' );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_VERSION_OPTION', 'nature_showcase_for_inaturalist_version' );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_CACHE_KEYS_OPTION', 'nature_showcase_for_inaturalist_cache_keys' );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_DEFAULT_PROJECT_ID', 0 );
define( 'NATURE_SHOWCASE_FOR_INATURALIST_DEFAULT_PROJECT_SLUG', '' );

require_once NATURE_SHOWCASE_FOR_INATURALIST_PATH . 'includes/class-nature-showcase-for-inaturalist-plugin.php';
require_once NATURE_SHOWCASE_FOR_INATURALIST_PATH . 'includes/class-nature-showcase-for-inaturalist-admin.php';
require_once NATURE_SHOWCASE_FOR_INATURALIST_PATH . 'includes/class-nature-showcase-for-inaturalist-renderer.php';
require_once NATURE_SHOWCASE_FOR_INATURALIST_PATH . 'includes/class-nature-showcase-for-inaturalist-cache.php';

add_action(
	'plugins_loaded',
	function () {
		Nature_Showcase_For_INaturalist_Plugin::instance();
	}
);

register_activation_hook( __FILE__, 'nature_showcase_for_inaturalist_activate' );
register_deactivation_hook( __FILE__, 'nature_showcase_for_inaturalist_deactivate' );

add_action( 'admin_init', 'nature_showcase_for_inaturalist_maybe_create_pages' );

/**
 * Create default pages and store the installed version on activation.
 */
function nature_showcase_for_inaturalist_activate() {
	nature_showcase_for_inaturalist_create_default_pages();
	nature_showcase_for_inaturalist_schedule_cache_warmer();
	update_option( NATURE_SHOWCASE_FOR_INATURALIST_VERSION_OPTION, NATURE_SHOWCASE_FOR_INATURALIST_VERSION );
}

/**
 * Unschedule cache warming on deactivation.
 */
function nature_showcase_for_inaturalist_deactivate() {
	nature_showcase_for_inaturalist_unschedule_cache_warmer();
}

/**
 * Create default pages after plugin updates for already-active installs.
 */
function nature_showcase_for_inaturalist_maybe_create_pages() {
	nature_showcase_for_inaturalist_schedule_cache_warmer();

	if ( NATURE_SHOWCASE_FOR_INATURALIST_VERSION === get_option( NATURE_SHOWCASE_FOR_INATURALIST_VERSION_OPTION ) ) {
		return;
	}

	nature_showcase_for_inaturalist_create_default_pages();
	update_option( NATURE_SHOWCASE_FOR_INATURALIST_VERSION_OPTION, NATURE_SHOWCASE_FOR_INATURALIST_VERSION );
}

/**
 * Schedule hourly background warming for default iNaturalist caches.
 */
function nature_showcase_for_inaturalist_schedule_cache_warmer() {
	if ( ! wp_next_scheduled( 'nature_showcase_for_inaturalist_warm_cache' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'nature_showcase_for_inaturalist_warm_cache' );
	}
}

/**
 * Remove scheduled cache warming.
 */
function nature_showcase_for_inaturalist_unschedule_cache_warmer() {
	$timestamp = wp_next_scheduled( 'nature_showcase_for_inaturalist_warm_cache' );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'nature_showcase_for_inaturalist_warm_cache' );
	}
}

/**
 * Create starter observation pages as drafts.
 */
function nature_showcase_for_inaturalist_create_default_pages() {
	nature_showcase_for_inaturalist_create_page(
		NATURE_SHOWCASE_FOR_INATURALIST_PAGE_OPTION,
		'inaturalist-observations',
		__( 'iNaturalist Observations', 'nature-showcase-for-inaturalist' ),
		'<!-- wp:paragraph --><p>Nature sites support remarkable biodiversity, and community science platforms like iNaturalist help document those living communities over time. This page highlights recent observations recorded for this reserve.</p><!-- /wp:paragraph -->' . "\n\n" . '<!-- wp:nature-showcase-for-inaturalist/observations ' . wp_json_encode( array( 'perPage' => 100 ) ) . ' /-->'
	);

	nature_showcase_for_inaturalist_create_page(
		NATURE_SHOWCASE_FOR_INATURALIST_MAP_PAGE_OPTION,
		'map-of-observations',
		__( 'Map of Observations', 'nature-showcase-for-inaturalist' ),
		'<!-- wp:nature-showcase-for-inaturalist/observations-map ' . wp_json_encode( array( 'perPage' => 200 ) ) . ' /-->'
	);
}

/**
 * Create a WordPress page when it does not already exist.
 *
 * @param string $option_name Option key used to store the page ID.
 * @param string $slug        Page slug.
 * @param string $title       Page title.
 * @param string $content     Page content.
 */
function nature_showcase_for_inaturalist_create_page( $option_name, $slug, $title, $content ) {
	$page_id = absint( get_option( $option_name ) );

	if ( $page_id && 'page' === get_post_type( $page_id ) ) {
		return;
	}

	$existing_page = get_page_by_path( $slug );
	if ( $existing_page instanceof WP_Post ) {
		update_option( $option_name, $existing_page->ID );
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'draft',
			'post_type'    => 'page',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) ) {
		update_option( $option_name, absint( $page_id ) );
	}
}
