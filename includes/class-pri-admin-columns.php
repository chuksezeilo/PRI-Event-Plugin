<?php
defined( 'ABSPATH' ) || exit;

/**
 * Extra columns on the Programs list so you can see dates, location and
 * status at a glance without opening each program.
 */
class PRI_PM_Admin_Columns {

	public static function init() {
		$pt = PRI_PM_Post_Types::POST_TYPE;
		add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'render' ), 10, 2 );
		add_filter( "manage_edit-{$pt}_sortable_columns", array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_date' ) );
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['pri_dates']    = __( 'Dates', 'pri-programs-manager' );
				$new['pri_location'] = __( 'Location', 'pri-programs-manager' );
				$new['pri_status']   = __( 'Status', 'pri-programs-manager' );
				$new['pri_docs']     = __( 'PDFs', 'pri-programs-manager' );
			}
		}
		return $new;
	}

	public static function render( $column, $post_id ) {
		if ( 'pri_dates' === $column ) {
			$start = get_post_meta( $post_id, '_pri_start_date', true );
			$end   = get_post_meta( $post_id, '_pri_end_date', true );
			if ( ! $start ) {
				echo '&mdash;';
				return;
			}
			$fmt = get_option( 'date_format' );
			echo esc_html( mysql2date( $fmt, $start ) );
			if ( $end && $end !== $start ) {
				echo ' &ndash; ' . esc_html( mysql2date( $fmt, $end ) );
			}
		} elseif ( 'pri_location' === $column ) {
			$loc = get_post_meta( $post_id, '_pri_location', true );
			echo $loc ? esc_html( $loc ) : '&mdash;';
		} elseif ( 'pri_docs' === $column ) {
			$count = count( PRI_PM_Documents::get( $post_id ) );
			echo $count ? esc_html( $count ) : '&mdash;';
		} elseif ( 'pri_status' === $column ) {
			$status = PRI_PM_Status::get_status( $post_id );
			$labels = PRI_PM_Status::labels();
			$colors = array( 'upcoming' => '#16a34a', 'ongoing' => '#d97706', 'past' => '#6b7280' );
			printf(
				'<span style="background:%s;color:#fff;padding:2px 9px;border-radius:12px;font-weight:600">%s</span>',
				esc_attr( $colors[ $status ] ),
				esc_html( $labels[ $status ] )
			);
		}
	}

	public static function sortable( $cols ) {
		$cols['pri_dates'] = 'pri_dates';
		return $cols;
	}

	/** Sorting by the Dates column orders by the stored start date. */
	public static function sort_by_date( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'pri_dates' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', '_pri_start_date' );
		$query->set( 'orderby', 'meta_value' );
	}
}
