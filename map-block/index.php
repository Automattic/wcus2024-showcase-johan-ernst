<?php
/**
 * Manage the Map block.
 *
 * @package johan-ernst
 */

namespace Johan\Blocks\Map;

use Johan\Blocks\Functions as Blocks;
use Johan\Features\Taxonomy\Location;
use Johan\Features\Taxonomy\ExplorationType;
use function Johan\Features\PostType\Common\get_coordinates_meta_key;
use function Johan\Features\PostType\Exploration\get_slug as exploration_slug;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\register_block' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\handle_frontend_assets' );
add_filter( 'body_class', __NAMESPACE__ . '\add_body_class' );
add_action( 'wp_footer', __NAMESPACE__ . '\render_audio_tag' );
add_action( 'rest_api_init', __NAMESPACE__ . '\add_featured_image_field' );

/**
 * Get the view script handle for the block.
 *
 * @return string The script handle.
 */
function get_view_script_handle(): string {
	return 'johan-ernst-map-view-script';
}

/**
 * Register the block.
 *
 * @return void
 */
function register_block(): void {
	register_block_type_from_metadata(
		JOHAN_BLOCKS_DIR . '/build/map',
		array(
			'render_callback' => __NAMESPACE__ . '\render_block',
		)
	);
}

/**
 * Render the block.
 *
 * @param array $attributes The block attributes.
 *
 * @return string The block markup.
 */
function render_block( array $attributes ): string {
	// Get all the Location taxonomy terms.
	$locations = get_terms(
		array(
			'fields'     => 'id=>name',
			'taxonomy'   => Location\get_slug(),
			'hide_empty' => false,
		)
	);

	$marker_data = array();

	ob_start();

	?>
	<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
		<div class="introduction">
			<p><?php echo esc_html( $attributes['loadingText'] ); ?></p>
			<div class="loading-audio-controls">
				<p>This experience is better with sound, would you like to enable it?</p>
				<div class="loading-audio-toggle">
					<button class="button-sound-on" aria-pressed="false">Sound on</button>
					<button class="button-sound-off" aria-pressed="true">Sound off</button>
				</div>
			</div>
		</div>

		<div class="map"></div>

		<?php if ( ! empty( $locations ) && ! is_wp_error( $locations ) ) : ?>
			<div class="explorations">
				<h2><?php esc_html_e( 'Exploration', 'johan-ernst-blocks' ); ?></h2>
				<ul>
					<?php
					foreach ( $locations as $id => $name ) :
						$location_coordinates = get_term_meta( $id, Location\get_coordinates_meta_key(), true );

						if ( ! $location_coordinates ) {
							continue;
						}

						?>
					<li>
						<a
							class="location"
							data-coordinates="<?php echo esc_attr( $location_coordinates ); ?>"
							href="<?php echo esc_url( get_term_link( $id, Location\get_slug() ) ); ?>"
						>[<?php echo esc_html( $name ); ?>]</a>
						<?php
						// Query for posts with the `coordinate` meta key.
						$posts = new \WP_Query(
							array(
								'meta_query'     => array(
									array(
										'key'     => get_coordinates_meta_key(),
										'compare' => 'EXISTS',
									),
								),
								'no_found_rows'  => true,
								'order'          => 'ASC',
								'orderby'        => 'title',
								'post_status'    => 'publish',
								'post_type'      => array( exploration_slug() ),
								'posts_per_page' => 100,
								'tax_query'      => array(
									array(
										'field'    => 'term_id',
										'taxonomy' => Location\get_slug(),
										'terms'    => $id,
									),
								),
							)
						);

						if ( $posts->have_posts() ) :
							?>
							<ul class="posts js-location-accordion" hidden>
								<?php
								while ( $posts->have_posts() ) :
									$posts->the_post();

									$coordinates = get_post_meta( get_the_ID(), get_coordinates_meta_key(), true );
									$coordinates = explode( ',', $coordinates );

									$exploration_type = wp_get_post_terms( get_the_ID(), ExplorationType\get_slug() );
									$title            = get_the_title();
									if ( ! empty( $exploration_type ) ) {
										$exploration_type = $exploration_type[0]->slug;
									} else {
										$exploration_type = 'exploration';
									}

									$marker_data[] = array(
										'href'  => get_the_permalink(),
										'id'    => get_the_ID(),
										'lat'   => trim( floatval( $coordinates[0] ) ),
										'lng'   => trim( floatval( $coordinates[1] ) ),
										'thumb' => get_the_post_thumbnail_url( get_the_ID(), 'medium_large' ),
										'title' => get_the_title(),
										'type'  => $exploration_type,
									);

									// Do not output Re:wild titles in navigation.
									if ( 'rewild' === $exploration_type ) {
										continue;
									}
									?>
									<li>
										<a
											class="post"
											href="<?php the_permalink(); ?>"
										>
											<?php the_title(); ?>
										</a>
									</li>
									<?php
								endwhile;
								?>
							</ul>
							<?php
						endif;
						wp_reset_postdata();
						?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<svg hidden>
			<symbol id="map-marker-rewild" viewBox="0 0 24 24">
				<circle cx="12" cy="12" r="8" fill="#43C970" stroke="#fff" stroke-width="8"/>
			</symbol>
			<symbol id="map-marker-exploration" viewBox="0 0 32 64">
				<path fill="#FF4800" d="M15 30.562h2v27.435h-2z"/>
				<circle cx="16" cy="60" r="4" fill="#FF4800"/>
			</symbol>
		</svg>
		<template class="post-modal-template">
			<dialog class="post-modal">
				<button autofocus class="post-modal__close-button button close">
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'johan-ernst-blocks' ); ?></span>
				</button>
				<div class="post-wrapper">
					<div class="header">
						<div class="taxonomy-category has-text-align-center wp-block-post-terms has-3-font-size">
							[<a href="" rel="tag"></a>]
						</div>
						<h1 class="has-text-align-center wp-block-post-title"></h1>
					</div>
					<figure class="alignwide wp-block-post-featured-image">
						<img/>
					</figure>
					<div class="post-content"></div>

					<div class="rewild-callout">
						<h2>Re:wild Facts</h2>

						<p>Re:wild launched in 2021 combining more than three decades of conservation impact by <a href="https://www.rewild.org/team/leonardo-dicaprio">Leonardo DiCaprio</a> Foundation and Global Wildlife Conservation, leveraging expertise, partnerships and platforms to bring new attention, energy and voices together. <a href="https://www.rewild.org/team/johan-ernst-nilson">Johan Ernst Nilson</a> is a proud ambassador for Re:wild.</p>

						<div class="wp-block-buttons">
							<div class="wp-block-button is-style-outline is-offsite"><a class="wp-block-button__link wp-element-button" href="https://www.rewild.org/donate-landing">Visit re:wild</a></div>
						</div>
					</div>
				</div>
			</dialog>
		</template>
	</div>
	<?php

	$html = ob_get_clean();

	wp_add_inline_script(
		get_view_script_handle(),
		'const JohanErnstMapBlockData = ' . wp_json_encode(
			array(
				'apiURL'  => get_rest_url( null, 'wp/v2/' ),
				'markers' => $marker_data,
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		) . ';',
		'before'
	);

	wp_enqueue_script( get_view_script_handle() );

	return $html;
}

