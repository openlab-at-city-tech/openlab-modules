<?php
/**
 * Module functionality.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Module object.
 */
class Module {
	/**
	 * Module ID.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {}

	/**
	 * Gets an instance corresponding to a module ID.
	 *
	 * @param int $module_id ID of the module.
	 * @return false|\OpenLab\Modules\Module False if no instance is found.
	 */
	public static function get_instance( $module_id ) {
		$post = self::get_post_by_id( $module_id );
		if ( ! $post ) {
			return false;
		}

		$module     = new self();
		$module->id = $module_id;

		return $module;
	}

	/**
	 * Gets the term ID corresponding to this module.
	 *
	 * @return int
	 */
	public function get_term_id() {
		return \HardG\CptTax\Registry::get_term_id_for_post_id( 'module', $this->id );
	}

	/**
	 * Gets a list of page IDs.
	 *
	 * @param string $type 'publish' to get only those items that are published. 'all' to get all'.
	 *
	 * @return int[]
	 */
	public function get_page_ids( $type = 'all' ) {
		// Stored as JSON for better manipulation in Block Editor.
		$module_page_ids_raw = get_post_meta( $this->id, 'module_page_ids', true );
		if ( ! is_string( $module_page_ids_raw ) ) {
			$module_page_ids_raw = '[]';
		}

		$page_ids_raw = json_decode( $module_page_ids_raw );
		if ( ! is_array( $page_ids_raw ) ) {
			$page_ids_raw = [];
		}

		$page_ids = [];
		foreach ( $page_ids_raw as $pid ) {
			if ( is_numeric( $pid ) ) {
				$page_ids[] = intval( $pid );
			}
		}

		if ( 'publish' === $type ) {
			$page_ids = array_filter(
				$page_ids,
				function ( $page_id ) {
					return 'publish' === get_post_status( $page_id );
				}
			);
		}

		return array_map( 'intval', $page_ids );
	}

	/**
	 * Links a page to the module.
	 *
	 * It will be placed at the end of the list.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public function link_page_to_module( $page_id ) {
		wp_set_object_terms( $page_id, [ $this->get_term_id() ], Schema::get_module_taxonomy(), true );

		$page_ids   = $this->get_page_ids();
		$page_ids[] = $page_id;

		$updated = update_post_meta( $this->id, 'module_page_ids', wp_json_encode( $page_ids ) );

		return (bool) $updated;
	}

	/**
	 * Unlinks a page from the module.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public function unlink_page_from_module( $page_id ) {
		wp_remove_object_terms( $page_id, [ $this->get_term_id() ], Schema::get_module_taxonomy() );

		$page_ids = array_filter(
			$this->get_page_ids(),
			function ( $linked_page_id ) use ( $page_id ) {
				return $linked_page_id !== $page_id;
			}
		);

		$updated = update_post_meta( $this->id, 'module_page_ids', wp_json_encode( $page_ids ) );

		return (bool) $updated;
	}

	/**
	 * Gets IDs of modules to which a page is linked.
	 *
	 * @param int $page_id ID of the page.
	 * @return int[] IDs of module CPT objects.
	 */
	public static function get_module_ids_of_page( $page_id ) {
		$terms = wp_get_object_terms( $page_id, Schema::get_module_taxonomy() );
		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$module_ids = array_map(
			function ( $term ) {
				return \HardG\CptTax\Registry::get_post_id_for_term_id( 'module', $term->term_id );
			},
			$terms
		);

		return array_filter( $module_ids );
	}

	/**
	 * Gets the completion popup text for a module page.
	 *
	 * At some point we may move this to a more logical place.
	 *
	 * @param int $page_id ID of the page.
	 * @return string
	 */
	public static function get_page_completion_popup_text( $page_id ) {
		$page_popup_text = get_post_meta( $page_id, 'completion_popup_text', true );
		if ( $page_popup_text && is_string( $page_popup_text ) ) {
			return $page_popup_text;
		}

		// Fall back on the module's popup text.
		$module_ids = self::get_module_ids_of_page( $page_id );
		if ( empty( $module_ids ) ) {
			return '';
		}

		$module_id = reset( $module_ids );
		$module    = self::get_instance( $module_id );
		if ( ! $module ) {
			return '';
		}

		return $module->get_completion_popup_text();
	}

