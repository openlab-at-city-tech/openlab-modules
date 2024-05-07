<?php
/**
 * Admin module.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Admin module.
 */
class Admin {
	/**
	 * Fetches the singleton instance of this class.
	 *
	 * @return Admin
	 */
	public static function get_instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new Admin();
		}

		return $instance;
	}

	/**
	 * Initializes the admin module.
	 *
	 * @return void
	 */
	public function init() {
		// edit.php mods.
		add_filter( 'manage_' . Schema::get_module_post_type() . '_posts_columns', [ $this, 'module_custom_columns' ] );
		add_action( 'manage_' . Schema::get_module_post_type() . '_posts_custom_column', [ $this, 'module_custom_column_contents' ], 10, 2 );

		add_action( 'restrict_manage_posts', [ $this, 'module_filter_for_page_table_markup' ] );
		add_action( 'pre_get_posts', [ $this, 'module_filter_for_page_table' ] );
	}

	/**
	 * Adds custom columns to Modules edit.php screen.
	 *
	 * @param string[] $columns Column keys and header text.
	 * @return string[]
	 */
	public function module_custom_columns( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column_name ) {
			$new_columns[ $key ] = $column_name;

			if ( 'title' === $key ) {
				$new_columns['module_author'] = __( 'Author', 'openlab-modules' );
			}
		}

		return $new_columns;
	}

	/**
	 * Generates content for Modules edit.php custom columns.
	 *
	 * @param string $column_name String defining the column.
	 * @param int    $post_id     ID of the current post in the loop.
	 * @return void
	 */
	public function module_custom_column_contents( $column_name, $post_id ) {
		if ( 'module_author' === $column_name ) {
			$post = get_post( $post_id );
			if ( $post ) {
				if ( ! function_exists( 'bp_core_get_userlink' ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo bp_core_get_userlink( $post->post_author ); // @phpstan-ignore-line
				} else {
					$author_id = (int) $post->post_author;
					$author    = get_userdata( $author_id );
					if ( $author ) {
						printf(
							'<a href="%s">%s</a>',
							esc_url( get_author_posts_url( $author_id ) ),
							esc_html( $author->display_name )
						);
					}
				}
			}
		}
	}

	/**
	 * Adds the 'Module' filter for Dashboard > Pages.
	 *
	 * @param string $post_type Post type for the current table.
	 * @return void
	 */
	public function module_filter_for_page_table_markup( $post_type ) {
		if ( 'page' !== $post_type ) {
			return;
		}

		$all_modules = Module::get();

		if ( ! $all_modules ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_module_id = ! empty( $_GET['filter-by-module'] ) ? (int) $_GET['filter-by-module'] : 0;

		?>

		<select name="filter-by-module">
			<option value="" <?php selected( ! $selected_module_id ); ?>><?php echo esc_html_e( 'All Modules', 'openlab-modules' ); ?></option>

			<?php foreach ( $all_modules as $module ) : ?>
				<option value="<?php echo esc_html( (string) $module->get_id() ); ?>" <?php selected( $selected_module_id, $module->get_id() ); ?>><?php echo esc_html( $module->get_title() ); ?></option>
			<?php endforeach; ?>
		</select>

		<?php
	}

	/**
	 * Modifies post query when using the 'filter-by-module' tool on edit.php.
	 *
	 * @param \WP_Query $query Query from 'pre_get_posts'.
	 * @return void
	 */
	public function module_filter_for_page_table( $query ) {
		global $pagenow;

		if ( ! is_admin() || ! $query->is_main_query() || 'page' !== $query->get( 'post_type' ) || 'edit.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['filter-by-module'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$module_id = (int) $_GET['filter-by-module'];

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return;
		}

		$module_term_id = $module->get_term_id();

		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		$tax_query['module'] = [
			'taxonomy' => Schema::get_module_taxonomy(),
			'terms'    => $module_term_id,
			'field'    => 'term_id',
		];

		$query->set( 'tax_query', $tax_query );
	}
}
