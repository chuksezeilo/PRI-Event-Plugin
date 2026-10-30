<?php
/**
 * Runs when the plugin is DELETED from the Plugins screen (not when it is just deactivated).
 *
 * It only removes your data if you ticked the box in Programs > Settings > Data. Otherwise
 * deleting and re-installing the plugin brings all your programs straight back.
 * Photos, videos and PDFs in the Media Library are never deleted.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The daily status job is always removed: it is useless without the plugin.
wp_clear_scheduled_hook( 'pri_pm_daily_status_sync' );

$settings = get_option( 'pri_pm_settings' );
if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
	return; // Keep everything.
}

global $wpdb;

// 1. Programs (every status, including Trash). Deleting a post also removes its meta:
//    dates, location, summary, PDFs list, gallery list, enquiry email, stored status.
//    'any' is not enough: WordPress leaves Trash out of it, so the statuses are listed.
$ids = get_posts( array(
	'post_type'      => 'pri_program',
	'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
) );
foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}

// 2. Program Types. The taxonomy is not registered during uninstall, so register it briefly.
register_taxonomy( 'pri_program_type', 'pri_program' );
$terms = get_terms( array( 'taxonomy' => 'pri_program_type', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( is_array( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'pri_program_type' );
	}
}

// 3. Settings, and the temporary per-visitor counters of the enquiry form.
delete_option( 'pri_pm_settings' );
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_pri_enq_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_pri_enq_' ) . '%'
	)
);
