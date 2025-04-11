<?php
/**
 * Handles editor functionality.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Handles editor functionality.
 */
class Editor {
	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Editor
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}
	/**
	 * Initializes Editor integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_dynamic_blocks' ] );

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_assets' ] );

		add_action( 'save_post', [ $this, 'link_to_module_on_post_creation' ] );

		add_filter( 'use_block_editor_for_post', [ $this, 'use_block_editor_for_module' ], 200, 2 );

		add_action( 'after_setup_theme', [ $this, 'register_custom_font_sizes' ], 20 );
	}

	/**
	 * Gets block asset info from the build directory.
	 *
	 * Used when enqueuing assets, to help with cache busting during development.
	 *
	 * @access protected
	 *
	 * @param string $asset_handle Asset handle. Defaults to 'index'.
	 * @return array{"dependencies": string[], "version": string}
	 */
	public static function get_blocks_asset_file( $asset_handle = 'index' ) {
		$blocks_dir        = ROOT_DIR . '/build/';
		$blocks_asset_file = include $blocks_dir . $asset_handle . '.asset.php';

		// Replace "wp-blockEditor" with "wp-block-editor".
		$blocks_asset_file['dependencies'] = array_replace(
			$blocks_asset_file['dependencies'],
			array_fill_keys(
				array_keys( $blocks_asset_file['dependencies'], 'wp-blockEditor', true ),
				'wp-block-editor'
			)
		);

		return $blocks_asset_file;
	}

	/**
	 * Registers dynamic blocks.
	 *
	 * These are the blocks that are rendered on the server in PHP, and so
	 * much be registered with WP in PHP.
	 *
	 * @return void
	 */
	public function register_dynamic_blocks() {
		register_block_type(
			'openlab-modules/module-list',
			[
				'api_version'     => '2',
				'attributes'      => [
					'orderedIds' => [
						'type'    => 'array',
						'default' => [],
					],
				],
				'render_callback' => function ( $attributes, $content ) {
					return $this->render_block( 'module-list', $attributes, $content );
				},
			]
		);

		register_block_type(
			'openlab-modules/module-navigation',
			[
				'api_version'     => '2',
				'attributes'      => [
					'moduleId' => [
						'type'    => 'integer',
						'default' => 0,
					],
				],
				'render_callback' => function ( $attributes, $content ) {
					return $this->render_block( 'module-navigation', $attributes, $content );
				},
			]
		);

		register_block_type(
			'openlab-modules/placeholder-text',
			[
				'api_version'     => '2',
				'attributes'      => [
					'textContent' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'render_callback' => '__return_empty_string',
			]
		);

		register_block_type(
			'openlab-modules/sharing',
			[
				'api_version'     => '2',
				'attributes'      => [],
				'render_callback' => function ( $attributes, $content ) {
					return $this->render_block( 'sharing', $attributes, $content );
				},
			]
		);
	}

	/**
	 * Block rendering method.
	 *
	 * Loads from templates/blocks.
	 *
	 * @param string  $block_type Block type.
	 * @param mixed[] $attributes Attribute array.
	 * @param string  $content    Block content.
	 * @return string
	 */
	public function render_block( $block_type, $attributes, $content ) {
		$template_args = array_merge(
			$attributes,
			array(
				'content' => $content,
			)
		);

		$template_name = $block_type . '.php';

		// Allow theme overrides.
		$located = locate_template( $template_name );
		if ( ! $located ) {
			$located = ROOT_DIR . '/templates/blocks/' . $template_name;
		}

		ob_start();
		load_template( $located, false, $template_args );
		$contents = ob_get_contents();
		ob_end_clean();

		return (string) $contents;
	}

	/**
	 * Enqueue block assets on the Dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
		$blocks_asset_file = self::get_blocks_asset_file();

		wp_enqueue_script(
			'openlab-modules-dashboard',
			OPENLAB_MODULES_PLUGIN_URL . '/build/index.js',
			$blocks_asset_file['dependencies'],
			$blocks_asset_file['version'],
			true
		);

		wp_enqueue_style(
			'openlab-modules-dashboard',
			OPENLAB_MODULES_PLUGIN_URL . '/build/index.css',
			[],
			$blocks_asset_file['version']
		);
	}

	/**
	 * Links a post to a module on post creation, if necessary.
	 *
	 * @param int $post_id ID of the post.
	 * @return void
	 */
	public function link_to_module_on_post_creation( $post_id ) {
		$link_to_module = get_post_meta( $post_id, 'link_to_module', true );
		if ( ! $link_to_module || ! is_numeric( $link_to_module ) ) {
			return;
		}

		$module = Module::get_instance( (int) $link_to_module );
		if ( ! $module ) {
			return;
		}

		if ( $module->link_page_to_module( $post_id ) ) {
			delete_post_meta( $post_id, 'link_to_module' );
		}
	}

	/**
	 * Recursive version of serialize_block().
	 *
	 * @param mixed[] $block Block definition. See `serialize_block()`.
	 * @return string
	 */
	public static function serialize_block_recursive( $block ) {
		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return serialize_block( $block );
		}

		$inner_content = [];
		foreach ( $block['innerBlocks'] as $inner_block ) {
			$inner_content[] = self::serialize_block_recursive( $inner_block );
		}

		$block['innerContent'] = $inner_content;

		return serialize_block( $block );
	}

	/**
	 * Determines whether the block editor should be used for a given post type.
	 *
	 * @param bool     $use_block_editor Whether to use the block editor.
	 * @param \WP_Post $post             Post object.
	 * @return bool
	 */
	public function use_block_editor_for_module( $use_block_editor, $post ) {
		if ( Schema::get_module_post_type() === $post->post_type ) {
			return true;
		}

		return $use_block_editor;
	}

	/**
	 * Register custom font sizes for the editor.
	 */
	public function register_custom_font_sizes() {
		// Get existing font sizes if they exist.
		$existing_font_sizes = [];
		$existing_support    = get_theme_support( 'editor-font-sizes' );

		if ( $existing_support && is_array( $existing_support ) && ! empty( $existing_support[0] ) ) {
			$existing_font_sizes = $existing_support[0];
		}

		// Check if our font size already exists (to avoid duplicates).
		$has_attribution_size = false;
		foreach ( $existing_font_sizes as $font_size ) {
			if ( isset( $font_size['slug'] ) && '14-px' === $font_size['slug'] ) {
				$has_attribution_size = true;
				break;
			}
		}

		// Only add our font size if it doesn't already exist.
		if ( ! $has_attribution_size ) {
			$existing_font_sizes[] = array(
				'name'      => __( '14px', 'openlab-modules' ),
				'shortName' => __( '14px', 'openlab-modules' ),
				'size'      => 14,
				'slug'      => '14-px',
			);
		}

		// Remove existing support before adding it again with our additions.
		remove_theme_support( 'editor-font-sizes' );
		add_theme_support( 'editor-font-sizes', $existing_font_sizes );

		// Add custom CSS for classes that won't be edited by users.
		add_action(
			'wp_head',
			function () {
				echo '<style>
					.has-14-px-font-size {
						font-size: 14px;
					}
					.openlab-module-attribution-prefix {
						font-weight: 700;
					}
				</style>';
			}
		);

		// Add the same styles to the editor.
		add_action(
			'admin_head',
			function () {
				echo '<style>
					.has-14-px-font-size {
						font-size: 14px;
					}
					.openlab-module-attribution-prefix {
						font-weight: 700;
					}
				</style>';
			}
		);
	}
}
