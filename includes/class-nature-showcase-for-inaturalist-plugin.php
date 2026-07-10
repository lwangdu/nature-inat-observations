<?php
/**
 * Main plugin coordinator.
 *
 * @package Nature_Showcase_For_INaturalist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the plugin services.
 */
final class Nature_Showcase_For_INaturalist_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Nature_Showcase_For_INaturalist_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the plugin singleton.
	 *
	 * @return Nature_Showcase_For_INaturalist_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register plugin services.
	 */
	private function __construct() {
		new Nature_Showcase_For_INaturalist_Admin();
		new Nature_Showcase_For_INaturalist_Renderer();
		add_action( 'nature_showcase_for_inaturalist_warm_cache', array( 'Nature_Showcase_For_INaturalist_Cache', 'warm_default_cache' ) );
		add_action( 'save_post', array( 'Nature_Showcase_For_INaturalist_Cache', 'clear_warm_sources_cache' ) );
	}
}
