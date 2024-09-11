<?php
/**
 * Manage the "Exploration" post type.
 *
 * @package johan-ernst
 */

namespace Johan\Features\PostType\Exploration;

use Johan\Features\Taxonomy\ExplorationType;
use Yoast\WP\SEO\Helpers\Robots_Txt_Helper;

use function Johan\Features\Functions\enqueue_script;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\register' );
add_action( 'template_redirect', __NAMESPACE__ . '\redirect_rewild' );
add_action( 'pre_get_posts', __NAMESPACE__ . '\filter_pre_get_posts' );
add_filter( 'rest_api_allowed_post_types', __NAMESPACE__ . '\allow_in_related_posts' );
add_filter( 'jetpack_relatedposts_filter_exclude_post_ids', __NAMESPACE__ . '\exclude_rewild_posts_from_related' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_front_end_assets' );
add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\filter_query_block_query_vars', 10, 2 );
add_action( 'Yoast\WP\SEO\register_robots_rules', __NAMESPACE__ . '\add_disallow_for_rewild' );

/**
 * Return the post type slug.
 *
 * @return string The meta key.
 */
function get_slug(): string {
	return 'exploration';
}

/**
 * Register the meta key for capturing coordinates.
 *
 * @return void
 */
function register(): void {
	register_post_type(
		get_slug(),
		array(
			'label'         => __( 'Explorations', 'johan-ernst-features' ),
			'description'   => __( 'Expeditions and other trips', 'johan-ernst-features' ),
			'labels'        => array(
				'name'               => _x( 'Exploration', 'Post Type General Name', 'johan-ernst-features' ),
				'singular_name'      => __( 'Exploration', 'johan-ernst-features' ),
				'menu_name'          => _x( 'Exploration', 'Admin Menu text', 'johan-ernst-features' ),
				'all_items'          => __( 'All Explorations', 'johan-ernst-features' ),
				'view_item'          => __( 'View Exploration', 'johan-ernst-features' ),
				'add_new_item'       => __( 'Add New Exploration', 'johan-ernst-features' ),
				'add_new'            => __( 'Add New', 'johan-ernst-features' ),
				'edit_item'          => __( 'Edit Exploration', 'johan-ernst-features' ),
				'update_item'        => __( 'Update Exploration', 'johan-ernst-features' ),
				'search_items'       => __( 'Search Explorations', 'johan-ernst-features' ),
				'not_found'          => __( 'Not Found', 'johan-ernst-features' ),
				'not_found_in_trash' => __( 'Not found in Trash', 'johan-ernst-features' ),
			),
			'supports'      => array(
				'title',
				'editor',
				'excerpt',
				'author',
				'thumbnail',
				'revisions',
				'custom-fields',
			),
			'public'        => true,
			'menu_position' => 5,
			'show_in_rest'  => true,
			'menu_icon'     => 'dashicons-location-alt',
			'has_archive'   => true,
		)
	);
}

/**
 * Redirect re:wild explorations to the home page.
 *
 * @return void
 */
function redirect_rewild(): void {
	if ( is_singular( get_slug() ) ) {
		$types = wp_get_object_terms( get_queried_object_id(), ExplorationType\get_slug() );

		if ( ! is_wp_error( $types ) && ! empty( $types ) ) {
			$rewild = array_filter(
				$types,
				function ( $type ) {
					return 'rewild' === $type->slug;
				}
			);

			if ( ! empty( $rewild ) ) {
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
		}
	}
}

/**
 * Filter the main query for exploration archives.
 *
 * @param WP_Query $query The main query.
 *
 * @return void
 */
function filter_pre_get_posts( $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! is_post_type_archive( 'exploration' ) ) {
		return;
	}

	$tax_query = $query->get( 'tax_query' );

	if ( ! is_array( $tax_query ) ) {
		$tax_query = array();
	}

	// Exclude re:wild posts from the exploration archive.
	$tax_query[] = array(
		'taxonomy' => ExplorationType\get_slug(),
		'field'    => 'slug',
		'terms'    => 'rewild',
		'operator' => 'NOT IN',
	);

	$query->set( 'tax_query', $tax_query );

	// Order alphabetically by title.
	$query->set( 'orderby', 'title' );
	$query->set( 'order', 'ASC' );
}

/**
 * Allow the Exploration post type to be queried via the REST API.
 *
 * @see https://jetpack.com/support/related-posts/customize-related-posts/#related-posts-custom-post-types.
 *
 * @param array $allowed_post_types The allowed post types.
 *
 * @return array The allowed post types.
 */
function allow_in_related_posts( array $allowed_post_types ): array {
	$allowed_post_types[] = get_slug();

	return $allowed_post_types;
}

/**
 * Exclude the re:wild exploration category from related posts.
 *
 * @param array $post_ids Excluded post IDs.
 *
 * @return array Modified list of excluded post IDs.
 */
function exclude_rewild_posts_from_related( array $post_ids ): array {
	$rewild_posts = new \WP_Query(
		array(
			'post_type'              => get_slug(),
			'post_status'            => 'publish',
			'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'tax_query'              => array(
				array(
					'taxonomy' => ExplorationType\get_slug(),
					'field'    => 'slug',
					'terms'    => 'rewild',
				),
			),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$post_ids = array_merge( $post_ids, $rewild_posts->posts );

	return $post_ids;
}

/**
 * Enqueue front-end assets.
 *
 * @return void.
 */
function enqueue_front_end_assets(): void {
	if ( is_singular( get_slug() ) ) {
		enqueue_script( 'explorations' );
	}
}

/**
 * Filter the query vars for the Query Loop block to include all explorations
 * sorted by location when the query-linear-explorations class has been assigned
 * to the post template block.
 *
 * @param array     $query The query vars.
 * @param \WP_Block $block The block attributes.
 *
 * @return array The filtered query vars.
 */
function filter_query_block_query_vars( array $query, $block ): array {

	if ( is_front_page() && isset( $block->parsed_block['attrs']['className'] ) && str_contains( $block->parsed_block['attrs']['className'], 'query-linear-explorations' ) ) {
		$query['orderby']  = 'post__in';
		$query['post__in'] = get_sorted_explorations();
	}

	return $query;
}

/**
 * Retrieve a list of all sorted explorations, ordered by location name.
 *
 * @return array The sorted explorations.
 */
function get_sorted_explorations(): array {

	$sorted_explorations = wp_cache_get( 'sorted_explorations', 'explorations' );

	if ( $sorted_explorations ) {
		return $sorted_explorations;
	}

	$locations = get_terms(
		array(
			'taxonomy'   => 'location',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'fields'     => 'ids',
		)
	);

	$sorted_explorations = array();

	foreach ( $locations as $location_id ) {
		$explorations_in_location = new \WP_Query(
			array(
				'post_type'              => get_slug(),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'location',
						'field'    => 'term_id',
						'terms'    => $location_id,
					),
				),
			)
		);

		$sorted_explorations = array_merge( $sorted_explorations, $explorations_in_location->posts );
	}

	wp_cache_set( 'sorted_explorations', $sorted_explorations, 'explorations', DAY_IN_SECONDS );

	return $sorted_explorations;
}

