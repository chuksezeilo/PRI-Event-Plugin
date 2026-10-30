<?php
defined( 'ABSPATH' ) || exit;

/**
 * Upcoming -> Ongoing -> Past logic.
 *
 * Two layers keep the status correct:
 *  1. compute() works the status out from the dates on demand, so a page
 *     is never wrong even if WP-Cron has not run yet.
 *  2. A daily cron job stores the result in the _pri_status meta, so later
 *     modules (shortcode filters, emails) can query by status cheaply.
 */
class PRI_PM_Status {

	const CRON_HOOK = 'pri_pm_daily_status_sync';
	const META_KEY  = '_pri_status';

	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'sync_all' ) );
		// Re-saving a program refreshes its stored status immediately.
		// Priority 20 so it runs after the meta box has saved the dates (priority 10).
		add_action( 'save_post_' . PRI_PM_Post_Types::POST_TYPE, array( __CLASS__, 'sync_one_on_save' ), 20 );
		// Safety net: if the cron event was lost (migration, cleared cron table), recreate it.
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/** Human-readable labels, keyed by status slug. */
	public static function labels() {
		return array(
			'upcoming' => __( 'Upcoming', 'pri-programs-manager' ),
			'ongoing'  => __( 'Ongoing', 'pri-programs-manager' ),
			'past'     => __( 'Past', 'pri-programs-manager' ),
		);
	}

	/**
	 * Pure comparison of Y-m-d strings. These compare correctly as plain strings
	 * because the format is zero-padded, so no date objects are needed.
	 * Kept free of WordPress calls so it is easy to test.
	 *
	 * An empty end date is treated as a one-day program (end = start).
	 */
	public static function compute( $start, $end, $today ) {
		if ( empty( $start ) ) {
			return 'upcoming'; // No date yet: treat as a draft-style upcoming program.
		}
		if ( empty( $end ) || $end < $start ) {
			$end = $start;
		}
		if ( $today < $start ) {
			return 'upcoming';
		}
		if ( $today > $end ) {
			return 'past';
		}
		return 'ongoing';
	}

	/** Status for one program, calculated live. */
	public static function get_status( $post_id ) {
		$start = get_post_meta( $post_id, '_pri_start_date', true );
		$end   = get_post_meta( $post_id, '_pri_end_date', true );
		// current_time() uses the site's timezone (set in Settings > General),
		// so a program flips at midnight Nigeria time, not midnight UTC.
		return self::compute( $start, $end, current_time( 'Y-m-d' ) );
	}

	/** Store the live status in meta, only writing when it actually changed. */
	public static function store_status( $post_id ) {
		$status = self::get_status( $post_id );
		if ( get_post_meta( $post_id, self::META_KEY, true ) !== $status ) {
			update_post_meta( $post_id, self::META_KEY, $status );
		}
		return $status;
	}

	public static function sync_one_on_save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		self::store_status( $post_id );
	}

	/** Daily job: refresh every program's stored status. */
	public static function sync_all() {
		$ids = get_posts( array(
			'post_type'      => PRI_PM_Post_Types::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		foreach ( $ids as $id ) {
			self::store_status( $id );
		}
	}

	/** Schedule the daily job to start just after the site's next midnight. */
	public static function schedule() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}
		$next_midnight = strtotime( 'tomorrow 00:05', current_time( 'timestamp' ) );
		// current_time('timestamp') is site-local; convert back to a real UTC timestamp for cron.
		$utc = $next_midnight - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		wp_schedule_event( max( $utc, time() + 60 ), 'daily', self::CRON_HOOK );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
