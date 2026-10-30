<?php
defined( 'ABSPATH' ) || exit;

/**
 * Minimal program page: adds status, dates, location, summary and PDF links
 * around the normal content, so "Learn More" leads somewhere useful.
 * It works inside any theme because it uses the_content, not a custom template.
 * (A fuller template comes in a later release.)
 */
class PRI_PM_Single {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter' ) );
	}

	public static function filter( $content ) {
		// Only the main content of a single program page, not excerpts, widgets or other loops.
		if ( ! is_singular( PRI_PM_Post_Types::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		wp_enqueue_style( 'pri-pm-frontend' );

		$id      = get_the_ID();
		$status  = PRI_PM_Status::get_status( $id );
		$loc     = get_post_meta( $id, '_pri_location', true );
		$summary = get_post_meta( $id, '_pri_summary', true );

		$head  = '<div class="pri-single-meta">' . PRI_PM_Helpers::badge( $status );
		$head .= '<span class="pri-date">' . esc_html( PRI_PM_Helpers::date_range( $id ) ) . '</span>';
		if ( $loc ) {
			$head .= '<span class="pri-loc">' . esc_html( $loc ) . '</span>';
		}
		$head .= '</div>';

		$tail = '';
		if ( $summary ) {
			$tail .= '<section id="pri-stories" class="pri-single-section"><h2>' . esc_html__( 'Summary', 'pri-programs-manager' ) . '</h2>'
				. wpautop( esc_html( $summary ) ) . '</section>';
		}
		// Photos and videos are shown only once the program is over.
		if ( 'past' === $status ) {
			$tail .= PRI_PM_Gallery::render( $id );
		}
		$docs = PRI_PM_Helpers::docs_list( $id );
		if ( $docs ) {
			$tail .= '<section id="pri-documents" class="pri-single-section"><h2>' . esc_html__( 'Downloads', 'pri-programs-manager' ) . '</h2>' . $docs . '</section>';
		}

		// Enquiry form (hidden automatically for past programs).
		$tail .= PRI_PM_Enquiry::render_section( $id, $status );

		return $head . $content . $tail;
	}
}