	/**
	 * Gets the 'show completion popup' setting for a page.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public static function get_page_show_completion_popup( $page_id ) {
		$saved = get_post_meta( $page_id, 'show_completion_popup', true );

		// If not set, default to true.
		if ( '' === $saved ) {
			return true;
		}

		return (bool) $saved;
	}

	/**
	 * Gets the 'send completion email' setting for a page.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public static function get_page_send_completion_email( $page_id ) {
		$saved = get_post_meta( $page_id, 'send_completion_email', true );

		// If not set, default to true.
		if ( '' === $saved ) {
			return true;
		}

		return (bool) $saved;
	}

	/**
	 * Gets the 'include popup text in completion email' setting for a page.
	 *
	 * @param int $page_id ID of the page.
	 * @return bool
	 */
	public static function get_page_include_popup_text_in_completion_email( $page_id ) {
		$saved = get_post_meta( $page_id, 'include_popup_text_in_completion_email', true );

		// If not set, default to false.
		if ( '' === $saved ) {
			return false;
		}

		return (bool) $saved;
	}

	/**
	 * Gets the post associated with a module id.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|null
	 */
	protected static function get_post_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || Schema::get_module_post_type() !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Gets the post associated with the current module.
	 *
	 * @return \WP_Post|null
	 */
	protected function get_post() {
		return self::get_post_by_id( $this->id );
	}

	/**
	 * Gets the ID of the current module.
	 *
	 * @return int
	 */
	public function get_id() {
		return (int) $this->id;
	}

