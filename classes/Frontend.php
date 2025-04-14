<?php
/**
 * Handles frontend integration.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Handles frontend integration.
 */
class Frontend {
	/**
	 * Private constructor.
	 *
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Gets the singleton instance.
	 *
	 * @return \OpenLab\Modules\Frontend
	 */
	public static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Initializes frontend integration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );

		add_filter( 'the_content', [ __CLASS__, 'maybe_migrate_attribution_blocks' ], 5 );
		add_filter( 'the_content', [ __CLASS__, 'append_pagination' ], 15 );

		add_action( 'wp_ajax_mark_module_section_complete', [ __CLASS__, 'ajax_mark_module_section_complete' ] );
	}

	/**
	 * Registers assets for use on the frontend.
	 *
	 * @return void
	 */
	public function register_assets() {
		$blocks_asset_file = Editor::get_blocks_asset_file();

		wp_register_script(
			'openlab-modules-frontend',
			OPENLAB_MODULES_PLUGIN_URL . '/build/frontend.js',
			[ 'wp-api-fetch', 'wp-element', 'wp-i18n' ],
			$blocks_asset_file['version'],
			true
		);

		$current_page_permalink = get_permalink();

		wp_localize_script(
			'openlab-modules-frontend',
			'openlabModulesStrings',
			[
				'continueWithout'   => __( 'Continue without logging in', 'openlab-modules' ),
				'dismiss'           => __( 'Dismiss', 'openlab-modules' ),
				'logIn'             => __( 'Log In', 'openlab-modules' ),
				'sectionComplete'   => __( 'You have completed this page. You will receive a private message confirming the completion.', 'openlab-modules' ),
				'toReceiveCredit'   => __( 'To receive an official confirmation when you complete this page, please sign in now.', 'openlab-modules' ),
				'youAreNotLoggedIn' => __( 'You are not logged in.', 'openlab-modules' ),
			]
		);

		wp_add_inline_script(
			'openlab-modules-frontend',
			'const openlabModules = ' . wp_json_encode(
				[
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'isUserLoggedIn' => is_user_logged_in(),
					'loginUrl'       => wp_login_url( (string) $current_page_permalink ),
					'nonce'          => wp_create_nonce( 'openlab-modules' ),
					'postId'         => get_queried_object_id(),
				]
			),
			'before'
		);

		wp_register_style(
			'openlab-modules-frontend',
			OPENLAB_MODULES_PLUGIN_URL . '/build/frontend.css',
			[],
			$blocks_asset_file['version']
		);
	}

	/**
	 * Enqueues assets.
	 *
	 * For the time being, our frontend is loaded on every module or module page,
	 * since it's hard to detect whether they're needed based on the presence
	 * of integration embeds.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( self::get_module_id_for_post() ) {
			wp_enqueue_script( 'openlab-modules-frontend' );
			wp_enqueue_style( 'openlab-modules-frontend' );
		}
	}

	/**
	 * Appends module pagination.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public static function append_pagination( $content ) {
		// Only run once per pageload. If another plugin interferes, there's nothing we can do.
		remove_filter( 'the_content', [ __CLASS__, 'append_pagination' ] );

		if ( ! is_main_query() ) {
			return $content;
		}

		if ( ! is_singular() ) {
			return $content;
		}

		$module_id = self::get_module_id_for_post();

		if ( ! $module_id ) {
			return $content;
		}

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return $content;
		}

		$is_module = Schema::get_module_post_type() === get_post_type( get_queried_object_id() );

		// Offset keys so that the module page is 0.
		$module_page_ids = $module->get_page_ids();
		$all_page_ids    = array_merge( [ $module_id ], $module_page_ids );

		// Don't include non-published posts in pagination.
		$all_page_ids = array_filter(
			$all_page_ids,
			function ( $page_id ) {
				return 'publish' === get_post_status( $page_id );
			}
		);

		// Reindex the array, as some keys may have been removed.
		$all_page_ids = array_values( $all_page_ids );

		$current_index = $is_module ? 0 : array_search( get_queried_object_id(), $all_page_ids, true );

		if ( false === $current_index ) {
			return $content;
		}

		$current_index = (int) $current_index;

		$neighbors = [
			'prev' => $current_index > 0 ? $all_page_ids[ $current_index - 1 ] : null,
			'next' => ( $current_index + 1 ) < count( $all_page_ids ) ? $all_page_ids[ $current_index + 1 ] : null,
		];

		$links = [
			'prev' => null,
			'next' => null,
		];

		foreach ( $neighbors as $neighbor_type => $neighbor_id ) {
			if ( null === $neighbor_id ) {
				continue;
			}

			$page_title = $neighbor_id === $module_id ? $module->get_nav_title() : (string) get_the_title( $neighbor_id );

			$links[ $neighbor_type ] = [
				'title' => $page_title,
				'url'   => (string) get_permalink( $neighbor_id ),
			];
		}

		$prev_link = '';
		if ( $links['prev'] ) {
			$prev_link = sprintf(
				// translators: Title of previous post.
				esc_html__( 'Previous: %s', 'openlab-modules' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $links['prev']['url'] ),
					esc_html( $links['prev']['title'] )
				)
			);
		}

		$next_link = '';
		if ( $links['next'] ) {
			$next_link = sprintf(
				// translators: Title of next post.
				esc_html__( 'Next: %s', 'openlab-modules' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $links['next']['url'] ),
					esc_html( $links['next']['title'] )
				)
			);
		}

		$pagination = sprintf(
			'
			<div class="module-pagination">
				<div class="module-pagination-previous">%s</div>

				<div class="module-pagination-next">%s</div>
			</div>
			',
			$prev_link,
			$next_link
		);

		wp_enqueue_style( 'openlab-modules-frontend' );

		return $content . $pagination;
	}

	/**
	 * Marks a module section as complete.
	 *
	 * @return void
	 */
	public static function ajax_mark_module_section_complete() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'openlab-modules' ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'openlab-modules' ) ] );
		}

		if ( ! isset( $_POST['postId'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No post ID available', 'openlab-modules' ) ] );
		}

		$post_id = is_numeric( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'openlab-modules' ) ] );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$module_id = self::get_module_id_for_post( $post_id );

		if ( ! $module_id ) {
			return;
		}

		$is_module = Schema::get_module_post_type() === get_post_type( $post );

		/**
		 * Filter the completion message type.
		 *
		 * @param string $message_type Message type. Default is 'bp_messages'.
		 * @param int    $module_id    Module ID.
		 */
		$message_type = apply_filters( 'openlab_modules_completion_message_type', 'bp_messages', $module_id, $post_id );

		switch ( $message_type ) {
			case 'bp_messages':
				self::send_completion_message_bp_messages( $post_id, $module_id );
				break;

			default:
				break;
		}

		/**
		 * Fires after a module section is marked as complete.
		 *
		 * @param int    $post_id      Post ID.
		 * @param int    $module_id    Module ID.
		 * @param string $message_type Message type.
		 */
		do_action( 'openlab_modules_section_complete', $post_id, $module_id, $message_type );
	}

	/**
	 * Sends a completion message using BuddyPress private messages.
	 *
	 * @param int $post_id   Post ID.
	 * @param int $module_id Module ID.
	 * @return void
	 */
	protected static function send_completion_message_bp_messages( $post_id, $module_id ) {
		if ( ! function_exists( 'messages_new_message' ) ) {
			return;
		}

		$post = get_post( $post_id );

			// translators: 1. Module title, 2. Module URL.
		$module_infos = '<p>' . esc_html( sprintf( __( 'Module: %1$s %2$s', 'openlab-modules' ), get_the_title( $module_id ), get_permalink( $module_id ) ) ) . '</p>';

		if ( ! $is_module ) {
			// translators: 1. section title, 2. section URL.
			$module_infos .= '<p>' . esc_html( sprintf( __( 'Section: %1$s %2$s', 'openlab-modules' ), get_the_title( $post ), get_permalink( $post ) ) ) . '</p>';
		}

		$message_content = sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html__( 'You have completed a module section.', 'openlab-modules' ),
			$module_infos
		);

		$message_subject = sprintf(
			// translators: 1. Module title.
			__( 'Well done! You have completed a section of the module: %s', 'openlab-modules' ),
			get_the_title( $module_id )
		);

		$messages = \messages_new_message(
			[
				'sender_id'  => $post->post_author,
				'recipients' => bp_loggedin_user_id(),
				'subject'    => $message_subject,
				'content'    => $message_content,
			]
		);
	}

	/**
	 * Get module ID for a post ID.
	 *
	 * For modules, this returns the post ID itself. For module pages,
	 * this returns the module ID.
	 *
	 * @param int $post_id Optional. Post ID. Default is the current post.
	 * @return int|null
	 */
	public static function get_module_id_for_post( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		if ( Schema::get_module_post_type() === get_post_type( $post ) ) {
			return $post_id;
		}

		$module_ids = Module::get_module_ids_of_page( $post_id );
		if ( $module_ids ) {
			return $module_ids[0];
		}

		return null;
	}

	/**
	 * Just-in-time migration for attribution blocks.
	 *
	 * @param string $content The post content.
	 * @return string The filtered content.
	 */
	public static function maybe_migrate_attribution_blocks( $content ) {
		// Skip if no content or no post ID.
		if ( empty( $content ) ) {
			return $content;
		}

		// Get the fresh post content, which cannot yet have the blocks rendered.
		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		// Skip if no old attribution blocks.
		if ( ! str_contains( $content, '<!-- wp:openlab-modules/module-attribution' ) ) {
			return $content;
		}

		// Process and replace each attribution block.
		$updated_content = self::migrate_attribution_blocks( $content );

		// Only update if content has changed.
		if ( $content !== $updated_content ) {
			// Update the post in the database.
			wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => $updated_content,
				)
			);

			// Return the updated content.
			return $updated_content;
		}

		return $content;
	}

	/**
	 * Migrate old attribution blocks to new group blocks.
	 *
	 * @param string $content The post content.
	 * @return string The updated content.
	 */
	protected static function migrate_attribution_blocks( $content ) {
		$current_page_id = get_the_id();
		if ( ! $current_page_id ) {
			return $content;
		}

		// Get the attribution text for the module.
		$module = \OpenLab\Modules\Module::get_instance( $current_page_id );
		if ( ! $module ) {
			return $content;
		}

		$attribution_text = $module->get_attribution_text();

		$pattern = '/<!-- wp:openlab-modules\/module-attribution\s+({[^}]+})\s+\/-->/';

		$swapped = preg_replace_callback(
			$pattern,
			function () use ( $attribution_text ) {
				// Create a paragraph block with the attribution text.
				$paragraph_block = array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array(
						'fontSize' => '14-px',
						'style'    => array(
							'spacing' => array(
								'margin'  => '0',
								'padding' => '0',
							),
						),
					),
					'innerBlocks'  => array(),
					'innerHTML'    => sprintf(
						'<p class="has-14-px-font-size" style="margin:0;padding:0"><strong class="openlab-module-attribution-prefix" style="font-weight:700">Attribution:</strong> %s</p>',
						$attribution_text
					),
					'innerContent' => array(
						sprintf(
							'<p class="has-14-px-font-size" style="margin:0;padding:0"><strong class="openlab-module-attribution-prefix" style="font-weight:700">Attribution:</strong> %s</p>',
							$attribution_text
						),
					),
				);

				// Create an inner group block for the attribution text.
				$inner_group_block = array(
					'blockName'    => 'core/group',
					'attrs'        => array(
						'className' => 'openlab-modules-attribution-text',
					),
					'innerBlocks'  => array( $paragraph_block ),
					'innerHTML'    => '<div class="wp-block-group openlab-modules-attribution-text"></div>',
					'innerContent' => array(
						'<div class="wp-block-group openlab-modules-attribution-text">',
						null,
						'</div>',
					),
				);

				// Create the outer group block with styling.
				$outer_group_block = array(
					'blockName'    => 'core/group',
					'attrs'        => array(
						'className' => 'openlab-modules-attribution-wrapper',
						'style'     => array(
							'color'   => array(
								'background' => '#efefef',
							),
							'spacing' => array(
								'padding' => '20px',
							),
						),
					),
					'innerBlocks'  => array( $inner_group_block ),
					'innerHTML'    => '<div class="wp-block-group openlab-modules-attribution-wrapper has-background" style="background-color:#efefef;padding:20px"></div>',
					'innerContent' => array(
						'<div class="wp-block-group openlab-modules-attribution-wrapper has-background" style="background-color:#efefef;padding:20px">',
						null,
						'</div>',
					),
				);

				return serialize_block( $outer_group_block );
			},
			$content
		);

		if ( ! is_string( $swapped ) ) {
			return $content;
		}

		return $swapped;
	}
}
