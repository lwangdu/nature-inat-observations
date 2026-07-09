<?php
/**
 * Block and front-end rendering.
 *
 * @package Nature_Observations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders observation blocks.
 */
final class Nature_Observations_Renderer {
	/**
	 * Hook asset and block registration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_interactivity_assets' ) );
	}

	/**
	 * Register front-end styles and scripts.
	 */
	public function register_assets() {
		$frontend_css_path = NATURE_OBSERVATIONS_PATH . 'assets/css/frontend.css';
		$map_js_path       = NATURE_OBSERVATIONS_PATH . 'assets/js/map.js';
		$view_js_path      = NATURE_OBSERVATIONS_PATH . 'assets/js/view.js';
		$leaflet_css_path  = NATURE_OBSERVATIONS_PATH . 'assets/vendor/leaflet/leaflet.css';
		$leaflet_js_path   = NATURE_OBSERVATIONS_PATH . 'assets/vendor/leaflet/leaflet.js';

		wp_register_style(
			'nature-observations-leaflet',
			NATURE_OBSERVATIONS_URL . 'assets/vendor/leaflet/leaflet.css',
			array(),
			file_exists( $leaflet_css_path ) ? filemtime( $leaflet_css_path ) : '1.9.4'
		);

		wp_register_style(
			'nature-observations',
			NATURE_OBSERVATIONS_URL . 'assets/css/frontend.css',
			array(),
			file_exists( $frontend_css_path ) ? filemtime( $frontend_css_path ) : NATURE_OBSERVATIONS_VERSION
		);

		wp_register_script(
			'nature-observations-leaflet',
			NATURE_OBSERVATIONS_URL . 'assets/vendor/leaflet/leaflet.js',
			array(),
			file_exists( $leaflet_js_path ) ? filemtime( $leaflet_js_path ) : '1.9.4',
			true
		);

		wp_register_script(
			'nature-observations-map',
			NATURE_OBSERVATIONS_URL . 'assets/js/map.js',
			array( 'nature-observations-leaflet' ),
			file_exists( $map_js_path ) ? filemtime( $map_js_path ) : NATURE_OBSERVATIONS_VERSION,
			true
		);

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'nature-observations-view',
				NATURE_OBSERVATIONS_URL . 'assets/js/view.js',
				array(
					array(
						'id'     => '@wordpress/interactivity',
						'import' => 'static',
					),
				),
				file_exists( $view_js_path ) ? filemtime( $view_js_path ) : NATURE_OBSERVATIONS_VERSION
			);
		}
	}

	/**
	 * Register dynamic blocks.
	 */
	public function register_block() {
		$options       = Nature_Observations_Admin::get_options();
		$block_js_path = NATURE_OBSERVATIONS_PATH . 'assets/js/block.js';

		wp_register_script(
			'nature-observations-block',
			NATURE_OBSERVATIONS_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n', 'nature-observations-map' ),
			file_exists( $block_js_path ) ? filemtime( $block_js_path ) : NATURE_OBSERVATIONS_VERSION,
			true
		);
		wp_set_script_translations( 'nature-observations-block', 'nature-observations' );
		wp_add_inline_script(
			'nature-observations-block',
			'window.natureObservations = ' . wp_json_encode(
				array(
					'defaultProjectId'   => absint( $options['project_id'] ),
					'defaultProjectSlug' => $options['project_slug'],
					'defaultPerPage'     => absint( $options['per_page'] ),
					'maxPerPage'         => Nature_Observations_Cache::MAX_PER_PAGE,
					'openLinksInNewTab'  => ! empty( $options['open_new_tab'] ),
				)
			) . ';',
			'before'
		);

		$observation_attributes = $this->block_attributes(
			array(
				'perPage' => absint( $options['per_page'] ),
			)
		);
		$map_attributes         = $this->block_attributes(
			array(
				'perPage' => min( Nature_Observations_Cache::MAX_PER_PAGE, 200 ),
			)
		);

		register_block_type(
			NATURE_OBSERVATIONS_PATH . 'blocks/observations',
			array(
				'attributes'      => $observation_attributes,
				'render_callback' => array( $this, 'render_block' ),
			)
		);
		register_block_type(
			'nature-inat/observations',
			array(
				'attributes'      => $observation_attributes,
				'render_callback' => array( $this, 'render_block' ),
			)
		);

		register_block_type(
			NATURE_OBSERVATIONS_PATH . 'blocks/observations-map',
			array(
				'attributes'      => $map_attributes,
				'render_callback' => array( $this, 'render_map_block' ),
			)
		);
		register_block_type(
			'nature-inat/observations-map',
			array(
				'attributes'      => $map_attributes,
				'render_callback' => array( $this, 'render_map_block' ),
			)
		);
	}

	/**
	 * Get shared block attribute definitions with dynamic defaults.
	 *
	 * @param array $overrides Default overrides.
	 * @return array
	 */
	private function block_attributes( $overrides = array() ) {
		$options  = Nature_Observations_Admin::get_options();
		$defaults = wp_parse_args(
			$overrides,
			array(
				'projectId'         => absint( $options['project_id'] ),
				'projectSlug'       => $options['project_slug'],
				'placeId'           => 0,
				'userId'            => '',
				'perPage'           => absint( $options['per_page'] ),
				'openLinksInNewTab' => ! empty( $options['open_new_tab'] ),
				'title'             => '',
				'summary'           => '',
			)
		);

		return array(
			'projectId'         => array(
				'type'    => 'number',
				'default' => absint( $defaults['projectId'] ),
			),
			'projectSlug'       => array(
				'type'    => 'string',
				'default' => sanitize_title( $defaults['projectSlug'] ),
			),
			'placeId'           => array(
				'type'    => 'number',
				'default' => absint( $defaults['placeId'] ),
			),
			'userId'            => array(
				'type'    => 'string',
				'default' => sanitize_text_field( $defaults['userId'] ),
			),
			'perPage'           => array(
				'type'    => 'number',
				'default' => min( Nature_Observations_Cache::MAX_PER_PAGE, max( 1, absint( $defaults['perPage'] ) ) ),
			),
			'openLinksInNewTab' => array(
				'type'    => 'boolean',
				'default' => ! empty( $defaults['openLinksInNewTab'] ),
			),
			'title'             => array(
				'type'    => 'string',
				'default' => sanitize_text_field( $defaults['title'] ),
			),
			'summary'           => array(
				'type'    => 'string',
				'default' => sanitize_text_field( $defaults['summary'] ),
			),
		);
	}

	/**
	 * Enqueue Interactivity API assets early when the map view is in post content.
	 */
	public function enqueue_interactivity_assets() {
		if ( ! function_exists( 'wp_enqueue_script_module' ) || ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if (
			has_block( 'nature-observations/observations', $post ) ||
			has_block( 'nature-observations/observations-map', $post ) ||
			has_block( 'nature-inat/observations', $post ) ||
			has_block( 'nature-inat/observations-map', $post )
		) {
			wp_enqueue_script_module( 'nature-observations-view' );
		}
	}

	/**
	 * Render the observations block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$options = Nature_Observations_Admin::get_options();

		return $this->render(
			array(
				'project_id'   => $attributes['projectId'] ?? $options['project_id'],
				'project_slug' => $attributes['projectSlug'] ?? $options['project_slug'],
				'place_id'     => $attributes['placeId'] ?? 0,
				'user_id'      => $attributes['userId'] ?? '',
				'per_page'     => $attributes['perPage'] ?? $options['per_page'],
				'open_links'   => $attributes['openLinksInNewTab'] ?? $options['open_new_tab'],
				'title'        => $attributes['title'] ?? '',
				'summary'      => $attributes['summary'] ?? '',
			)
		);
	}

	/**
	 * Render the map block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_map_block( $attributes ) {
		$options = Nature_Observations_Admin::get_options();

		return $this->render_map(
			array(
				'project_id'   => $attributes['projectId'] ?? $options['project_id'],
				'project_slug' => $attributes['projectSlug'] ?? $options['project_slug'],
				'place_id'     => $attributes['placeId'] ?? 0,
				'user_id'      => $attributes['userId'] ?? '',
				'per_page'     => $attributes['perPage'] ?? min( Nature_Observations_Cache::MAX_PER_PAGE, 200 ),
				'open_links'   => $attributes['openLinksInNewTab'] ?? $options['open_new_tab'],
				'title'        => $attributes['title'] ?? '',
				'summary'      => $attributes['summary'] ?? '',
			)
		);
	}

	/**
	 * Render the map view.
	 *
	 * @param array $args Render arguments.
	 * @return string
	 */
	private function render_map( $args ) {
		wp_enqueue_style( 'nature-observations-leaflet' );
		wp_enqueue_style( 'nature-observations' );
		wp_enqueue_script( 'nature-observations-map' );

		$use_interactivity_api = function_exists( 'wp_enqueue_script_module' ) && function_exists( 'wp_interactivity_data_wp_context' );
		if ( $use_interactivity_api ) {
			wp_enqueue_script_module( 'nature-observations-view' );
		}

		$raw_group  = isset( $_GET['nature_observations_group'] ) ? sanitize_text_field( wp_unslash( $_GET['nature_observations_group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$group      = Nature_Observations_Cache::sanitize_group( $raw_group );
		$query_args = array(
			'project_id'   => $args['project_id'],
			'project_slug' => $args['project_slug'],
			'place_id'     => $args['place_id'],
			'user_id'      => $args['user_id'],
			'per_page'     => $args['per_page'],
			'page'         => 1,
			'group'        => $group,
			'geo'          => true,
		);
		$data       = $this->get_map_observation_data( $query_args );
		$boundary   = Nature_Observations_Cache::get_source_boundary( $query_args );

		$heading_id            = wp_unique_id( 'nature-observations-map-heading-' );
		$map_id                = wp_unique_id( 'nature-observations-map-' );
		$rail_id               = wp_unique_id( 'nature-observations-map-thumbs-' );
		$title                 = sanitize_text_field( $args['title'] );
		$summary               = sanitize_text_field( $args['summary'] );
		$has_header            = '' !== $title || '' !== $summary;
		$labelledby            = '' !== $title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : '';
		$open_links_in_new_tab = ! in_array( $args['open_links'], array( false, 0, '0', 'false', 'no', 'off' ), true );
		$observations          = ! is_wp_error( $data ) ? $this->mappable_observations( $data['results'] ?? array() ) : array();
		$boundary_data         = ! is_wp_error( $boundary ) ? $boundary : array();
		$carousel_context      = $use_interactivity_api ? wp_interactivity_data_wp_context(
			array(
				'railId'      => $rail_id,
				'hasPrevious' => false,
				'hasNext'     => count( $observations ) > 1,
			),
			'nature-observations/observations-map'
		) : '';

		ob_start();
		?>
		<section class="nature-observations nature-observations-map-view"<?php echo $labelledby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $has_header ) : ?>
				<div class="nature-observations__header">
					<?php if ( '' !== $title ) : ?>
						<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="nature-observations__title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $summary ) : ?>
						<p class="nature-observations__summary"><?php echo esc_html( $summary ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php $this->render_filters( $group ); ?>
			<?php if ( is_wp_error( $data ) ) : ?>
				<p class="nature-observations__notice"><?php echo esc_html( $data->get_error_message() ); ?></p>
			<?php elseif ( empty( $observations ) && empty( $boundary_data ) ) : ?>
				<p class="nature-observations__notice"><?php esc_html_e( 'No mapped observations found for this filter.', 'nature-observations' ); ?></p>
			<?php else : ?>
				<div class="nature-observations-map" data-observations="<?php echo esc_attr( wp_json_encode( $observations ) ); ?>" data-boundary="<?php echo esc_attr( wp_json_encode( $boundary_data ) ); ?>">
					<div id="<?php echo esc_attr( $map_id ); ?>" class="nature-observations-map__canvas" data-map-id="<?php echo esc_attr( $map_id ); ?>"></div>
					<div class="nature-observations-map__recent" aria-label="<?php esc_attr_e( 'Recent mapped observations', 'nature-observations' ); ?>">
						<h3 class="nature-observations-map__recent-title"><?php esc_html_e( 'Recent Observations', 'nature-observations' ); ?></h3>
						<div class="nature-observations-map__carousel"<?php echo $use_interactivity_api ? ' data-wp-interactive="nature-observations/observations-map" ' . $carousel_context . ' data-wp-init="callbacks.initCarousel"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<button class="nature-observations-map__nav nature-observations-map__nav--prev" type="button" aria-label="<?php esc_attr_e( 'Previous observations', 'nature-observations' ); ?>"<?php echo $use_interactivity_api ? ' data-wp-on--click="actions.scrollPrevious" data-wp-bind--disabled="state.isPreviousDisabled"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<span aria-hidden="true">‹</span>
							</button>
							<div id="<?php echo esc_attr( $rail_id ); ?>" class="nature-observations-map__thumbs" tabindex="0">
								<?php if ( empty( $observations ) ) : ?>
									<p class="nature-observations-map__empty"><?php esc_html_e( 'No recent mapped observations found for this filter.', 'nature-observations' ); ?></p>
								<?php else : ?>
									<?php foreach ( array_slice( $observations, 0, 12 ) as $observation ) : ?>
										<?php
										$thumb_context = $use_interactivity_api ? wp_interactivity_data_wp_context(
											array(
												'observationId' => absint( $observation['id'] ),
											),
											'nature-observations/observations-map'
										) : '';
										?>
										<?php if ( ! empty( $observation['url'] ) ) : ?>
											<a class="nature-observations-map__thumb" href="<?php echo esc_url( $observation['url'] ); ?>"<?php echo $open_links_in_new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?><?php echo $use_interactivity_api ? ' ' . $thumb_context . ' data-wp-on--focus="actions.selectObservation" data-wp-on--mouseenter="actions.selectObservation" data-wp-on--pointerdown="actions.selectObservation" data-wp-class--is-active="state.isActiveObservation" data-wp-bind--aria-current="state.activeObservationAriaCurrent"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
												<?php if ( '' !== $observation['photo_url'] ) : ?>
													<img src="<?php echo esc_url( $observation['photo_url'] ); ?>" alt="<?php echo esc_attr( $observation['photo_alt'] ); ?>">
												<?php else : ?>
													<span><?php echo esc_html( $observation['common_name'] ); ?></span>
												<?php endif; ?>
											</a>
										<?php else : ?>
											<span class="nature-observations-map__thumb"<?php echo $use_interactivity_api ? ' ' . $thumb_context . ' data-wp-on--mouseenter="actions.selectObservation" data-wp-class--is-active="state.isActiveObservation"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
												<?php if ( '' !== $observation['photo_url'] ) : ?>
													<img src="<?php echo esc_url( $observation['photo_url'] ); ?>" alt="<?php echo esc_attr( $observation['photo_alt'] ); ?>">
												<?php else : ?>
													<span><?php echo esc_html( $observation['common_name'] ); ?></span>
												<?php endif; ?>
											</span>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<button class="nature-observations-map__nav nature-observations-map__nav--next" type="button" aria-label="<?php esc_attr_e( 'Next observations', 'nature-observations' ); ?>"<?php echo $use_interactivity_api ? ' data-wp-on--click="actions.scrollNext" data-wp-bind--disabled="state.isNextDisabled"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<span aria-hidden="true">›</span>
							</button>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render the observations grid view.
	 *
	 * @param array $args Render arguments.
	 * @return string
	 */
	private function render( $args ) {
		wp_enqueue_style( 'nature-observations' );

		$use_interactivity_api = function_exists( 'wp_enqueue_script_module' ) && function_exists( 'wp_interactivity_data_wp_context' );
		if ( $use_interactivity_api ) {
			wp_enqueue_script_module( 'nature-observations-view' );
		}

		$raw_group  = isset( $_GET['nature_observations_group'] ) ? sanitize_text_field( wp_unslash( $_GET['nature_observations_group'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_page   = isset( $_GET['nature_observations_page'] ) ? absint( wp_unslash( $_GET['nature_observations_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$group      = Nature_Observations_Cache::sanitize_group( $raw_group );
		$page       = max( 1, absint( $raw_page ) );
		$query_args = array(
			'project_id'   => $args['project_id'],
			'project_slug' => $args['project_slug'],
			'place_id'     => $args['place_id'],
			'user_id'      => $args['user_id'],
			'per_page'     => $args['per_page'],
			'page'         => $page,
			'group'        => $group,
		);
		$data       = Nature_Observations_Cache::get_observations( $query_args );

		$heading_id            = wp_unique_id( 'nature-observations-heading-' );
		$title                 = sanitize_text_field( $args['title'] );
		$summary               = sanitize_text_field( $args['summary'] );
		$has_header            = '' !== $title || '' !== $summary;
		$labelledby            = '' !== $title ? ' aria-labelledby="' . esc_attr( $heading_id ) . '"' : '';
		$stats                 = is_wp_error( $data ) ? null : Nature_Observations_Cache::get_source_stats( $query_args );
		$stats_title           = ! is_wp_error( $stats ) && ! empty( $stats['label'] ) ? $stats['label'] : __( 'iNaturalist', 'nature-observations' );
		$stats_filter_label    = $this->group_label( $group );
		$open_links_in_new_tab = ! in_array( $args['open_links'], array( false, 0, '0', 'false', 'no', 'off' ), true );
		$section_context       = $use_interactivity_api ? wp_interactivity_data_wp_context(
			array(
				'isLoading' => false,
			),
			'nature-observations/observations'
		) : '';

		ob_start();
		?>
		<section class="nature-observations"<?php echo $labelledby; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $use_interactivity_api ? ' data-wp-interactive="nature-observations/observations" ' . $section_context . ' data-wp-bind--aria-busy="state.isPaginationLoading" data-wp-class--is-loading="state.isPaginationLoading"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $has_header ) : ?>
				<div class="nature-observations__header">
					<?php if ( '' !== $title ) : ?>
						<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="nature-observations__title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== $summary ) : ?>
						<p class="nature-observations__summary"><?php echo esc_html( $summary ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( is_wp_error( $data ) ) : ?>
				<p class="nature-observations__notice"><?php echo esc_html( $data->get_error_message() ); ?></p>
			<?php elseif ( empty( $data['results'] ) ) : ?>
				<p class="nature-observations__notice"><?php esc_html_e( 'No observations found for this filter.', 'nature-observations' ); ?></p>
			<?php else : ?>
				<?php $this->render_stats_cards( Nature_Observations_Cache::displayed_stats( $data ), $stats, $stats_title, $open_links_in_new_tab, $stats_filter_label ); ?>
				<?php $this->render_filters( $group ); ?>
				<div class="nature-observations__grid">
					<?php foreach ( $data['results'] as $observation ) : ?>
						<?php include NATURE_OBSERVATIONS_PATH . 'templates/observation-card.php'; ?>
					<?php endforeach; ?>
				</div>
				<?php $this->render_pagination( $data ); ?>
			<?php endif; ?>
		</section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get a display label for an active group filter.
	 *
	 * @param string $group Group key.
	 * @return string
	 */
	private function group_label( $group ) {
		$options = Nature_Observations_Cache::group_options();

		return '' !== $group && isset( $options[ $group ] ) ? $options[ $group ] : '';
	}

	/**
	 * Render observation summary cards.
	 *
	 * @param array       $displayed_stats       Displayed-page stats.
	 * @param array|false $source_stats          Source stats.
	 * @param string      $title                 Source title.
	 * @param bool        $open_links_in_new_tab Whether links open in a new tab.
	 * @param string      $filter_label          Active filter label.
	 */
	private function render_stats_cards( $displayed_stats, $source_stats, $title, $open_links_in_new_tab, $filter_label = '' ) {
		?>
		<div class="nature-observations-stats" aria-label="<?php esc_attr_e( 'iNaturalist observation summary', 'nature-observations' ); ?>">
			<div class="nature-observations-stats__card">
				<p class="nature-observations-stats__eyebrow"><?php esc_html_e( 'Showing on this page', 'nature-observations' ); ?></p>
				<div class="nature-observations-stats__metrics">
					<?php $this->render_stat_metric( $displayed_stats['observations'], __( 'Observations', 'nature-observations' ) ); ?>
					<?php $this->render_stat_metric( $displayed_stats['species'], __( 'Species', 'nature-observations' ) ); ?>
					<?php $this->render_stat_metric( $displayed_stats['observers'], __( 'Observers', 'nature-observations' ) ); ?>
				</div>
			</div>

			<?php if ( ! is_wp_error( $source_stats ) && is_array( $source_stats ) ) : ?>
				<div class="nature-observations-stats__card nature-observations-stats__card--source">
					<p class="nature-observations-stats__eyebrow">
						<?php
						if ( '' !== $filter_label ) {
							printf(
								/* translators: 1: iNaturalist source title, 2: active filter label. */
								esc_html__( '%1$s - All time, %2$s', 'nature-observations' ),
								esc_html( $title ),
								esc_html( $filter_label )
							);
						} else {
							printf(
								/* translators: %s: iNaturalist source title. */
								esc_html__( '%s - All time', 'nature-observations' ),
								esc_html( $title )
							);
						}
						?>
					</p>
					<div class="nature-observations-stats__metrics">
						<?php $this->render_stat_metric( $source_stats['observations'], __( 'Observations', 'nature-observations' ) ); ?>
						<?php $this->render_stat_metric( $source_stats['species'], __( 'Species', 'nature-observations' ) ); ?>
						<?php $this->render_stat_metric( $source_stats['identifiers'], __( 'Identifiers', 'nature-observations' ) ); ?>
						<?php $this->render_stat_metric( $source_stats['observers'], __( 'Observers', 'nature-observations' ) ); ?>
					</div>
					<?php if ( ! empty( $source_stats['url'] ) ) : ?>
						<a class="nature-observations-stats__link" href="<?php echo esc_url( $source_stats['url'] ); ?>"<?php echo $open_links_in_new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
							<?php esc_html_e( 'View full project on iNaturalist', 'nature-observations' ); ?>
							<span aria-hidden="true">→</span>
							<?php if ( $open_links_in_new_tab ) : ?>
								<span class="screen-reader-text"> <?php esc_html_e( 'opens in a new tab', 'nature-observations' ); ?></span>
							<?php endif; ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render one stat metric.
	 *
	 * @param int    $value Metric value.
	 * @param string $label Metric label.
	 */
	private function render_stat_metric( $value, $label ) {
		?>
		<div class="nature-observations-stats__metric">
			<strong><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></strong>
			<span><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	/**
	 * Get enough georeferenced observations to make the map match the iNaturalist map view more closely.
	 *
	 * @param array $query_args Query arguments.
	 * @return array|WP_Error
	 */
	private function get_map_observation_data( $query_args ) {
		$map_args             = $query_args;
		$map_args['per_page'] = Nature_Observations_Cache::MAX_PER_PAGE;
		$map_args['page']     = 1;

		$data = Nature_Observations_Cache::get_observations( $map_args );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$results       = $data['results'] ?? array();
		$total_results = absint( $data['total_results'] ?? count( $results ) );
		$per_page      = max( 1, absint( $data['per_page'] ?? Nature_Observations_Cache::MAX_PER_PAGE ) );
		$max_results   = min( Nature_Observations_Cache::MAX_MAP_MARKERS, $total_results );
		$max_pages     = (int) ceil( $max_results / $per_page );

		for ( $page = 2; $page <= $max_pages; $page++ ) {
			$map_args['page'] = $page;
			$page_data        = Nature_Observations_Cache::get_observations( $map_args );

			if ( is_wp_error( $page_data ) ) {
				break;
			}

			$results = array_merge( $results, $page_data['results'] ?? array() );

			if ( count( $results ) >= Nature_Observations_Cache::MAX_MAP_MARKERS ) {
				break;
			}
		}

		$data['results']  = array_slice( $results, 0, Nature_Observations_Cache::MAX_MAP_MARKERS );
		$data['page']     = 1;
		$data['per_page'] = $per_page;

		return $data;
	}

	/**
	 * Convert normalized observations into map marker data.
	 *
	 * @param array $observations Observations.
	 * @return array
	 */
	private function mappable_observations( $observations ) {
		$markers = array();

		foreach ( $observations as $observation ) {
			if ( ! isset( $observation['latitude'], $observation['longitude'] ) || null === $observation['latitude'] || null === $observation['longitude'] ) {
				continue;
			}

			$markers[] = array(
				'id'              => absint( $observation['id'] ),
				'lat'             => (float) $observation['latitude'],
				'lng'             => (float) $observation['longitude'],
				'url'             => esc_url_raw( $observation['url'] ),
				'photo_url'       => esc_url_raw( $observation['photo_url'] ),
				'photo_alt'       => sanitize_text_field( $observation['photo_alt'] ),
				'common_name'     => sanitize_text_field( $observation['common_name'] ),
				'scientific_name' => sanitize_text_field( $observation['scientific_name'] ),
				'taxon_group'     => sanitize_text_field( $observation['taxon_group'] ),
				'observed_on'     => sanitize_text_field( $observation['observed_on'] ),
				'observer'        => sanitize_text_field( $observation['observer'] ),
			);
		}

		return $markers;
	}

	/**
	 * Render pagination controls.
	 *
	 * @param array $data Observation data.
	 */
	private function render_pagination( $data ) {
		$total_results = absint( $data['total_results'] ?? 0 );
		$per_page      = max( 1, absint( $data['per_page'] ?? 100 ) );
		$current_page  = max( 1, absint( $data['page'] ?? 1 ) );
		$total_pages   = (int) ceil( $total_results / $per_page );

		if ( $total_pages <= 1 ) {
			return;
		}

		$start      = max( 1, $current_page - 2 );
		$end        = min( $total_pages, $current_page + 2 );
		$link_attrs = function_exists( 'wp_enqueue_script_module' )
			? ' data-wp-on--mouseenter="actions.prefetchPaginationPage" data-wp-on--focus="actions.prefetchPaginationPage" data-wp-on--click="actions.setPaginationLoading"'
			: '';
		?>
		<nav class="nature-observations-pagination" aria-label="<?php esc_attr_e( 'Observations pagination', 'nature-observations' ); ?>">
			<p class="nature-observations-pagination__summary">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages, 3: total observations. */
					esc_html__( 'Page %1$s of %2$s (%3$s observations)', 'nature-observations' ),
					esc_html( number_format_i18n( $current_page ) ),
					esc_html( number_format_i18n( $total_pages ) ),
					esc_html( number_format_i18n( $total_results ) )
				);
				?>
			</p>
			<div class="nature-observations-pagination__links">
				<?php if ( $current_page > 1 ) : ?>
					<a class="nature-observations-pagination__link" href="<?php echo esc_url( $this->pagination_url( $current_page - 1 ) ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Previous', 'nature-observations' ); ?></a>
				<?php endif; ?>

				<?php if ( $start > 1 ) : ?>
					<a class="nature-observations-pagination__link" href="<?php echo esc_url( $this->pagination_url( 1 ) ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>1</a>
					<?php if ( $start > 2 ) : ?>
						<span class="nature-observations-pagination__ellipsis" aria-hidden="true">...</span>
					<?php endif; ?>
				<?php endif; ?>

				<?php for ( $page = $start; $page <= $end; $page++ ) : ?>
					<?php if ( $page === $current_page ) : ?>
						<span class="nature-observations-pagination__link is-current" aria-current="page"><?php echo esc_html( number_format_i18n( $page ) ); ?></span>
					<?php else : ?>
						<a class="nature-observations-pagination__link" href="<?php echo esc_url( $this->pagination_url( $page ) ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( number_format_i18n( $page ) ); ?></a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ( $end < $total_pages ) : ?>
					<?php if ( $end < $total_pages - 1 ) : ?>
						<span class="nature-observations-pagination__ellipsis" aria-hidden="true">...</span>
					<?php endif; ?>
					<a class="nature-observations-pagination__link" href="<?php echo esc_url( $this->pagination_url( $total_pages ) ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( number_format_i18n( $total_pages ) ); ?></a>
				<?php endif; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a class="nature-observations-pagination__link" href="<?php echo esc_url( $this->pagination_url( $current_page + 1 ) ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Next', 'nature-observations' ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
		<?php
	}

	/**
	 * Build a pagination URL.
	 *
	 * @param int $page Page number.
	 * @return string
	 */
	private function pagination_url( $page ) {
		if ( $page <= 1 ) {
			return remove_query_arg( 'nature_observations_page' );
		}

		return add_query_arg( 'nature_observations_page', absint( $page ) );
	}

	/**
	 * Render observation filter links.
	 *
	 * @param string $active_group Active group key.
	 */
	private function render_filters( $active_group ) {
		?>
		<nav class="nature-observations__filters" aria-label="<?php esc_attr_e( 'Observation filters', 'nature-observations' ); ?>">
			<?php foreach ( Nature_Observations_Cache::group_options() as $group => $label ) : ?>
				<?php
				$url = remove_query_arg( array( 'nature_observations_group', 'nature_observations_page' ) );
				if ( '' !== $group ) {
					$url = add_query_arg( 'nature_observations_group', $group, $url );
				}
				?>
				<a class="nature-observations__filter<?php echo $group === $active_group ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $group === $active_group ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}
