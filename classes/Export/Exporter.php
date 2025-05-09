<?php
/**
 * Exporter Class.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Export;

use WP_Error;
use ZipArchive;
use OpenLab\Modules\Iterator\UploadsIterator;
use OpenLab\Modules\Module;

/**
 * Exporter class.
 */
class Exporter {

	/**
	 * Files to export.
	 *
	 * @var array<string>
	 */
	protected $files = [];

	/**
	 * Module ID.
	 *
	 * @var int
	 */
	protected $module_id = 0;

	/**
	 * Custom text to be appended to the readme file.
	 *
	 * @var string
	 */
	protected $readme_custom_text;

	/**
	 * Custom text for the auto-generated Acknowledgements page.
	 *
	 * @var string
	 */
	protected $acknowledgements_text;

	/**
	 * ID of the auto-generated Acknowledgements page.
	 *
	 * @var int
	 */
	protected $acknowledgements_page_id;

	/**
	 * Text of the readme file.
	 *
	 * @var string
	 */
	protected $readme_text;

	/**
	 * Cached version of 'basedir' from `wp_upload_dir()`.
	 *
	 * @var string
	 */
	protected $uploads_dir_basedir;

	/**
	 * Cached version of 'baseurl' from `wp_upload_dir()`.
	 *
	 * @var string
	 */
	protected $uploads_dir_baseurl;

	/**
	 * Exports directory.
	 *
	 * @var string
	 */
	public $exports_dir;

	/**
	 * Exports URL
	 *
	 * @var string
	 */
	public $exports_url;

	/**
	 * Original post_content for module post.
	 *
	 * @var string
	 */
	protected $original_post_content;

	/**
	 * Module page IDs.
	 *
	 * @var array<int>
	 */
	protected $module_pages = [];

	/**
	 * Create export object.
	 *
	 * @param string $upload_dir_basedir Upload directory base directory.
	 * @param string $upload_dir_baseurl Upload directory base URL.
	 */
	public function __construct( $upload_dir_basedir, $upload_dir_baseurl ) {
		$this->uploads_dir_basedir = $upload_dir_basedir;
		$this->uploads_dir_baseurl = $upload_dir_baseurl;

		$this->exports_dir = trailingslashit( $this->uploads_dir_basedir ) . 'openlab-modules-exports/';
		$this->exports_url = trailingslashit( $this->uploads_dir_baseurl ) . 'openlab-modules-exports/';
	}

	/**
	 * Start export process.
	 *
	 * @return \WP_Error|string
	 */
	public function run() {
		$dest = $this->create_dest();

		if ( is_wp_error( $dest ) ) {
			return $dest;
		}

		$this->delete_previous_export_files();

		$this->insert_acknowledgements_block();

		$export = $this->create_wxp();
		if ( is_wp_error( $export ) ) {
			return $export;
		}

		$this->delete_acknowledgements_block();

		$this->prepare_files( $this->uploads_dir_basedir );

		$this->prepare_readme();

		return $this->archive();
	}

	/**
	 * Set ID of the module to be exported.
	 *
	 * @param int $module_id ID of the module to be exported.
	 * @return void
	 */
	public function set_module_id( $module_id ) {
		$this->module_id = $module_id;
	}

	/**
	 * Adds custom text for the end of the readme file.
	 *
	 * @param string $text Text to be added to the readme file.
	 * @return void
	 */
	public function add_readme_custom_text( $text ) {
		$this->readme_custom_text = $text;
	}

	/**
	 * Adds acknowledgements text for the auto-generated Acknowledgements page.
	 *
	 * @param string $text Text to be added to the Acknowledgements page.
	 * @return void
	 */
	public function add_acknowledgements_text( $text ) {
		$this->acknowledgements_text = $text;
	}

	/**
	 * Create export destination.
	 *
	 * @return \WP_Error|bool
	 */
	protected function create_dest() {
		if ( ! wp_mkdir_p( $this->exports_dir ) ) {
			return new WP_Error( 'ol.exporter.create.dest', 'Unable to create export folder.' );
		}

		return true;
	}

