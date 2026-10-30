<?php
defined( 'ABSPATH' ) || exit;

/**
 * The program page: a details panel (status, dates, location, type, Book button) above the
 * normal content, then Summary, Gallery (past programs), Downloads and the enquiry form.
 *
 * It works inside any theme because it hooks into the_content instead of replacing the
 * theme's template; the theme keeps control of the page title banner and the featured image.
 */
class PRI_PM_Single {

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter' ) );
	}

	/** Section heading. The class lets frontend.css beat themes that style bare h2 tags. */
	public static function title( $text ) {
		return '<h2 class="pri-section-title">' . esc_html( $text ) . '</h2>';
	}

	private static function fact( $label, $value_html ) {
		return '<li><span class="pri-fact-label">' . esc_html( $label ) . '</span><span class="pri-fact-value">' . $value_html . '</span></li>';
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
		$time    = get_post_meta( $id, '_pri_start_time', true );
		$summary = get_post_meta( $id, '_pri_summary', true );

		// ---- Details panel ----
		$facts = self::fact( __( 'Date', 'pri-programs-manager' ), esc_html( PRI_PM_Helpers::date_range( $id ) ) );
		if ( $time ) {
			$facts .= self::fact( __( 'Time', 'pri-programs-manager' ), esc_html( mysql2date( get_option( 'time_format' ), '2000-01-01 ' . $time ) ) );
		}
		if ( $loc ) {
			$facts .= self::fact( __( 'Location', 'pri-programs-manager' ), esc_html( $loc ) );
		}
		$terms = get_the_terms( $id, PRI_PM_Post_Types::TAXONOMY );
		if ( is_array( $terms ) && $terms ) {
			$facts .= self::fact( __( 'Type', 'pri-programs-manager' ), esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) );
		}

		$head  = '<div class="pri-single-meta">';
		$head .= '<div class="pri-single-status">' . PRI_PM_Helpers::badge( $status ) . '</div>';
		$head .= '<ul class="pri-facts">' . $facts . '</ul>';
		if ( 'past' !== $status ) {
			// Jumps to the enquiry form at the bottom of this page.
			$head .= '<a class="pri-btn pri-btn-primary" href="#pri-enquiry">' . esc_html__( 'Book Your Spot', 'pri-programs-manager' ) . '</a>';
		}
		$head .= '</div>';

		// ---- Sections below the content ----
		$tail = '';
		if ( $summary ) {
			$tail .= '<section id="pri-stories" class="pri-single-section">' . self::title( __( 'Summary', 'pri-programs-manager' ) )
				. wpautop( esc_html( $summary ) ) . '</section>';
		}
		// Photos and videos are shown only once the program is over.
		if ( 'past' === $status ) {
			$tail .= PRI_PM_Gallery::render( $id );
		}
		$docs = PRI_PM_Helpers::docs_list( $id );
		if ( $docs ) {
			$tail .= '<section id="pri-documents" class="pri-single-section">' . self::title( __( 'Downloads', 'pri-programs-manager' ) ) . $docs . '</section>';
		}

		// Enquiry form (hidden automatically for past programs).
		$tail .= PRI_PM_Enquiry::render_section( $id, $status );

		return $head . $content . $tail;
	}
}
