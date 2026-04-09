<?php
/**
 * Plugin Name:       OpenLab Module Builder
 * Plugin URI:        https://openlab.citytech.cuny.edu/
 * Description:       A plugin to manage and display OpenLab Modules.
 * Version:           1.0.0-alpha-20260409-2
 * Requires at least: 5.4
 * Requires PHP:      7.3
 * Author:            OpenLab at City Tech
 * Author URI:        https://openlab.citytech.cuny.edu/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       openlab-module-builder
 * Domain Path:       /languages
 *
 * @package openlab-module-builder
 */

namespace OpenLab\Modules;

defined( 'ABSPATH' ) || exit;

const ROOT_DIR  = __DIR__;
const ROOT_FILE = __FILE__;

require ROOT_DIR . '/constants.php';
require ROOT_DIR . '/vendor/autoload.php';

const VERSION = '1.0.0-alpha-20260409-2';

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