/**
 * Retrieve a list of all re:wild explorations.
 *
 * @return array The re:wild explorations.
 */
function get_rewild_slugs(): array {
	$rewild_slugs = wp_cache_get( 'robots:rewild_slugs', 'explorations' );

	if ( $rewild_slugs && is_array( $rewild_slugs ) ) {
		return $rewild_slugs;
	}

	$rewild_posts = new \WP_Query(
		array(
			'post_type'              => get_slug(),
			'post_status'            => 'publish',
			'posts_per_page'         => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'tax_query'              => array(
				array(
					'taxonomy' => ExplorationType\get_slug(),
					'field'    => 'slug',
					'terms'    => 'rewild',
				),
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$rewild_slugs = array_map(
		function ( $post ) {
			return get_post_field( 'post_name', $post );
		},
		$rewild_posts->posts
	);

	wp_cache_set( 'robots:rewild_slugs', $rewild_slugs, 'explorations', DAY_IN_SECONDS );

	return $rewild_slugs;
}

/**
 * Filter the robots.txt rules generated by Yoast SEO to disallow
 * bot access to re:wild explorations.
 *
 * @param Robots_Txt_Helper $robots_txt_helper The robots.txt helper.
 *
 * @return void
 */
function add_disallow_for_rewild( Robots_Txt_Helper $robots_txt_helper ): void {
	$rewild_slugs = get_rewild_slugs();

	// Add disallow rules for rewild posts.
	foreach ( $rewild_slugs as $slug ) {
		$robots_txt_helper->add_disallow( '*', "/$slug" );
	}
}
