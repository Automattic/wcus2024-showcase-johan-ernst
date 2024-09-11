<?php
/**
 * Manage the Location taxonomy.
 *
 * @package johan-ernst
 */

namespace Johan\Features\Taxonomy\Location;

use function Johan\Features\PostType\Exploration\get_slug as exploration_slug;
use function Johan\Features\PostType\Press\get_slug as press_slug;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\register' );
add_action( 'init', __NAMESPACE__ . '\register_meta', 11 );
add_action( get_slug() . '_add_form_fields', __NAMESPACE__ . '\display_add_form_field' );
add_action( get_slug() . '_edit_form_fields', __NAMESPACE__ . '\display_edit_form_field' );
add_action( 'created_' . get_slug(), __NAMESPACE__ . '\save_term_fields' );
add_action( 'edited_' . get_slug(), __NAMESPACE__ . '\save_term_fields' );
add_filter( 'manage_edit-' . get_slug() . '_columns', __NAMESPACE__ . '\add_coordinate_column' );
add_filter( 'manage_' . get_slug() . '_custom_column', __NAMESPACE__ . '\display_coordinate_column', 10, 3 );
add_filter( 'term_link', __NAMESPACE__ . '\filter_term_link', 10, 2 );

/**
 * Provide the taxonomy slug.
 *
 * @return string Taxonomy slug.
 */
function get_slug(): string {
	return 'location';
}

/**
 * Return the key for capturing coordinates.
 *
 * @return string The meta key.
 */
function get_coordinates_meta_key(): string {
	return 'coordinates';
}

/**
 * Register the taxonomy.
 *
 * @return void
 */
function register(): void {
	register_taxonomy(
		get_slug(),
		array( 'post', exploration_slug(), press_slug() ),
		array(
			'labels'            => array(
				'name'                  => _x( 'Locations', 'Taxonomy General Name', 'johan-ernst-features' ),
				'singular_name'         => _x( 'Location', 'Taxonomy Singular Name', 'johan-ernst-features' ),
				'search_items'          => __( 'Search Locations', 'johan-ernst-features' ),
				'all_items'             => __( 'All Locations', 'johan-ernst-features' ),
				'parent_item'           => __( 'Parent Location', 'johan-ernst-features' ),
				'parent_item_colon'     => __( 'Parent Location:', 'johan-ernst-features' ),
				'edit_item'             => __( 'Edit Location', 'johan-ernst-features' ),
				'view_item'             => __( 'View Location', 'johan-ernst-features' ),
				'update_item'           => __( 'Update Location', 'johan-ernst-features' ),
				'add_new_item'          => __( 'Add New Location', 'johan-ernst-features' ),
				'new_item_name'         => __( 'New Location Name', 'johan-ernst-features' ),
				'not_found'             => __( 'Not locations found', 'johan-ernst-features' ),
				'no_terms'              => __( 'No locations', 'johan-ernst-features' ),
				'filter_by_item'        => __( 'Filter by location', 'johan-ernst-features' ),
				'items_list_navigation' => __( 'Locations list navigation', 'johan-ernst-features' ),
				'items_list'            => __( 'Locations list', 'johan-ernst-features' ),
				'back_to_items'         => __( '&larr; Back to Locations', 'johan-ernst-features' ),
				'item_link'             => __( 'Location Link', 'johan-ernst-features' ),
				'item_link_description' => __( 'A link to a location', 'johan-ernst-features' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		)
	);
}

/**
 * Register the meta key for capturing coordinates.
 *
 * @return void
 */
function register_meta(): void {
	register_term_meta(
		get_slug(),
		get_coordinates_meta_key(),
		array(
			'auth_callback'     => function () {
				return current_user_can( 'manage_categories' );
			},
			'sanitize_callback' => 'sanitize_text_field',
			'single'            => true,
			'show_in_rest'      => true,
			'type'              => 'string',
		)
	);
}


/**
 * Display the coordinates field on the "Add Term" form.
 *
 * @return void
 */
function display_add_form_field(): void {
	?>
	<div class="form-field">
		<label for="coordinates"><?php esc_html_e( 'Coordinates', 'johan-ernst-features' ); ?></label>
		<input type="text" name="coordinates" id="coordinates" value="" />
		<p><?php esc_html_e( 'The coordinates of the location, in "latitude, longitude" format.', 'johan-ernst-features' ); ?></p>
	</div>
	<?php
}


/**
 * Display the coordinates field on the "Edit Term" form.
 *
 * @param \WP_Term $term The term being edited.
 *
 * @return void
 */
function display_edit_form_field( \WP_Term $term ): void {
	$coordinates = get_term_meta( $term->term_id, get_coordinates_meta_key(), true );
	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="coordinates"><?php esc_html_e( 'Coordinates', 'johan-ernst-features' ); ?></label>
		</th>
		<td>
			<input type="text" name="coordinates" id="coordinates" value="<?php echo esc_attr( $coordinates ); ?>" />
			<p class="description"><?php esc_html_e( 'The coordinates of the location, in "latitude, longitude" format.', 'johan-ernst-features' ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * Save the coordinates field.
 *
 * @param integer $term_id The term ID.
 *
 * @return void
 */
function save_term_fields( int $term_id ): void {
	if ( 'created_' . get_slug() === current_filter() ) {
		check_admin_referer( 'add-tag', '_wpnonce_add-tag' );
	} else {
		check_admin_referer( 'update-tag_' . $term_id );
	}

	if ( ! isset( $_POST['coordinates'] ) ) {
		return;
	}

	$coordinates = sanitize_text_field( wp_unslash( $_POST['coordinates'] ) );

	update_term_meta( $term_id, get_coordinates_meta_key(), $coordinates );
}

/**
 * Add the coordinates column to the term list table.
 *
 * @param array $columns The columns.
 *
 * @return array The modified columns.
 */
function add_coordinate_column( array $columns ): array {
	if ( 'edit-tags' === get_current_screen()->base ) {
		$cb   = $columns['cb'];
		$name = $columns['name'];

		unset( $columns['cb'] );
		unset( $columns['name'] );

		$columns = array_merge(
			array(
				'cb'     => $cb,
				'name'   => $name,
				'coords' => __( 'Coordinates', 'johan-ernst-features' ),
			),
			$columns
		);
	}

	return $columns;
}

/**
 * Display the coordinates in the term list table.
 *
 * @param string  $content The column content.
 * @param string  $column  The column name.
 * @param integer $term_id The term ID.
 *
 * @return string The modified column content.
 */
function display_coordinate_column( string $content, string $column, int $term_id ): string {
	return 'coords' !== $column
		? $content
		: get_term_meta( $term_id, get_coordinates_meta_key(), true );
}

/**
 * Filter location archive links to point to the
 * main location archive.
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

	$link = is_page( 'press' )
		? get_permalink( get_page_by_path( 'press' ) )
		: get_post_type_archive_link( exploration_slug() );
	$link = add_query_arg( 'locationId', $term->term_id, $link );

	return $link;
}
