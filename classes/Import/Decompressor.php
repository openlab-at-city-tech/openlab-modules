<?php
/**
 * Decompressor class.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Import;

use WP_Error;
use ZipArchive;

/**
 * Decompressor class.
 */
class Decompressor {

	/**
	 * Attachement ID.
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Archive file path.
	 *
	 * @var string
	 */
	protected $archive = '';

	/**
	 * Archive directory.
	 *
	 * @var string
	 */
	protected $archive_path = '';

	/**
	 * Extract directory.
	 *
	 * @var string
	 */
	protected $extract_path = '';

	/**
	 * Decompress Constructor
	 *
	 * @param int $id Archive ID.
	 */
	public function __construct( $id ) {
		$this->id = $id;

		$attached_file = get_attached_file( $id );
		if ( $attached_file ) {
			$this->archive = $attached_file;
		}

		if ( $this->archive ) {
			$archive_realpath = realpath( $this->archive );
			if ( $archive_realpath ) {
				$this->archive_path = pathinfo( $archive_realpath, PATHINFO_DIRNAME );
				$this->extract_path = $this->archive_path . '/extract';
			}
		}
	}


	/**
	 * Extract import archive.
	 *
	 * @return WP_Error|string
	 */
	public function extract() {
		$zip = new ZipArchive();

		if ( ! $zip->open( $this->archive ) ) {
			return new WP_Error(
				'ol.importer.archive',
				'Unable to extract export file.'
			);
		}

		// Extract File.
		$extracted = $zip->extractTo( $this->extract_path );
		$zip->close();

		// Delete non-permitted files.
		$this->sanitize_extracted_files();

		return $this->extract_path;
	}

	/**
	 * Gets the filesystem instance.
	 *
	 * @return \WP_Filesystem_Base
	 */
	protected function get_filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			// Make sure the WP_Filesystem function exists.
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once untrailingslashit( ABSPATH ) . '/wp-admin/includes/file.php';
			}

			WP_Filesystem();
		}

		return $wp_filesystem;
	}

	/**
	 * Sanitizes extracted files to remove those that are not permitted by this WP installation.
	 *
	 * @return void
	 */
	protected function sanitize_extracted_files() {
		$rdi = new \RecursiveDirectoryIterator( $this->extract_path );
		$rii = new \RecursiveIteratorIterator( $rdi, \RecursiveIteratorIterator::SELF_FIRST );

		$filesystem = $this->get_filesystem();

		foreach ( $rii as $file_info ) {
			if ( ! ( $file_info instanceof \SplFileInfo ) ) {
				continue;
			}

			$file_name = $file_info->getFilename();
			if ( '.' === $file_name || '..' === $file_name ) {
				continue;
			}

			if ( $file_info->isDir() ) {
				continue;
			}

			// We allow these plugin-generated non-executables.
			if ( 'readme.md' === $file_name || 'wordpress.xml' === $file_name ) {
				continue;
			}

			$wp_filetype = wp_check_filetype( $file_name );
			if ( ! $wp_filetype['ext'] ) {
				$filesystem->delete( $file_info->getRealPath(), true );
			}
		}
	}

	/**
	 * Delete import attachement and extract dir.
	 *
	 * @return bool
	 */
	public function cleanup() {
		$filesystem = $this->get_filesystem();

		wp_delete_attachment( $this->id );

		// Recursively delete the extract directory.
		return $filesystem->delete( $this->extract_path, true );
	}
}
