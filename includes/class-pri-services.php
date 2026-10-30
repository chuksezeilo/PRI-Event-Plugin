<?php
defined( 'ABSPATH' ) || exit;

/**
 * v0.6 - [pri_services] accordion ("Core Consulting & Development Offerings").
 *
 * The list is edited in Programs > Settings > Services.
 *
 * Attributes (all optional):
 *   title  = heading above the list (default "Core Consulting & Development Offerings"; title="" hides it)
 *   open   = first | none       which item starts open (default none)
 *   single = yes | no           opening one closes the others (default yes)
 *
 * Built on the browser's own <details>/<summary> element, so it works without JavaScript,
 * with the keyboard, and with screen readers. The "only one open" behaviour uses the
 * <details name="..."> attribute, which current browsers support; older ones simply let
 * several stay open, which is harmless.
 */
class PRI_PM_Services {

	private static $instances = 0;

	public static function init() {
		add_shortcode( 'pri_services', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$a = shortcode_atts( array(
			'title'  => __( 'Core Consulting & Development Offerings', 'pri-programs-manager' ),
			'open'   => 'none',
			'single' => 'yes',
		), $atts, 'pri_services' );

		$items = PRI_PM_Settings::services();
		if ( ! $items ) {
			return '';
		}

		wp_enqueue_style( 'pri-pm-frontend' );

		// A group name per shortcode instance, so two accordions on one page do not close each other.
		self::$instances++;
		$group = ( 'no' === strtolower( $a['single'] ) ) ? '' : 'pri-acc-' . self::$instances;

		ob_start();
		?>
		<div class="pri-services">
			<?php if ( '' !== trim( $a['title'] ) ) : ?>
				<h2 class="pri-services-title">
					<?php echo esc_html( $a['title'] ); ?>
					<span class="pri-services-hint"><?php esc_html_e( '(Click to expand)', 'pri-programs-manager' ); ?></span>
				</h2>
			<?php endif; ?>

			<div class="pri-acc">
				<?php foreach ( $items as $i => $item ) : ?>
					<details class="pri-acc-item"
						<?php echo $group ? 'name="' . esc_attr( $group ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?>
						<?php echo ( 'first' === strtolower( $a['open'] ) && 0 === $i ) ? 'open' : ''; ?>>
						<summary><span class="pri-acc-q"><?php echo esc_html( $item['title'] ); ?></span><span class="pri-acc-chev" aria-hidden="true"></span></summary>
						<?php if ( ! empty( $item['text'] ) ) : ?>
							<div class="pri-acc-a"><?php echo wp_kses_post( wpautop( esc_html( $item['text'] ) ) ); ?></div>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