	/**
	 * Delete previous export files.
	 *
	 * Prevents the export directory from getting too large due to aborted exports.
	 *
	 * @return void
	 */
	protected function delete_previous_export_files() {
		$files = glob( $this->exports_dir . '*' );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Inserts an acknowledgements block into the module.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function insert_acknowledgements_block() {
		if ( empty( $this->acknowledgements_text ) ) {
			return;
		}

		$module_post = get_post( $this->module_id );
		if ( ! $module_post ) {
			return;
		}

		$this->original_post_content = $module_post->post_content;

		$block_markup = Module::generate_attribution_block( $this->acknowledgements_text );
		$new_content  = Module::insert_attribution_block( $block_markup, $module_post->post_content );

		$module_post->post_content = $new_content;

		wp_update_post(
			[
				'ID'           => $this->module_id,
				'post_content' => $new_content,
			]
		);
	}

	/**
	 * Deletes the auto-generated Acknowledgements block.
	 *
	 * We do this by restoring the original post content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function delete_acknowledgements_block() {
		if ( empty( $this->acknowledgements_page_id ) ) {
			return;
		}

		if ( empty( $this->original_post_content ) ) {
			return;
		}

		wp_update_post(
			[
				'ID'           => $this->module_id,
				'post_content' => $this->original_post_content,
			]
		);
	}

	/**
	 * Prepare backups files. Image uploads, etc.
	 *
	 * @param string $folder Folder to prepare.
	 * @return \WP_Error|void
	 */
	protected function prepare_files( $folder ) {
		$folder = trailingslashit( $folder );

		if ( ! is_dir( $folder ) ) {
			return new WP_Error(
				'ol.exporter.prepare.files',
				sprintf( 'Folder %s does not exist.', $folder )
			);
		}

		if ( ! is_readable( $folder ) ) {
			return new WP_Error(
				'ol.exporter.prepare.files',
				sprintf( 'Folder %s is not readable.', $folder )
			);
		}

		// Get a list of all posts/pages in the module.
		$item_ids = [ $this->module_id ];
		$item_ids = array_merge( $item_ids, $this->get_module_pages() );

		// Get the relative path of $folder, which we'll use as a sniff.
		$folder = str_replace( $this->uploads_dir_basedir, '', $folder );

		// Crawl them and look for uploads.
		foreach ( $item_ids as $item_id ) {
			$item = get_post( $item_id );
			if ( ! $item ) {
				continue;
			}

			$matches = [];
			preg_match_all(
				'#(https?://[^/]+)?' . preg_quote( $this->uploads_dir_baseurl, '#' ) . '((.*?)(\.(jpg|jpeg|png|gif)))#i',
				$item->post_content,
				$matches
			);

			if ( ! empty( $matches[2] ) ) {
				foreach ( $matches[2] as $match ) {
					$this->files[] = trailingslashit( $this->uploads_dir_basedir ) . $match;
				}
			}
		}
	}

	/**
	 * Generates the text of the readme file.
	 *
	 * @return void
	 */
	protected function prepare_readme() {
		$admin_names = $this->get_site_admin_names();

		$text = esc_html__( 'Acknowledgements', 'openlab-modules' );

		$converter = new \League\HTMLToMarkdown\HtmlConverter();

		$text .= "\n\n";
		$text .= $converter->convert( $this->acknowledgements_text );

		if ( ! empty( $this->readme_custom_text ) ) {
			$text .= "\n\n";
			$text .= '# ' . esc_html__( 'Note from Exporter', 'openlab-modules' );
			$text .= "\n\n";
			$text .= $this->readme_custom_text;
		}

		$text .= "\n\n";

		$text .= '# ' . esc_html__( 'Theme, Plugins, and Menu', 'openlab-modules' );
		$text .= "\n\n";

		$text .= esc_html__( 'The exported site uses the theme and plugins listed below. If you want your site to have the same appearance and features, you will need to install (if necessary) and activate the theme and plugins before you import.', 'openlab-modules' );

		$active_theme = wp_get_theme( get_stylesheet() );

		$theme_uri = $this->get_theme_uri( get_stylesheet() );
		if ( ! $theme_uri ) {
			$theme_uri = $active_theme->get( 'ThemeURI' );
		}

		$text .= "\n\n";
		$text .= esc_html__( 'Theme:', 'openlab-modules' );
		$text .= "\n";
		$text .= sprintf(
			'* %s: %s',
			esc_html( $active_theme->name ),
			esc_html( $theme_uri )
		);
		$text .= "\n\n";

		$text .= esc_html__( 'Plugins:', 'openlab-modules' );
		$text .= "\n";

		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			if ( ! is_plugin_active( $plugin_file ) ) {
				continue;
			}

			if ( is_plugin_active_for_network( $plugin_file ) ) {
				continue;
			}

			$plugin_uri = $this->get_plugin_uri( $plugin_file );
			if ( ! $plugin_uri && ! empty( $plugin_data['PluginURI'] ) ) {
				$plugin_uri = $plugin_data['PluginURI'];
			}

			if ( ! empty( $plugin_uri ) ) {
				$text .= sprintf(
					'* %s: %s',
					esc_html( $plugin_data['Name'] ),
					esc_html( $plugin_uri )
				);
			} else {
				$text .= sprintf(
					'* %s',
					esc_html( $plugin_data['Name'] ),
				);
			}
			$text .= "\n";
		}

		$this->readme_text = $text;
	}

