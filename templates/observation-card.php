<?php
/**
 * Observation card template.
 *
 * @package Nature_Observations
 *
 * @var array $observation Observation data.
 * @var bool  $open_links_in_new_tab Whether observation links open in a new tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nature_observations_quality_label = 'research' === $observation['quality_grade']
	? __( 'Research Grade', 'nature-observations' )
	: ucwords( str_replace( '_', ' ', $observation['quality_grade'] ) );

if ( 'unknown' === $observation['quality_grade'] ) {
	$nature_observations_quality_label = __( 'Unknown status', 'nature-observations' );
}

$nature_observations_show_scientific_name = ! empty( $observation['scientific_name'] ) && 0 !== strcasecmp( $observation['common_name'], $observation['scientific_name'] );
$nature_observations_observed_timestamp   = ! empty( $observation['observed_on'] ) ? strtotime( $observation['observed_on'] ) : false;
?>
<article class="nature-observations-card" aria-label="<?php echo esc_attr( $observation['common_name'] ); ?>">
	<div class="nature-observations-card__media">
		<?php if ( $observation['photo_url'] ) : ?>
			<?php if ( ! empty( $observation['url'] ) ) : ?>
				<a class="nature-observations-card__media-link" href="<?php echo esc_url( $observation['url'] ); ?>"<?php echo $open_links_in_new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<img src="<?php echo esc_url( $observation['photo_url'] ); ?>" alt="<?php echo esc_attr( $observation['photo_alt'] ); ?>" loading="lazy" decoding="async">
					<?php if ( $open_links_in_new_tab ) : ?>
						<span class="screen-reader-text"> <?php esc_html_e( 'opens in a new tab', 'nature-observations' ); ?></span>
					<?php endif; ?>
				</a>
			<?php else : ?>
				<img src="<?php echo esc_url( $observation['photo_url'] ); ?>" alt="<?php echo esc_attr( $observation['photo_alt'] ); ?>" loading="lazy" decoding="async">
			<?php endif; ?>
		<?php else : ?>
			<span class="nature-observations-card__placeholder"><?php esc_html_e( 'No photo', 'nature-observations' ); ?></span>
		<?php endif; ?>
	</div>
	<div class="nature-observations-card__body">
		<?php if ( ! empty( $observation['taxon_group'] ) ) : ?>
			<p class="nature-observations-card__group"><?php echo esc_html( $observation['taxon_group'] ); ?></p>
		<?php endif; ?>
		<h3 class="nature-observations-card__name">
			<?php if ( ! empty( $observation['url'] ) ) : ?>
				<a href="<?php echo esc_url( $observation['url'] ); ?>"<?php echo $open_links_in_new_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php echo esc_html( $observation['common_name'] ); ?>
					<?php if ( $open_links_in_new_tab ) : ?>
						<span class="screen-reader-text"> <?php esc_html_e( 'opens in a new tab', 'nature-observations' ); ?></span>
					<?php endif; ?>
				</a>
			<?php else : ?>
				<?php echo esc_html( $observation['common_name'] ); ?>
			<?php endif; ?>
		</h3>
		<?php if ( $nature_observations_show_scientific_name ) : ?>
			<p class="nature-observations-card__scientific"><em><?php echo esc_html( $observation['scientific_name'] ); ?></em></p>
		<?php endif; ?>
		<div class="nature-observations-card__details">
			<div class="nature-observations-card__meta">
				<?php if ( false !== $nature_observations_observed_timestamp ) : ?>
					<time datetime="<?php echo esc_attr( $observation['observed_on'] ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), $nature_observations_observed_timestamp ) ); ?></time>
				<?php endif; ?>
				<p><?php echo esc_html( $observation['observer'] ); ?></p>
			</div>
			<p class="nature-observations-card__grade"><?php echo esc_html( $nature_observations_quality_label ); ?></p>
		</div>
	</div>
</article>
