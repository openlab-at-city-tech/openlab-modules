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
	 * @return bool|\WP_Error
	 */
	public static function import_module_to_site( $module_data, $destination_site_id ) {
		$id_map = [];

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

		$module_post = get_post( $module_id );
		if ( ! $module_post ) {
			return new \WP_Error( 'module_not_found', __( 'Module not found.', 'openlab-modules' ), [ 'status' => 404 ] );
		}

		$module_post_content = self::swap_urls( $module_post->post_content, $module_data->get_url(), (string) get_permalink( $module_id ) );
		$module_post_content = self::swap_module_navigation_module_ids( $module_post_content, $module_data->get_module_id(), $module_id );

		wp_update_post(
			[
				'ID'           => $module_id,
				'post_content' => $module_post_content,
			]
		);

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
				'post_status'  => 'publish',
				'post_type'    => 'page',
			];

			$page_id = wp_insert_post( $page_post_data, true );

			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}

			$page_post_content = self::swap_urls( $page_data['content'], $module_data->get_url(), (string) get_permalink( $page_id ) );
			$page_post_content = self::swap_module_navigation_module_ids( $page_post_content, $module_data->get_module_id(), $module_id );

			wp_update_post(
				[
					'ID'           => $page_id,
					'post_content' => $page_post_content,
				]
			);

			$id_map[ $page_data['id'] ] = $page_id;

			// Before linking linking, set the postmeta indicating that navigation has been inserted.
			update_post_meta( $page_id, 'openlab_modules_inserted_navigation_' . $module_id, '1' );

			$module->link_page_to_module( $page_id );
		}

		_b( $module_id );
		_b( $id_map );

		restore_current_blog();
	}

	/**
	 * Swaps URLs in content.
	 *
	 * @param string $content         Content.
	 * @param string $source_url      Source URL.
	 * @param string $destination_url Destination URL.
	 * @return string
	 */
	protected static function swap_urls( $content, $source_url, $destination_url ) {
		$source_url      = trailingslashit( $source_url );
		$destination_url = trailingslashit( $destination_url );

		$content = str_replace( $source_url, $destination_url, $content );

		return $content;
	}

	/**
	 * Swaps moduleId of module-navigation blocks in content.
	 *
	 * @param string $content               Content.
	 * @param int    $source_module_id      Source module ID.
	 * @param int    $destination_module_id Destination module ID.
	 * @return string
	 */
	protected static function swap_module_navigation_module_ids( $content, $source_module_id, $destination_module_id ) {
		$source_module_id      = (string) $source_module_id;
		$destination_module_id = (string) $destination_module_id;

		$content = preg_replace(
			'|wp:openlab-modules/module-navigation \{"moduleId":(\d+)\}|',
			'wp:openlab-modules/module-navigation {"moduleId":' . $destination_module_id . '}',
			$content
		);

		return (string) $content;
	}
}
