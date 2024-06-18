<?php
/**
 * Cloner.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Cloner.
 */
class Cloner {
	/**
	 * Gets module data object.
	 *
	 * @param int $module_id Module ID.
	 * @return ModuleData|\WP_Error
	 */
	public static function get_module_data( $module_id ) {
		$module = Module::get_instance( $module_id );

		if ( ! $module ) {
			return new \WP_Error( 'module_not_found', __( 'Module not found.', 'openlab-modules' ), [ 'status' => 404 ] );
		}

		return $module->get_module_data();
	}

	/**
	 * Imports a module to a site.
	 *
	 * @param ModuleData $module_data Module data object.
	 * @param int        $destination_site_id Destination site ID.
	 * @return array{clone_url: string}|\WP_Error
	 */
	public static function import_module_to_site( ModuleData $module_data, $destination_site_id ) {
		$id_map  = [];
		$url_map = [];

		// Create the module first, so we have the new module ID.
		$module_post_data = [
			'post_title'   => $module_data->get_title(),
			'post_content' => $module_data->get_content(),
			'post_name'    => $module_data->get_slug(),
			'post_status'  => 'publish',
			'post_type'    => Schema::get_module_post_type(),
		];

		switch_to_blog( $destination_site_id );

		$module_id = wp_insert_post( $module_post_data, true );

		if ( is_wp_error( $module_id ) ) {
			return $module_id;
		}

		$id_map[ $module_data->get_module_id() ] = $module_id;
		$url_map[ $module_data->get_url() ]      = (string) get_permalink( $module_id );

		$module_post = get_post( $module_id );
		if ( ! $module_post ) {
			return new \WP_Error( 'module_not_found', __( 'Module not found.', 'openlab-modules' ), [ 'status' => 404 ] );
		}

		$attribution_data = $module_data->get_attribution();
		update_post_meta( $module_id, 'module_attribution', $attribution_data );

		$module = Module::get_instance( $module_id );
		if ( ! $module ) {
			return new \WP_Error( 'module_not_found', __( 'Module not found.', 'openlab-modules' ), [ 'status' => 404 ] );
		}

		// Create the pages.
		$module_pages = $module_data->get_pages();
		foreach ( $module_pages as $page_data ) {
			$page_post_data = [
				'post_title'   => $page_data['title'],
				'post_content' => $page_data['content'],
				'post_name'    => $page_data['slug'],
				'post_status'  => $page_data['status'],
				'post_type'    => 'page',
			];

			$page_id = wp_insert_post( $page_post_data, true );

			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}

			$id_map[ $page_data['id'] ] = $page_id;

			// Before linking linking, set the postmeta indicating that navigation has been inserted.
			update_post_meta( $page_id, 'openlab_modules_inserted_navigation_' . $module_id, '1' );

			$module->link_page_to_module( $page_id );
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		foreach ( $module_data->get_attachments() as $attachment ) {
			$source_path = $attachment['path'];
			$temp_file   = download_url( $attachment['url'] );

			if ( is_wp_error( $temp_file ) ) {
				continue; // Handle the error or skip the attachment.
			}

			$file_array = [
				'name'     => basename( $source_path ),
				'tmp_name' => $temp_file,
			];

			$attachment_id = media_handle_sideload( $file_array, 0 );

			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_file( $file_array['tmp_name'] );
				continue; // Handle the error or skip the attachment.
			}

			$id_map[ $attachment['id'] ]   = $attachment_id;
			$url_map[ $attachment['url'] ] = (string) wp_get_attachment_url( $attachment_id );

			// Update attachment metadata.
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $attachment['alt'] );

			wp_update_post(
				[
					'ID'           => $attachment_id,
					'post_title'   => $attachment['title'],
					'post_content' => $attachment['content'],
					'post_excerpt' => $attachment['excerpt'],
				]
			);

