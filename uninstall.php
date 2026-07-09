<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package Nature_Observations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$nature_observations_cache_keys = get_option( 'nature_observations_cache_keys', array() );
if ( is_array( $nature_observations_cache_keys ) ) {
	foreach ( $nature_observations_cache_keys as $nature_observations_cache_key ) {
		delete_transient( sanitize_key( $nature_observations_cache_key ) );
	}
}

delete_transient( 'nature_observations_warm_sources_v1' );

$nature_observations_timestamp = wp_next_scheduled( 'nature_observations_warm_cache' );
if ( $nature_observations_timestamp ) {
	wp_unschedule_event( $nature_observations_timestamp, 'nature_observations_warm_cache' );
}

delete_option( 'nature_observations_options' );
delete_option( 'nature_observations_page_id' );
delete_option( 'nature_observations_map_page_id' );
delete_option( 'nature_observations_version' );
delete_option( 'nature_observations_cache_keys' );