	/**
	 * Gets a list of names of administrators for the current site.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	protected function get_site_admin_names() {
		$admin_names = array_map(
			function( $user ) {
				return $user->display_name;
			},
			get_users( [ 'role' => 'administrator' ] )
		);
		return $admin_names;
	}

	/**
	 * Gets a wordpress.org download URI for a plugin file.
	 *
	 * @param string $plugin_file The plugin file path.
	 * @return string
	 */
	protected function get_plugin_uri( $plugin_file ) {
		$pf_parts    = explode( '/', $plugin_file );
		$plugin_slug = $pf_parts[0];

		return $this->get_download_uri( $plugin_slug, 'plugins' );
	}

	/**
	 * Gets a wordpress.org download URI for a theme.
	 *
	 * @param string $theme The theme slug.
	 * @return string
	 */
	protected function get_theme_uri( $theme ) {
		return $this->get_download_uri( $theme, 'themes' );
	}

	/**
	 * Gets a wordpress.org download URI for a theme or plugin.
	 *
	 * @param string $slug The slug of the plugin or theme.
	 * @param string $type 'plugins' or 'themes'.
	 * @return string
	 */
	protected function get_download_uri( $slug, $type ) {
		$cached = get_transient( 'download_uri_' . $slug );
		if ( $cached && is_string( $cached ) ) {
			return $cached;
		}

		if ( ! in_array( $type, [ 'plugins', 'themes' ], true ) ) {
			return '';
		}

		$response = wp_remote_post(
			"http://api.wordpress.org/$type/info/1.0/$slug.xml",
			[
				'body' => [
					'action' => 'plugins' === $type ? 'plugin_information' : 'theme_information',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$link = '';
		} else {
			$link = "https://wordpress.org/$type/$slug";
		}

		set_transient( 'download_uri_' . $slug, $link, DAY_IN_SECONDS );

		return $link;
	}

	/**
	 * Create export WXP.
	 *
	 * @return \WP_Error|bool
	 */
	protected function create_wxp() {
		// phpcs:ignore WordPress.WP
		$wxp = new WXP( $this->exports_dir . 'wordpress.xml' );

		$wxp->set_module_id( $this->module_id );
		$wxp->set_module_pages( $this->get_module_pages() );
		$wxp->set_attachments( $this->get_attachments() );

		if ( ! $wxp->create() ) {
			return new WP_Error(
				'ol.exporter.create.wxp',
				'Unable to create WXP export file.'
			);
		}

		return true;
	}

	/**
	 * Gets a list of IDs of pages belonging to the module.
	 *
	 * @return array<int>
	 */
	protected function get_module_pages() {
		if ( ! empty( $this->module_pages ) ) {
			return $this->module_pages;
		}

		$module = Module::get_instance( $this->module_id );
		if ( ! $module ) {
			return [];
		}

		$this->module_pages = $module->get_page_ids();

		return $this->module_pages;
	}

	/**
	 * Gets a list of IDs belonging to attachments that should be included in the export.
	 *
	 * We loop through the content of all module and pages and look for image URLs.
	 *
	 * @return array<int>
	 */
	protected function get_attachments() {
		$item_ids = [ $this->module_id ];
		$item_ids = array_merge( $item_ids, $this->get_module_pages() );

		// Now collect any attachments referenced in post_content.
		$attachment_ids = [];

		foreach ( $item_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			preg_match_all(
				'#(https?://[^/]+)?' . preg_quote( $this->uploads_dir_baseurl, '#' ) . '((.*?)(\.(jpg|jpeg|png|gif|pdf|webp)))#i',
				$post->post_content,
				$matches
			);

			if ( ! empty( $matches[0] ) ) {
				foreach ( $matches[0] as $url ) {
					$attachment_id = $this->get_attachment_id_from_url( $url );
					if ( $attachment_id ) {
						$attachment_ids[] = $attachment_id;
					}
				}
			}
		}

		return array_unique( $attachment_ids );
	}

	/**
	 * Map any uploads URL (even sized variants) back to its attachment ID.
	 *
	 * @param string $url Full URL to the image file.
	 * @return int Attachment post ID, or 0 if none found.
	 */
	protected function get_attachment_id_from_url( $url ) {
		// 1) Try the built-in function first.
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			return $id;
		}

		// 2) Strip out -WxH size suffix (e.g. "-680x1024") before the extension.
		$clean = preg_replace(
			'/-\d+x\d+(?=\.\w{3,4}$)/',
			'',
			$url
		);

		if ( ! $clean ) {
			return 0;
		}

		if ( $clean !== $url ) {
			$id = attachment_url_to_postid( $clean );
			if ( $id ) {
				return $id;
			}
		}

		// 3) Last-ditch DB lookup on the guid (slow but reliable).
		global $wpdb;

		// We're looking for attachments whose GUID ends with this basename.
		$basename = wp_basename( $clean );
		$like     = '%' . $wpdb->esc_like( $basename );

		// phpcs:ignore WordPress.DB
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID
				 FROM {$wpdb->posts}
				 WHERE post_type = 'attachment'
				   AND guid LIKE %s
				 LIMIT 1",
				$like
			)
		);

