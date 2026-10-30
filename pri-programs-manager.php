<?php
/**
 * Plugin Name:       PRI Programs Manager
 * Description:       Organise programs, retreats and workshops. Statuses (Upcoming, Ongoing, Past) update automatically from the dates.
 * Version:           0.5.0
 * Author:            Chuks Ezeilo
 * Text Domain:       pri-programs-manager
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 */

// Block direct access: this file must only run inside WordPress.
defined( 'ABSPATH' ) || exit;

define( 'PRI_PM_VERSION', '0.5.0' );
define( 'PRI_PM_FILE', __FILE__ );
define( 'PRI_PM_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRI_PM_URL', plugin_dir_url( __FILE__ ) );

// Each module lives in its own file so later releases only add files.
require_once PRI_PM_DIR . 'includes/class-pri-status.php';
require_once PRI_PM_DIR . 'includes/class-pri-post-types.php';
require_once PRI_PM_DIR . 'includes/class-pri-helpers.php';
require_once PRI_PM_DIR . 'includes/class-pri-meta-box.php';
require_once PRI_PM_DIR . 'includes/class-pri-documents.php';
require_once PRI_PM_DIR . 'includes/class-pri-gallery.php';
require_once PRI_PM_DIR . 'includes/class-pri-admin-columns.php';
require_once PRI_PM_DIR . 'includes/class-pri-shortcode.php';
require_once PRI_PM_DIR . 'includes/class-pri-settings.php';
require_once PRI_PM_DIR . 'includes/class-pri-enquiry.php';
require_once PRI_PM_DIR . 'includes/class-pri-single.php';

PRI_PM_Post_Types::init();
PRI_PM_Meta_Box::init();
PRI_PM_Documents::init();
PRI_PM_Gallery::init();
PRI_PM_Status::init();
PRI_PM_Admin_Columns::init();
PRI_PM_Shortcode::init();
PRI_PM_Settings::init();
PRI_PM_Enquiry::init();
PRI_PM_Single::init();

/**
 * Activation: register the post type first so its URLs exist,
 * then flush rewrite rules once. Without this, program pages 404
 * until the admin manually re-saves Permalinks.
 */
register_activation_hook( __FILE__, function () {
	PRI_PM_Post_Types::register();
	flush_rewrite_rules();
	PRI_PM_Status::schedule();
	PRI_PM_Status::sync_all(); // Set correct statuses straight away.
} );

/**
 * Deactivation: remove our scheduled job so it does not linger
 * in WP-Cron after the plugin is switched off.
 */
register_deactivation_hook( __FILE__, function () {
	PRI_PM_Status::unschedule();
	flush_rewrite_rules();
} );
