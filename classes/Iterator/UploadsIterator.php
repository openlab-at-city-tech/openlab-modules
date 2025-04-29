<?php
/**
 * Uploads directory iterator.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Iterator;

use FilesystemIterator;
use RecursiveFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * UploadsIterator class.
 */
class UploadsIterator extends RecursiveFilterIterator {

	/**
	 * Create iterator instance.
	 *
	 * @param string $dir Directory to iterate.
	 * @return \RecursiveIteratorIterator<\OpenLab\Modules\Iterator\UploadsIterator>
	 */
	public static function create( $dir ) {
		return new RecursiveIteratorIterator(
			new UploadsIterator(
				new RecursiveDirectoryIterator(
					$dir,
					FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS
				)
			)
		);
	}

	/**
	 * Apply custom filters.
	 *
	 * @return bool
	 */
	public function accept(): bool {
		$current = $this->current();
		if ( ! $current instanceof \SplFileInfo ) {
			return false;
		}

		// So we can iterate again on subdirs.
		if ( $current->isDir() ) {
			return true;
		}

		$filename = $current->getFilename();
		$info     = wp_check_filetype( $filename );

		return ! empty( $info['ext'] );
	}
}