		return $id ? (int) $id : 0;
	}

	/**
	 * Save export files into archive.
	 *
	 * @return \WP_Error|string
	 */
	protected function archive() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'ol.exporter.archive',
				'Unable to generate export file. ZipArchive not available.'
			);
		}

		$archive_filename = $this->filename();
		$archive_pathname = $this->exports_dir . $archive_filename;

		if ( file_exists( $archive_pathname ) ) {
			wp_delete_file( $archive_pathname );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive_pathname, ZipArchive::CREATE ) ) {
			return new WP_Error(
				'ol.exporter.archive',
				'Unable to add data to export file.'
			);
		}

		$zip->addFile( $this->exports_dir . 'wordpress.xml', 'wordpress.xml' );

		foreach ( $this->files as $file ) {
			$zip->addFile( $file, $this->normalize_path( $file ) );
		}

		$readme_pathname = $this->exports_dir . 'readme.md';
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $readme_pathname, $this->readme_text );
		$zip->addFile( $readme_pathname, 'readme.md' );

		$zip->close();

		// Remove export file.
		wp_delete_file( $this->exports_dir . 'wordpress.xml' );

		return $archive_pathname;
	}

	/**
	 * Generate export filename.
	 *
	 * @return string $filename
	 */
	protected function filename() {
		$stripped_url = sanitize_title_with_dashes( get_bloginfo( 'name' ) );
		$timestamp    = gmdate( 'Y-m-d' );
		$filename     = "export-{$stripped_url}-{$timestamp}.zip";

		return $filename;
	}

	/**
	 * Change file path for better storing in archive.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	protected function normalize_path( $file ) {
		$abs_path = realpath( ABSPATH );
		if ( ! $abs_path ) {
			return $file;
		}

		$abs_path = trailingslashit( str_replace( '\\', '/', $abs_path ) );

		return str_replace( [ '\\', $abs_path ], '/', $file );
	}
}