	/**
	 * Gets the title of the module.
	 *
	 * @return string
	 */
	public function get_title() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		return $post->post_title;
	}

	/**
	 * Gets the URL of the module.
	 *
	 * @return string
	 */
	public function get_url() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		return get_permalink( $post );
	}

	/**
	 * Gets the "navigation title" of the module.
	 *
	 * @return string
	 */
	public function get_nav_title() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		$nav_title = get_post_meta( $post->ID, 'module_nav_title', true );
		if ( ! is_string( $nav_title ) ) {
			$nav_title = '';
		}

		if ( empty( $nav_title ) ) {
			$nav_title = __( 'Module Home', 'openlab-modules' );
		}

		return $nav_title;
	}

	/**
	 * Gets the description of the module.
	 *
	 * @return string
	 */
	public function get_description() {
		$post = $this->get_post();

		if ( ! $post ) {
			return '';
		}

		$description = get_post_meta( $post->ID, 'module_description', true );

		if ( ! is_string( $description ) ) {
			$description = '';
		}

		return $description;
	}

	/**
	 * Gets the "raw" attribution data for the module.
	 *
	 * We call it "raw" because it's the data that corresponds to the current
	 * module. What's stored in the 'module_attribution' post meta may refer
	 * to the source module.
	 *
	 * @return array{user_id: int, post_id: int, site_id: int, user_url: string, user_name: string, post_url: string, post_title: string, text: string}
	 */
	public function get_raw_attribution_data() {
		$module_post = $this->get_post();

		$attribution_data = [
			'user_id'    => 0,
			'user_url'   => '',
			'user_name'  => '',
			'post_id'    => 0,
			'post_url'   => '',
			'post_title' => '',
			'site_id'    => 0,
			'text'       => '',
		];

		if ( ! $module_post ) {
			return $attribution_data;
		}

		$attribution_data['user_id']    = (int) $module_post->post_author;
		$attribution_data['user_url']   = bp_core_get_user_domain( $module_post->post_author );
		$attribution_data['user_name']  = bp_core_get_user_displayname( $module_post->post_author );
		$attribution_data['post_id']    = $module_post->ID;
		$attribution_data['post_url']   = (string) get_permalink( $module_post->ID );
		$attribution_data['post_title'] = $module_post->post_title;
		$attribution_data['site_id']    = get_current_blog_id();

		$attribution_data['text'] = sprintf(
			// translators: 1. Link to source module, 2. Link to source module author.
			__( '<span class="openlab-module-attribution-prefix">Attribution:</span> This module is based on %1$s by %2$s.', 'openlab-modules' ),
			'<a href="' . $attribution_data['post_url'] . '">' . $attribution_data['post_title'] . '</a>',
			'<a href="' . $attribution_data['user_url'] . '">' . $attribution_data['user_name'] . '</a>'
		);

		return $attribution_data;
	}

	/**
	 * Gets the attribution data for the module.
	 *
	 * @return array{user_id: int, post_id: int, site_id: int, user_url: string, user_name: string, post_url: string, post_title: string, text: string}
	 */
	public function get_attribution_data() {
		$default = [
			'user_id'    => 0,
			'post_id'    => 0,
			'site_id'    => 0,
			'user_url'   => '',
			'user_name'  => '',
			'post_url'   => '',
			'post_title' => '',
			'text'       => '',
		];

		$post = $this->get_post();

		if ( ! $post ) {
			return $default;
		}

		$attribution = get_post_meta( $post->ID, 'module_attribution', true );

		if ( ! is_array( $attribution ) ) {
			return $default;
		}

		$retval = [
			'user_id'    => isset( $attribution['user_id'] ) && is_scalar( $attribution['user_id'] ) ? (int) $attribution['user_id'] : $default['user_id'],
			'post_id'    => isset( $attribution['post_id'] ) ? (int) $attribution['post_id'] : $default['post_id'],
			'site_id'    => isset( $attribution['site_id'] ) ? (int) $attribution['site_id'] : $default['site_id'],
			'user_url'   => isset( $attribution['user_url'] ) ? (string) $attribution['user_url'] : '',
			'user_name'  => isset( $attribution['user_name'] ) ? (string) $attribution['user_name'] : '',
			'post_url'   => isset( $attribution['post_url'] ) ? (string) $attribution['post_url'] : '',
			'post_title' => isset( $attribution['post_title'] ) ? (string) $attribution['post_title'] : '',
			'text'       => isset( $attribution['text'] ) ? (string) $attribution['text'] : $default['text'],
		];

		return $retval;
	}

	/**
	 * Gets the attribution text for the module.
	 *
	 * @return string
	 */
	public function get_attribution_text() {
		$attribution_data = $this->get_attribution_data();

		return sprintf(
			// translators: 1. Link to source module, 2. Link to source module author.
			__( 'This module is based on %1$s by %2$s.', 'openlab-modules' ),
			'<a href="' . esc_url( $attribution_data['post_url'] ) . '">' . esc_html( $attribution_data['post_title'] ) . '</a>',
			'<a href="' . esc_url( $attribution_data['user_url'] ) . '">' . esc_html( $attribution_data['user_name'] ) . '</a>'
		);
	}

	/**
	 * Gets the completion message CC string, for use in the editor.
	 *
	 * @return string
	 */
	public function get_completion_message_cc_string() {
		return implode( ', ', $this->get_completion_message_cc_list() );
	}

	/**
	 * Gets the list of completion message CC addresses for this module.
	 *
	 * @return string[]
	 */
	public function get_completion_message_cc_list() {
		$completion_message_cc = get_post_meta( $this->id, 'completion_message_cc', true );

		if ( ! is_string( $completion_message_cc ) ) {
			$completion_message_cc = '';
		}

		return array_filter( array_map( 'trim', explode( ',', $completion_message_cc ) ) );
	}

	/**
	 * Gets the email subject for completion messages.
	 *
	 * @return string
	 */
	public function get_completion_message_subject() {
		$subject = get_post_meta( $this->id, 'completion_message_subject', true );

		if ( ! is_string( $subject ) ) {
			$subject = '';
		}

		return $subject;
	}

	/**
	 * Gets the email body format for completion messages.
	 *
	 * @return string
	 */
	public function get_completion_message_body_format() {
		$body_format = get_post_meta( $this->id, 'completion_message_body_format', true );

		if ( ! is_string( $body_format ) ) {
			$body_format = '';
		}

		return $body_format;
	}

	/**
	 * Gets the email body for completion messages.
	 *
	 * @param int $post_id ID of the post just marked as complete.
	 * @return string
	 */
	public function get_completion_message_body( $post_id ) {
		$body_format = $this->get_completion_message_body_format();

		if ( ! $body_format ) {
			$body_format = __(
				'Hi {{display_name}},

You have completed the following:

Module: {{module_title}} {{module_url}}
Section: {{section_title}} {{section_url}}

Well done!',
				'openlab-modules'
			);
		}

		$display_name = '';
		$user         = wp_get_current_user();
		if ( $user->exists() ) {
			$display_name = $user->display_name;
		}

		if ( function_exists( 'bp_get_loggedin_user_fullname' ) ) {
			$display_name = bp_get_loggedin_user_fullname();
		}

		$replacements = [
			'{{display_name}}'  => $display_name,
			'{{module_title}}'  => $this->get_title(),
			'{{module_url}}'    => $this->get_url(),
			'{{section_title}}' => get_the_title( $post_id ),
			'{{section_url}}'   => get_permalink( $post_id ),
		];

		foreach ( $replacements as $key => $value ) {
			$body_format = str_replace( $key, $value, $body_format );
		}

		return $body_format;
	}

	/**
	 * Gets the completion popup text for the module.
	 *
	 * @return string
	 */
	public function get_completion_popup_text() {
		$popup_text = get_post_meta( $this->id, 'completion_popup_text', true );

		if ( ! is_string( $popup_text ) ) {
			$popup_text = '';
		}

		return $popup_text;
	}

	/**
	 * Is sharing enable for this module?
	 *
	 * @return bool
	 */
	public function is_sharing_enabled() {
		$saved = get_post_meta( $this->id, 'enable_sharing', true );

		if ( '' === $saved && function_exists( 'openlab_group_can_be_cloned' ) && function_exists( 'openlab_get_group_id_by_blog_id' ) ) {
			$group_id = openlab_get_group_id_by_blog_id( get_current_blog_id() );
			if ( $group_id ) {
				return openlab_group_can_be_cloned( $group_id );
			}
		}

		return (bool) $saved;
	}

	/**
	 * Gets a list of requirements for this module.
	 *
	 * Currently limited to plugin requirements.
	 *
	 * @return array{plugins: array<string>} List of requirements.
	 */
	public function get_requirements() {
		$plugin_requirements = apply_filters( 'openlab_modules_module_requirements', [], $this->id );

		return [
			'plugins' => $plugin_requirements,
		];
	}

	/**
	 * Gets a ModuleData object representing this module.
	 *
	 * @return \OpenLab\Modules\ModuleData
	 */
	public function get_module_data() {
		$module_data = new ModuleData();

		$module_data->set_id( $this->id );
		$module_data->set_title( $this->get_title() );
		$module_data->set_content( get_post_field( 'post_content', $this->id ) );
		$module_data->set_description( $this->get_description() );
		$module_data->set_nav_title( $this->get_nav_title() );
		$module_data->set_slug( get_post_field( 'post_name', $this->id ) );
		$module_data->set_url( $this->get_url() );
		$module_data->set_enable_sharing( $this->is_sharing_enabled() );

		$page_ids = $this->get_page_ids( 'all' );
		foreach ( $page_ids as $page_id ) {
			$post = get_post( $page_id );
			if ( ! $post ) {
				continue;
			}

			$module_data->add_page(
				[
					'id'      => $page_id,
					'title'   => $post->post_title,
					'slug'    => $post->post_name,
					'url'     => get_permalink( $post ),
					'status'  => $post->post_status,
					'content' => $post->post_content,
				]
			);
		}

		$attachment_ids = [];
		$all_item_ids   = array_merge( $page_ids, [ $this->id ] );
		foreach ( $all_item_ids as $item_id ) {
			// First, get those items that are attached to the post.
			$item_attachment_ids = get_posts(
				[
					'post_type'      => 'attachment',
					'posts_per_page' => -1,
					'post_parent'    => $item_id,
					'fields'         => 'ids',
				]
			);

			// Next, parse post_content for attachment URLs, which may not have the current post as parent.
			$post = get_post( $item_id );
			if ( $post ) {
				$attachment_urls = [];
				preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $post->post_content, $matches );
				if ( ! empty( $matches[1] ) ) {
					$attachment_urls = $matches[1];
				}

				foreach ( $attachment_urls as $attachment_url ) {
					$attachment_id = attachment_url_to_postid( $attachment_url );
					if ( ! $attachment_id ) {
						continue;
					}

					if ( in_array( $attachment_id, $item_attachment_ids, true ) ) {
						continue;
					}

					$item_attachment_ids[] = $attachment_id;
				}
			}

			$attachment_ids[ $item_id ] = $item_attachment_ids;
		}

		$processed_attachment_ids = [];
		foreach ( $attachment_ids as $item_id => $item_attachment_ids ) {
			foreach ( $item_attachment_ids as $attachment_id ) {
				if ( in_array( $attachment_id, $processed_attachment_ids, true ) ) {
					continue;
				}

				$processed_attachment_ids[] = $attachment_id;

				$attachment_post = get_post( $attachment_id );

				$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				if ( ! is_string( $alt ) ) {
					$alt = '';
				}

				$attachment_data = [
					'id'      => $attachment_id,
					'url'     => (string) wp_get_attachment_url( $attachment_id ),
					'path'    => (string) get_attached_file( $attachment_id ),
					'alt'     => $alt,
					'title'   => $attachment_post ? $attachment_post->post_title : '',
					'content' => $attachment_post ? $attachment_post->post_content : '',
					'excerpt' => $attachment_post ? $attachment_post->post_excerpt : '',
					'item_id' => $item_id,
				];

				$module_data->add_attachment( $attachment_data );
			}
		}

		$module_data->set_attribution( $this->get_raw_attribution_data() );

		return $module_data;
	}

	/**
	 * Gets a list of modules.
	 *
	 * @return \OpenLab\Modules\Module[]
	 */
	public static function get() {
		$args = [
			'post_type'      => Schema::get_module_post_type(),
			'posts_per_page' => -1,
			'orderby'        => [ 'title' => 'ASC' ],
			'fields'         => 'ids',
		];

		$post_ids = get_posts( $args );

		$modules = array_map(
			function ( $post_id ) {
				return self::get_instance( $post_id );
			},
			$post_ids
		);

		return array_filter( $modules );
	}

	/**
	 * Generates the markup for an attribution block.
	 *
	 * @param string $attribution_text Attribution text.
	 * @return string
	 */
	public static function generate_attribution_block( $attribution_text ) {
		// Create a paragraph block with the attribution prefix and text.
		$paragraph_block = [
			'blockName'    => 'core/paragraph',
			'attrs'        => [
				'fontSize' => '14-px',
				'style'    => [
					'spacing' => [
						'margin'  => '0',
						'padding' => '0',
					],
				],
			],
			'innerBlocks'  => [],
			'innerHTML'    => [
				'<p class="has-14-px-font-size" style="margin:0;padding:0"><strong class="openlab-module-attribution-prefix" style="font-weight:700">Attribution:</strong> %s</p>',
				wp_kses_post( $attribution_text ),
			],
			'innerContent' => [
				sprintf(
					'<p class="has-14-px-font-size" style="margin:0;padding:0"><strong class="openlab-module-attribution-prefix" style="font-weight:700">Attribution:</strong> %s</p>',
					wp_kses_post( $attribution_text )
				),
			],
		];

		// Create an inner group block to hold the paragraph (with the attribution text class).
		$inner_group_block = [
			'blockName'    => 'core/group',
			'attrs'        => [
				'className' => 'openlab-modules-attribution-text',
			],
			'innerBlocks'  => [ $paragraph_block ],
			'innerContent' => [
				'<div class="wp-block-group openlab-modules-attribution-text">',
				null, // This will be replaced by the paragraph block.
				'</div>',
			],
		];

		// Create the outer group block with styling.
		$outer_group_block = [
			'blockName'    => 'core/group',
			'attrs'        => [
				'className' => 'openlab-modules-attribution-wrapper',
				'style'     => [
					'color'   => [
						'background' => '#efefef',
					],
					'spacing' => [
						'padding' => '20px',
					],
				],
			],
			'innerBlocks'  => [ $inner_group_block ],
			'innerContent' => [
				'<div class="wp-block-group openlab-modules-attribution-wrapper has-background" style="background-color:#efefef;padding:20px">',
				null, // This will be replaced by the inner group block.
				'</div>',
			],
		];

		return serialize_block( $outer_group_block );
	}

	/**
	 * Inserts an attribution block, swapping with existing ones if found.
	 *
	 * @param string $attribution_block Serialized block.
	 * @param string $post_content      Post content.
	 * @return string
	 */
	public static function insert_attribution_block( $attribution_block, $post_content ) {
		$original_post_content = $post_content;

		$regex            = '/<!-- wp:group[^>]+className:"openlab-modules-attribution-wrapper".*?<!-- \/wp:group -->/s';
		$style_regex      = '/<!-- wp:group[^>]+"background":"#efefef"[^>]+padding":"20px"[^>]*--.*?<!-- \/wp:group -->/s';
		$navigation_regex = '/<!-- wp:openlab-modules\/module-navigation[^>]*-->/s';

		if ( preg_match( $regex, $post_content, $matches ) ) {
			// Replace existing block with new block.
			$post_content = preg_replace( $regex, $attribution_block, $post_content );
		} elseif ( preg_match( $style_regex, $post_content, $matches ) ) {
			// Try the style-based regex as a fallback.
			$post_content = preg_replace( $style_regex, $attribution_block, $post_content );
		} elseif ( preg_match( $navigation_regex, $post_content ) ) {
			// Look for a openlab-modules/module-navigation block, and put it after that.
			$post_content = preg_replace( $navigation_regex, '$0' . $attribution_block, $post_content );
		} else {
			// Prepends the new block to the content.
			$post_content = $attribution_block . $post_content;
		}

		if ( null === $post_content ) {
			// If the regex fails, return the original content.
			return $original_post_content;
		}

		return $post_content;
	}
}
