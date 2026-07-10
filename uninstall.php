<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package Nature_Showcase_For_INaturalist
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$nature_showcase_for_inaturalist_cache_keys = get_option( 'nature_showcase_for_inaturalist_cache_keys', array() );
if ( is_array( $nature_showcase_for_inaturalist_cache_keys ) ) {
	foreach ( $nature_showcase_for_inaturalist_cache_keys as $nature_showcase_for_inaturalist_cache_key ) {
		delete_transient( sanitize_key( $nature_showcase_for_inaturalist_cache_key ) );
	}
}

delete_transient( 'nature_showcase_for_inaturalist_warm_sources_v1' );

$nature_showcase_for_inaturalist_timestamp = wp_next_scheduled( 'nature_showcase_for_inaturalist_warm_cache' );
if ( $nature_showcase_for_inaturalist_timestamp ) {
	wp_unschedule_event( $nature_showcase_for_inaturalist_timestamp, 'nature_showcase_for_inaturalist_warm_cache' );
}

delete_option( 'nature_showcase_for_inaturalist_options' );
delete_option( 'nature_showcase_for_inaturalist_page_id' );
delete_option( 'nature_showcase_for_inaturalist_map_page_id' );
delete_option( 'nature_showcase_for_inaturalist_version' );
delete_option( 'nature_showcase_for_inaturalist_cache_keys' );
