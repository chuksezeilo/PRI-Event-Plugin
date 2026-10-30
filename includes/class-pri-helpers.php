<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small display helpers shared by the shortcode (cards) and the single program page,
 * so both always show dates, badges and PDF links the same way.
 */
class PRI_PM_Helpers {

	/**
	 * "Nov 19, 2026" / "Nov 19–29, 2026" / "Nov 19 – Dec 3, 2026" / across years.
	 * Pure PHP (no WordPress calls) so it is easy to test.
	 */
	public static function format_range( $start, $end ) {
		$s = $start ? date_create( $start ) : false;
		if ( ! $s ) {
			return '';
		}
		$e = $end ? date_create( $end ) : false;
		if ( ! $e || $end <= $start ) {
			return $s->format( 'M j, Y' );
		}
		if ( $s->format( 'Y-m' ) === $e->format( 'Y-m' ) ) {
			return $s->format( 'M j' ) . '–' . $e->format( 'j, Y' );
		}
		if ( $s->format( 'Y' ) === $e->format( 'Y' ) ) {
			return $s->format( 'M j' ) . ' – ' . $e->format( 'M j, Y' );
		}
		return $s->format( 'M j, Y' ) . ' – ' . $e->format( 'M j, Y' );
	}

	public static function date_range( $post_id ) {
		return self::format_range(
			get_post_meta( $post_id, '_pri_start_date', true ),
			get_post_meta( $post_id, '_pri_end_date', true )
		);
	}

	/** Coloured status pill. Colours live in the CSS, keyed by the status slug. */
	public static function badge( $status ) {
		$labels = PRI_PM_Status::labels();
		return sprintf(
			'<span class="pri-badge pri-badge-%s">%s</span>',
			esc_attr( $status ),
			esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status )
		);
	}

	public static function pdf_icon() {
		return '<svg class="pri-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM8 13h1.5a1.5 1.5 0 0 1 0 3H9v1.5H8V13zm1 .9v1.2h.5a.6.6 0 0 0 0-1.2H9zm3-.9h1.3c1.2 0 2 .8 2 2s-.8 2-2 2H12v-4zm1 .9v2.2h.3c.7 0 1-.4 1-1.1s-.3-1.1-1-1.1H13zm3-.9h2.5v.9H17v.8h1.3v.9H17V17h-1v-4z"/></svg>';
	}

	/** List of PDF download links for a program, or '' when it has none. */
	public static function docs_list( $post_id ) {
		$docs = PRI_PM_Documents::get( $post_id );
		if ( ! $docs ) {
			return '';
		}
		$html = '<ul class="pri-docs">';
		foreach ( $docs as $doc ) {
			$html .= sprintf(
				'<li><a href="%s" target="_blank" rel="noopener">%s<span>%s</span></a></li>',
				esc_url( $doc['url'] ),
				self::pdf_icon(),
				esc_html( $doc['label'] )
			);
		}
		return $html . '</ul>';
	}
}
