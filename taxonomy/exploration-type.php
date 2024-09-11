<?php
/**
 * Manage the exploration type.
 *
 * @package johan-ernst
 */

namespace Johan\Features\Taxonomy\ExplorationType;

use Johan\Features\PostType\Exploration;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\register' );
add_filter( 'term_link', __NAMESPACE__ . '\filter_term_link', 10, 2 );

/**
 * Provide the taxonomy slug.
 *
 * @return string Taxonomy slug.
 */
function get_slug(): string {
	return 'exploration-type';
}

/**
 * Register the taxonomy.
 *
 * @return void
 */
function register(): void {
	register_taxonomy(
		get_slug(),
		array( Exploration\get_slug() ),
		array(
			'labels'            => array(
				'name'                  => _x( 'Exploration Types', 'Taxonomy General Name', 'johan-ernst-features' ),
				'singular_name'         => _x( 'Exploration Type', 'Taxonomy Singular Name', 'johan-ernst-features' ),
				'search_items'          => __( 'Search Exploration Types', 'johan-ernst-features' ),
				'all_items'             => __( 'All Exploration Types', 'johan-ernst-features' ),
				'parent_item'           => __( 'Parent Exploration Type', 'johan-ernst-features' ),
				'parent_item_colon'     => __( 'Parent Exploration Type:', 'johan-ernst-features' ),
				'edit_item'             => __( 'Edit Exploration Type', 'johan-ernst-features' ),
				'view_item'             => __( 'View Exploration Type', 'johan-ernst-features' ),
				'update_item'           => __( 'Update Exploration Type', 'johan-ernst-features' ),
				'add_new_item'          => __( 'Add New Exploration Type', 'johan-ernst-features' ),
				'new_item_name'         => __( 'New Exploration Type Name', 'johan-ernst-features' ),
				'not_found'             => __( 'Not Exploration Types found', 'johan-ernst-features' ),
				'no_terms'              => __( 'No Exploration Types', 'johan-ernst-features' ),
				'filter_by_item'        => __( 'Filter by Exploration Type', 'johan-ernst-features' ),
				'items_list_navigation' => __( 'Exploration Types list navigation', 'johan-ernst-features' ),
				'items_list'            => __( 'Exploration Types list', 'johan-ernst-features' ),
				'back_to_items'         => __( '&larr; Back to Exploration Types', 'johan-ernst-features' ),
				'item_link'             => __( 'Exploration Type Link', 'johan-ernst-features' ),
				'item_link_description' => __( 'A link to a Exploration Type', 'johan-ernst-features' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		)
	);
}

/**
 * Filter exploration type archive links to point to the
 * main exploration archive.
 *
 * @param string   $link Term link.
 * @param \WP_Term $term Term object.
 *
 * @return string Term link.
 */
function filter_term_link( string $link, \WP_Term $term ): string {
	if ( get_slug() !== $term->taxonomy ) {
		return $link;
	}

	return get_post_type_archive_link( Exploration\get_slug() );
}
