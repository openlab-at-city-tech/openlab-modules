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

/**
 * Exporter class.
 */
class Exporter {

	/**
	 * Files to export.
	 *
	 * @var array
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
	 * Cached value of `wp_upload_dir()`.
	 *
	 * @var array
	 */
	public $uploads_dir = [];

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
	 * Create export object.
	 *
	 * @param array $upload_dir
	 */
	public function __construct( array $upload_dir ) {
		$this->uploads_dir = $upload_dir;
		$this->exports_dir = trailingslashit( $upload_dir['basedir'] ) . 'openlab-modules-exports/';
		$this->exports_url = trailingslashit( $upload_dir['baseurl'] ) . 'openlab-modules-exports/';
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

		$this->create_acknowledgements_page();

		$export = $this->create_wxp();
		if ( is_wp_error( $export ) ) {
			return $export;
		}

		$this->delete_acknowledgements_page();

		$this->prepare_files( $this->uploads_dir['basedir'] );

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
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Creates an Acknowledgements page to be included in the export.
	 *
	 * @since 1.0.0
	 *
	 * @return null
	 */
	protected function create_acknowledgements_page() {
		if ( empty( $this->acknowledgements_text ) ) {
			return;
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_name'    => 'acknowledgements',
				'post_status'  => 'draft',
				'post_title'   => __( 'Acknowledgements', 'openlab-import-export' ),
				'post_content' => $this->acknowledgements_text,
			]
		);

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return;
		}

		$this->acknowledgements_page_id = $post_id;
	}

	/**
	 * Deletes the auto-generated Acknowledgements page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function delete_acknowledgements_page() {
		if ( empty( $this->acknowledgements_page_id ) ) {
			return;
		}

		wp_delete_post( $this->acknowledgements_page_id, true );
	}

	/**
	 * Prepare backups files. Image uploads, etc.
	 *
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

		try {
			$iterator = UploadsIterator::create( $folder );

			foreach ( $iterator as $file ) {
				$this->files[] = $file->getPathname();
			}
		} catch ( UnexpectedValueException $e ) {
			return new WP_Error(
				'ol.exporter.prepare.files',
				sprintf( 'Could not open path: %', $e->getMessage() )
			);
		}
	}

	/**
	 * Generates the text of the readme file.
	 *
	 * @return void
	 */
	protected function prepare_readme() {
		$admin_names = \OpenLab\ImportExport\get_site_admin_names();

		$text = esc_html__( 'Acknowledgements', 'openlab-import-export' );

		$converter = new \League\HTMLToMarkdown\HtmlConverter();

		$text .= "\n\n";
		$text .= $converter->convert( $this->acknowledgements_text );

		if ( ! empty( $this->readme_custom_text ) ) {
			$text .= "\n\n";
			$text .= '# ' . esc_html__( 'Note from Exporter', 'openlab-import-export' );
			$text .= "\n\n";
			$text .= $this->readme_custom_text;
		};

		$text .= "\n\n";

		$text .= '# ' . esc_html__( 'Theme, Plugins, and Menu', 'openlab-import-export' );
		$text .= "\n\n";

		$text .= esc_html__( 'The exported site uses the theme and plugins listed below. If you want your site to have the same appearance and features, you will need to install (if necessary) and activate the theme and plugins before you import.', 'openlab-import-export' );

		$active_theme = wp_get_theme( get_stylesheet() );

		$theme_uri = $this->get_theme_uri( get_stylesheet() );
		if ( ! $theme_uri ) {
			$theme_uri = $active_theme->get( 'ThemeURI' );
		}

		$text .= "\n\n";
		$text .= esc_html__( 'Theme:', 'openlab-import-export' );
		$text .= "\n";
		$text .= sprintf(
			'* %s: %s',
			esc_html( $active_theme->name ),
			esc_html( $theme_uri )
		);
		$text .= "\n\n";

		$text .= esc_html__( 'Plugins:', 'openlab-import-export' );
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
	 * Gets a wordpress.org download URI for a plugin file.
	 *
	 * @param string $plugin_file
	 */
	protected function get_plugin_uri( $plugin_file ) {
		$pf_parts    = explode( '/', $plugin_file );
		$plugin_slug = $pf_parts[0];

		return $this->get_download_uri( $plugin_slug, 'plugins' );
	}

	/**
	 * Gets a wordpress.org download URI for a theme.
	 *
	 * @param string $theme
	 */
	protected function get_theme_uri( $theme ) {
		return $this->get_download_uri( $theme, 'themes' );
	}

	/**
	 * Gets a wordpress.org download URI for a theme or plugin.
	 *
	 * @param string $slug
	 * @param string $type 'plugins' or 'themes'.
	 */
	protected function get_download_uri( $slug, $type ) {
		$cached = get_transient( 'download_uri_' . $slug );
		if ( $cached ) {
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

		$wxp->set_post_types( $this->post_types );
		$wxp->set_acknowledgements_page_id( $this->acknowledgements_page_id );

		if ( ! $wxp->create() ) {
			return new WP_Error(
				'ol.exporter.create.wxp',
				'Unable to create WXP export file.'
			);
		}

		return true;
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

		$zip = new ZipArchive;
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
		file_put_contents( $readme_pathname, $this->readme_text );
		$zip->addFile( $readme_pathname, 'readme.md' );

		$zip->close();

		// Remove export file.
		unlink( $this->exports_dir . 'wordpress.xml' );

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
	 * @param string $file
	 * @return string
	 */
	protected function normalize_path( $file ) {
		$abs_path = realpath( ABSPATH );
		$abs_path = trailingslashit( str_replace( '\\', '/', $abs_path ) );

		return str_replace( [ '\\', $abs_path ], '/', $file );
	}
}