/**
 * Deregister and reregister the front-end script.
 *
 * There was an issue with `wp_add_inline_script` in the `block_render`
 * function executing twice, resulting in JavaScript errors which
 * prevented the map from rendering. I believe it's the same issue
 * flagged in https://core.trac.wordpress.org/ticket/54958.
 *
 * @return void
 */
function handle_frontend_assets(): void {
	$asset_path = 'build/map/view.js';
	$asset_meta = Blocks\get_asset_meta( JOHAN_BLOCKS_DIR . $asset_path );

	wp_deregister_script( get_view_script_handle() );

	wp_register_script(
		get_view_script_handle(),
		JOHAN_BLOCKS_URL . $asset_path,
		$asset_meta['dependencies'],
		$asset_meta['version'],
		true
	);
}

/**
 * Add a class to the body if the map block is present.
 *
 * @param array $classes The current body classes.
 *
 * @return array The modified body classes.
 */
function add_body_class( array $classes ): array {
	if ( has_block( 'johan-ernst/map' ) ) {
		$classes[] = 'has-map-block';
	}

	return $classes;
}

/**
 * Render the audio tag.
 *
 * @return void
 */
function render_audio_tag(): void {
	if ( ! is_front_page() ) {
		return;
	}

	$audio_url = get_stylesheet_directory_uri() . '/assets/audio/irgendwo-im-nirgendwo-dark-soul-lovesky.mp3';

	?>
	<audio class="js-background-audio" loop>
		<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
	</audio>
	<?php
}

/**
 * Add a featured image field to the REST API.
 *
 * @return void
 */
function add_featured_image_field(): void {
	register_rest_field(
		'exploration',
		'featured_image',
		array(
			'get_callback'    => __NAMESPACE__ . '\get_featured_image',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}

/**
 * Get the featured image for an exploration post.
 *
 * @param array $post The post object.
 *
 * @return string The featured image HTML.
 */
function get_featured_image( array $post ): string {
	return get_the_post_thumbnail( $post['id'], 'large' );
}
