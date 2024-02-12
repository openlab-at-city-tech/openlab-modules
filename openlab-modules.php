<?php
/**
 * Plugin Name:       OpenLab Modules
 * Plugin URI:        https://openlab.citytech.cuny.edu/
 * Description:
 * Version:           1.0.0-alpha
 * Requires at least: 5.4
 * Requires PHP:      7.3
 * Author:            OpenLab at City Tech
 * Author URI:        https://openlab.citytech.cuny.edu/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       openlab-modules
 * Domain Path:       /languages
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

const ROOT_DIR  = __DIR__;
const ROOT_FILE = __FILE__;

require ROOT_DIR . '/constants.php';
require ROOT_DIR . '/vendor/autoload.php';

register_activation_hook(
	__FILE__,
	function () {
		update_option( 'openlab_modules_rewrite_rules_flushed', '0' );
	}
);

add_action(
	'plugins_loaded',
	function () {
		App::init();
	}
);
