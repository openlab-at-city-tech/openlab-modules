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

		add_filter( 'manage_page_posts_columns', [ $this, 'page_custom_columns' ] );
		add_action( 'manage_page_posts_custom_column', [ $this, 'page_custom_column_contents' ], 10, 2 );

		add_action( 'admin_footer-edit.php', function() {
		  global $typenow;

		  if ( 'openlab_module' !== $typenow ) {
			return;
		  }

		  echo '<div id="clone-module-app-root"></div>';
		} );

		add_action( 'restrict_manage_posts', [ $this, 'module_filter_for_page_table_markup' ] );
		add_action( 'pre_get_posts', [ $this, 'module_filter_for_page_table' ] );

		add_filter( 'wp_dropdown_pages', [ $this, 'add_modules_to_page_on_front_dropdown' ], 10, 2 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		add_action( 'admin_init', [ $this, 'set_blogmeta_flag' ] );
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
	 * Generates content for PAges edit.php custom columns.
	 *
	 * @param string $column_name String defining the column.
	 * @param int    $post_id     ID of the current post in the loop.
	 * @return void
	 */
	public function page_custom_column_contents( $column_name, $post_id ) {
		if ( 'module' === $column_name ) {
			$module_ids = Module::get_module_ids_of_page( $post_id );
			if ( $module_ids ) {
				$module_links = [];
				foreach ( $module_ids as $module_id ) {
					$module_edit_link = get_edit_post_link( $module_id ) ?? '';

					$module_links[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( $module_edit_link ),
						esc_html( get_the_title( $module_id ) )
					);
				}
				echo implode( ', ', $module_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		// Assets that are specific to edit.php?post_type=openlab_module.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit.php' === $GLOBALS['pagenow'] && isset( $_GET['post_type'] ) && Schema::get_module_post_type() === $_GET['post_type'] ) {
			$blocks_asset_file = Editor::get_blocks_asset_file( 'admin' );

			wp_enqueue_script(
				'openlab-modules-admin',
				OPENLAB_MODULES_PLUGIN_URL . '/build/admin.js',
				[ 'wp-element', 'wp-api-fetch', 'wp-i18n' ],
				$blocks_asset_file['version'],
				true
			);

			wp_localize_script(
				'openlab-modules-admin',
				'openlabModulesAdmin',
				[
					'clone' => __( 'Clone', 'openlab-modules' ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				]
			);

			wp_enqueue_style(
				'openlab-modules-admin',
				OPENLAB_MODULES_PLUGIN_URL . '/build/admin.css',
				[],
				$blocks_asset_file['version']
			);
		}
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

			$module = Module::get_instance( $post_id );
			if ( $module ) {
				$enable_sharing = $module->is_sharing_enabled();

				printf(
					'<input class="enable-sharing" type="hidden" id="enable-sharing-%s" value="%s" />',
					esc_attr( (string) $post_id ),
					$enable_sharing ? '1' : '0'
				);

				if ( $enable_sharing ) {
					$module_id = (string) $post_id;
					$uniqid    = 'clone-module-' . $module_id;
					echo '<div
						class="clone-module-container"
						data-module-id="' . esc_attr( $module_id ) . '"
						data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"
						data-uniqid="' . esc_attr( $uniqid ) . '"
					></div>';
				}
			}
		}
	}

	/**
	 * Adds custom columns to Pages edit.php screen.
	 *
	 * @param string[] $columns Column keys and header text.
	 * @return string[]
	 */
	public function page_custom_columns( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column_name ) {
			$new_columns[ $key ] = $column_name;

			if ( 'title' === $key ) {
				$new_columns['module'] = __( 'Module', 'openlab-modules' );
			}
		}

		return $new_columns;
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
		$selected_module_id = ! empty( $_GET['filter-by-module'] ) && is_numeric( $_GET['filter-by-module'] ) ? (int) $_GET['filter-by-module'] : 0;

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
		$module_id = is_numeric( $_GET['filter-by-module'] ) ? (int) $_GET['filter-by-module'] : 0;

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

	/**
	 * Adds modules to the 'Page on Front' dropdown.
	 *
	 * @param string  $output Dropdown HTML.
	 * @param mixed[] $r      {
	 *    Arguments for building the dropdown. See wp_dropdown_pages() for complete docs.
	 *    @type string $name Name of the dropdown.
	 * }
	 * @return string
	 */
	public function add_modules_to_page_on_front_dropdown( $output, $r ) {
		if ( 'page_on_front' !== $r['name'] ) {
			return $output;
		}

		// Get a list of modules.
		$modules = Module::get();

		// Build <optgroup> for modules.
		$module_optgroup = '';
		if ( $modules ) {
			$module_optgroup = '<optgroup label="' . esc_attr__( 'Modules', 'openlab-modules' ) . '">';
			foreach ( $modules as $module ) {
				$module_optgroup .= sprintf(
					'<option value="%d"%s>%s</option>',
					$module->get_id(),
					selected( get_option( 'page_on_front' ), $module->get_id(), false ),
					esc_html( $module->get_title() )
				);
			}
			$module_optgroup .= '</optgroup>';
		}

		// Append the module <optgroup> to the dropdown.
		$output = str_replace( '</select>', $module_optgroup . '</select>', $output );

		return $output;
	}

	/**
	 * Sets a flag in blogmeta to indicate that the OpenLab Modules plugin is active.
	 *
	 * @return void
	 */
	public function set_blogmeta_flag() {
		if ( ! is_site_meta_supported() ) {
			return;
		}

		if ( get_site_meta( get_current_blog_id(), 'openlab_modules_active' ) ) {
			return;
		}

		update_site_meta( get_current_blog_id(), 'openlab_modules_active', '1' );
	}
}
