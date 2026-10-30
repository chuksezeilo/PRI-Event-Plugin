<?php
defined( 'ABSPATH' ) || exit;

/**
 * v0.3 - [pri_programs] shortcode: filterable grid of program cards.
 *
 * Attributes (all optional):
 *   status  = all | upcoming | ongoing | past   (comma list allowed, e.g. "upcoming,ongoing")
 *   type    = program-type slug(s), comma separated, e.g. "retreat,workshop"
 *   limit   = cards shown at first and added by each "Load More" click (default 6)
 *   filters = yes | no     show the filter pills (default yes)
 *   default = all | upcoming | ongoing | past | a type slug   pill selected on load (default all)
 *
 * Filtering and Load More happen in the browser (no page reloads): every matching
 * program is printed once, and a small script shows or hides cards.
 */
class PRI_PM_Shortcode {

	const MAX_PROGRAMS = 60; // Safety cap on cards printed on one page.

	public static function init() {
		add_shortcode( 'pri_programs', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/** Registered on every page but only loaded (enqueued) when the shortcode is used. */
	public static function register_assets() {
		wp_register_style( 'pri-pm-frontend', PRI_PM_URL . 'assets/css/frontend.css', array(), PRI_PM_VERSION );
		wp_register_script( 'pri-pm-frontend', PRI_PM_URL . 'assets/js/frontend.js', array(), PRI_PM_VERSION, true );
	}

	public static function render( $atts ) {
		$a = shortcode_atts( array(
			'status'  => 'all',
			'type'    => '',
			'limit'   => 6,
			'filters' => 'yes',
			'default' => 'all',
		), $atts, 'pri_programs' );

		$valid    = array_keys( PRI_PM_Status::labels() );
		$statuses = array_values( array_intersect( $valid, array_map( 'trim', explode( ',', strtolower( $a['status'] ) ) ) ) );
		if ( ! $statuses ) {
			$statuses = $valid; // "all" (or anything unrecognised) means every status.
		}
		$type_slugs = array_filter( array_map( 'sanitize_title', explode( ',', $a['type'] ) ) );
		$limit      = absint( $a['limit'] ) ? absint( $a['limit'] ) : 6;

		$query = array(
			'post_type'      => PRI_PM_Post_Types::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => self::MAX_PROGRAMS,
			'no_found_rows'  => true,
		);
		if ( $type_slugs ) {
			$query['tax_query'] = array( array(
				'taxonomy' => PRI_PM_Post_Types::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array_values( $type_slugs ),
			) );
		}

		// Status is worked out live from the dates (not read from the stored value),
		// so the list is right even if the daily job has not run yet.
		$items = array();
		foreach ( get_posts( $query ) as $post ) {
			$status = PRI_PM_Status::get_status( $post->ID );
			if ( ! in_array( $status, $statuses, true ) ) {
				continue;
			}
			$terms = get_the_terms( $post->ID, PRI_PM_Post_Types::TAXONOMY );
			$types = array();
			if ( is_array( $terms ) ) {
				foreach ( $terms as $t ) {
					$types[ $t->slug ] = $t->name;
				}
			}
			$items[] = array(
				'id'     => $post->ID,
				'status' => $status,
				'start'  => (string) get_post_meta( $post->ID, '_pri_start_date', true ),
				'types'  => $types,
			);
		}

		if ( ! $items ) {
			return '<p class="pri-empty">' . esc_html__( 'No programs to show yet.', 'pri-programs-manager' ) . '</p>';
		}

		// Ongoing first, then upcoming (soonest first), then past (most recent first).
		usort( $items, function ( $x, $y ) {
			$rank = array( 'ongoing' => 0, 'upcoming' => 1, 'past' => 2 );
			if ( $x['status'] !== $y['status'] ) {
				return $rank[ $x['status'] ] <=> $rank[ $y['status'] ];
			}
			$cmp = strcmp( $x['start'], $y['start'] );
			return 'past' === $x['status'] ? -$cmp : $cmp;
		} );

		// Pills are built from what is actually present, so there is never an empty filter.
		$present_status = array();
		$present_types  = array();
		foreach ( $items as $it ) {
			$present_status[ $it['status'] ] = true;
			$present_types                  += $it['types'];
		}

		$default = sanitize_title( $a['default'] );
		if ( isset( $present_status[ $default ] ) ) {
			$default = 'status:' . $default;
		} elseif ( isset( $present_types[ $default ] ) ) {
			$default = 'type:' . $default;
		} else {
			$default = 'all';
		}

		wp_enqueue_style( 'pri-pm-frontend' );
		wp_enqueue_script( 'pri-pm-frontend' );

		$labels = PRI_PM_Status::labels();
		ob_start();
		?>
		<div class="pri-programs" data-limit="<?php echo esc_attr( $limit ); ?>" data-default="<?php echo esc_attr( $default ); ?>">

			<?php if ( 'no' !== strtolower( $a['filters'] ) ) : ?>
				<div class="pri-filters">
					<button type="button" class="pri-pill" data-filter="all"><?php esc_html_e( 'All', 'pri-programs-manager' ); ?></button>
					<?php if ( count( $present_status ) > 1 ) : // One status only: a status filter would be pointless. ?>
						<?php foreach ( $valid as $s ) : ?>
							<?php if ( isset( $present_status[ $s ] ) ) : ?>
								<button type="button" class="pri-pill" data-filter="status:<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $labels[ $s ] ); ?></button>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php foreach ( $present_types as $slug => $name ) : ?>
						<button type="button" class="pri-pill" data-filter="type:<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="pri-grid">
				<?php
				foreach ( $items as $it ) {
					echo self::card( $it ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside card().
				}
				?>
			</div>

			<p class="pri-empty" hidden><?php esc_html_e( 'No programs match this filter.', 'pri-programs-manager' ); ?></p>

			<div class="pri-more-wrap">
				<button type="button" class="pri-load-more" hidden><?php esc_html_e( 'Load More', 'pri-programs-manager' ); ?></button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** One program card. */
	private static function card( $it ) {
		$id      = $it['id'];
		$status  = $it['status'];
		$link    = get_permalink( $id );
		$loc     = get_post_meta( $id, '_pri_location', true );
		$summary = get_post_meta( $id, '_pri_summary', true );

		// Short description: the Excerpt if filled in, otherwise the start of the main text.
		$excerpt = get_post_field( 'post_excerpt', $id );
		if ( '' === trim( (string) $excerpt ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 28 );
		}

		$thumb = get_the_post_thumbnail( $id, 'medium_large', array( 'loading' => 'lazy' ) );

		ob_start();
		?>
		<article class="pri-card pri-status-<?php echo esc_attr( $status ); ?>"
			data-status="<?php echo esc_attr( $status ); ?>"
			data-types="<?php echo esc_attr( implode( ' ', array_keys( $it['types'] ) ) ); ?>">

			<a class="pri-card-image" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				if ( $thumb ) {
					echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- core-generated <img>.
				} else {
					echo '<span class="pri-placeholder"></span>';
				}
				?>
				<?php echo PRI_PM_Helpers::badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>

			<div class="pri-card-body">
				<h3 class="pri-card-title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>

				<p class="pri-card-meta">
					<span class="pri-date"><?php echo esc_html( PRI_PM_Helpers::date_range( $id ) ); ?></span>
					<?php if ( $loc ) : ?>
						<span class="pri-loc"><?php echo esc_html( $loc ); ?></span>
					<?php endif; ?>
				</p>

				<?php if ( $excerpt ) : ?>
					<p class="pri-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<div class="pri-card-actions">
					<?php if ( 'upcoming' === $status ) : ?>
						<!-- Jumps to the enquiry form at the bottom of the program page. -->
						<a class="pri-btn pri-btn-primary" href="<?php echo esc_url( $link . '#pri-enquiry' ); ?>"><?php esc_html_e( 'Book Your Spot', 'pri-programs-manager' ); ?></a>
					<?php elseif ( 'past' === $status && $summary ) : ?>
						<a class="pri-btn pri-btn-muted" href="<?php echo esc_url( $link . '#pri-stories' ); ?>"><?php esc_html_e( 'View Gallery & Stories', 'pri-programs-manager' ); ?></a>
					<?php endif; ?>
					<a class="pri-btn pri-btn-outline" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Learn More', 'pri-programs-manager' ); ?></a>
				</div>

				<?php echo PRI_PM_Helpers::docs_list( $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper. ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