			// Link the attachment to the correct item.
			if ( isset( $id_map[ $attachment['item_id'] ] ) ) {
				$item_id = $id_map[ $attachment['item_id'] ];
				wp_update_post(
					[
						'ID'          => $attachment_id,
						'post_parent' => $item_id,
					]
				);
			}
		}

		// Update URLs and IDs in the module and pages content.
		$module_post_content = self::swap_urls_and_ids_in_content( $module_post->post_content, $url_map, $id_map );
		$module_post_content = self::swap_module_navigation_module_ids( $module_post_content, $module_data->get_module_id(), $module_id );
		$module_post_content = self::swap_module_attribution_block( $module_post_content, $module_id );
		$module_post_content = self::delete_sharing_blocks( $module_post_content );

		wp_update_post(
			[
				'ID'           => $module_id,
				'post_content' => $module_post_content,
			]
		);

		foreach ( $module_pages as $page_data ) {
			$page_id   = $id_map[ $page_data['id'] ];
			$page_post = get_post( $page_id );

			if ( $page_post ) {
				$page_post_content = self::swap_urls_and_ids_in_content( $page_post->post_content, $url_map, $id_map );
				$page_post_content = self::swap_module_navigation_module_ids( $page_post_content, $module_data->get_module_id(), $module_id );
				wp_update_post(
					[
						'ID'           => $page_id,
						'post_content' => $page_post_content,
					]
				);
			}
		}

		$module_url = get_permalink( $module_id );

		restore_current_blog();

		return [
			'clone_url' => (string) $module_url,
		];
	}

	/**
	 * Swaps moduleId of module-navigation blocks in content.
	 *
	 * @param string $content               Content.
	 * @param int    $source_module_id      Source module ID.
	 * @param int    $destination_module_id Destination module ID.
	 * @return string
	 */
	public static function swap_module_navigation_module_ids( $content, $source_module_id, $destination_module_id ) {
		$source_module_id      = (string) $source_module_id;
		$destination_module_id = (string) $destination_module_id;

		$content = preg_replace(
			'|wp:openlab-modules/module-navigation \{"moduleId":(\d+)\}|',
			'wp:openlab-modules/module-navigation {"moduleId":' . $destination_module_id . '}',
			$content
		);

		return (string) $content;
	}

	/**
	 * Swaps attachment URLs and IDs in content.
	 *
	 * @param string   $post_content Post content.
	 * @param string[] $url_map      URL map.
	 * @param int[]    $id_map       ID map.
	 * @return string
	 */
	public static function swap_urls_and_ids_in_content( $post_content, $url_map, $id_map ) {
		// Replace all URLs in the content.
		$post_content = str_replace( array_keys( $url_map ), array_values( $url_map ), $post_content );

		// Replace IDs in wp:image blocks.
		$post_content = preg_replace_callback(
			'/<!-- wp:image \{.*?"id":(\d+).*?\} -->/i',
			function ( $matches ) use ( $id_map ) {
				$old_id = $matches[1];
				$new_id = isset( $id_map[ $old_id ] ) ? $id_map[ $old_id ] : $old_id;
				return str_replace( '"id":' . $old_id, '"id":' . $new_id, $matches[0] );
			},
			$post_content
		);

		// Replace wp-image- classes in img tags.
		$post_content = preg_replace_callback(
			'/class=["\']([^"\']*wp-image-)(\d+)([^"\']*)["\']/i',
			function ( $matches ) use ( $id_map ) {
				$old_id = $matches[2];
				$new_id = isset( $id_map[ $old_id ] ) ? $id_map[ $old_id ] : $old_id;
				return 'class="' . $matches[1] . $new_id . $matches[3] . '"';
			},
			(string) $post_content
		);

		return (string) $post_content;
	}

	/**
	 * Add an attribution block, deleting existing ones if necessary.
	 *
	 * @param string $post_content Post content.
	 * @param int    $module_id    ID of the newly created module on the clone destination site.
	 * @return string
	 */
	public static function swap_module_attribution_block( $post_content, $module_id ) {
		// First build the inner openlab-modules/module-attribution block.
		$inner_block = [
			'blockName'    => 'openlab-modules/module-attribution',
			'attrs'        => [
				'moduleId' => $module_id,
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];

		$inner_block_serialized = serialize_block( $inner_block );

		$outer_block = [
			'blockName'    => 'core/group',
			'attrs'        => [
				'style'     => [
					'color'   => [
						'background' => '#efefef',
					],
					'spacing' => [
						'padding' => '20px',
					],
				],
				'className' => 'openlab-modules-module-attribution-wrapper',
				'layout'    => [
					'type' => 'constrained',
				],
			],
			'innerBlocks'  => array( $inner_block ),
			'innerHTML'    => '<div class="wp-block-group openlab-modules-module-attribution-wrapper has-background" style="background-color:#efefef;padding:20px"></div>',
			'innerContent' => array(
				'<div class="wp-block-group openlab-modules-module-attribution-wrapper has-background" style="background-color:#efefef;padding:20px">',
				$inner_block_serialized,
				'</div>',
			),
		];

		// Serialize the new block.
		$block_markup = serialize_block( $outer_block );

		// Look for existing attribution wrapper group blocks.
		$regex = '/<!-- wp:group[^>]+className:"openlab-modules-module-attribution-wrapper".*?<!-- \/wp:group -->/s';

		if ( preg_match( $regex, $post_content, $matches ) ) {
			// Replace existing block with new block.
			$post_content = preg_replace( $regex, $block_markup, $post_content );
		} else {
			// Look for a openlab-modules/sharing block, and put it before that.
			$sharing_regex = '/<!-- wp:openlab-modules\/sharing[^>]*-->/s';
			$post_content  = preg_replace( $sharing_regex, $block_markup . '$0', $post_content );
		}

		return (string) $post_content;
	}

	/**
	 * Delete sharing blocks.
	 *
	 * @param string $post_content Post content.
	 * @return string
	 */
	public static function delete_sharing_blocks( $post_content ) {
		$sharing_regex = '/<!-- wp:openlab-modules\/sharing[^>]*-->/s';
		$post_content  = preg_replace( $sharing_regex, '', $post_content );

		return (string) $post_content;
	}
}
