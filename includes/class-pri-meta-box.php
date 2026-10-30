<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Program Details" box on the edit screen: dates, time, location, summary.
 * Uses plain post meta, so no custom database tables are needed.
 */
class PRI_PM_Meta_Box {

	const NONCE_ACTION = 'pri_pm_save_details';
	const NONCE_NAME   = 'pri_pm_details_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . PRI_PM_Post_Types::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
	}

	public static function add() {
		add_meta_box(
			'pri_pm_details',
			__( 'Program Details', 'pri-programs-manager' ),
			array( __CLASS__, 'render' ),
			PRI_PM_Post_Types::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render( $post ) {
		// The nonce proves this form submission came from this screen, not a forged request.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$start    = get_post_meta( $post->ID, '_pri_start_date', true );
		$end      = get_post_meta( $post->ID, '_pri_end_date', true );
		$time     = get_post_meta( $post->ID, '_pri_start_time', true );
		$location = get_post_meta( $post->ID, '_pri_location', true );
		$summary  = get_post_meta( $post->ID, '_pri_summary', true );
		$status   = PRI_PM_Status::get_status( $post->ID );
		$labels   = PRI_PM_Status::labels();
		?>
		<style>
			.pri-pm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:14px}
			.pri-pm-grid label,.pri-pm-field label{display:block;font-weight:600;margin-bottom:4px}
			.pri-pm-grid input,.pri-pm-field input,.pri-pm-field textarea{width:100%}
			.pri-pm-status{display:inline-block;padding:2px 10px;border-radius:12px;color:#fff;font-weight:600}
			.pri-pm-status.upcoming{background:#16a34a}.pri-pm-status.ongoing{background:#d97706}.pri-pm-status.past{background:#6b7280}
			.pri-pm-field{margin-bottom:14px}
			.pri-pm-hint{color:#646970;font-size:12px;margin-top:3px}
		</style>

		<p>
			<?php esc_html_e( 'Current status:', 'pri-programs-manager' ); ?>
			<span class="pri-pm-status <?php echo esc_attr( $status ); ?>"><?php echo esc_html( $labels[ $status ] ); ?></span>
			<span class="pri-pm-hint"><?php esc_html_e( '(set automatically from the dates below)', 'pri-programs-manager' ); ?></span>
		</p>

		<div class="pri-pm-grid">
			<div>
				<label for="pri_start_date"><?php esc_html_e( 'Start date', 'pri-programs-manager' ); ?></label>
				<input type="date" id="pri_start_date" name="pri_start_date" value="<?php echo esc_attr( $start ); ?>" required>
			</div>
			<div>
				<label for="pri_end_date"><?php esc_html_e( 'End date', 'pri-programs-manager' ); ?></label>
				<input type="date" id="pri_end_date" name="pri_end_date" value="<?php echo esc_attr( $end ); ?>">
				<div class="pri-pm-hint"><?php esc_html_e( 'Leave empty for a one-day program.', 'pri-programs-manager' ); ?></div>
			</div>
			<div>
				<label for="pri_start_time"><?php esc_html_e( 'Start time (optional)', 'pri-programs-manager' ); ?></label>
				<input type="time" id="pri_start_time" name="pri_start_time" value="<?php echo esc_attr( $time ); ?>">
			</div>
		</div>

		<div class="pri-pm-field">
			<label for="pri_location"><?php esc_html_e( 'Location', 'pri-programs-manager' ); ?></label>
			<input type="text" id="pri_location" name="pri_location" value="<?php echo esc_attr( $location ); ?>" placeholder="Accra, Ghana">
		</div>

		<div class="pri-pm-field">
			<label for="pri_summary"><?php esc_html_e( 'Event summary', 'pri-programs-manager' ); ?></label>
			<textarea id="pri_summary" name="pri_summary" rows="5"><?php echo esc_textarea( $summary ); ?></textarea>
			<div class="pri-pm-hint"><?php esc_html_e( 'Shown for past programs (outcomes, highlights, stories). Short description: use the Excerpt box. Card image: use Featured image.', 'pri-programs-manager' ); ?></div>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		// Guard clauses: only save genuine, authorised form submissions.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$start = self::clean_date( isset( $_POST['pri_start_date'] ) ? wp_unslash( $_POST['pri_start_date'] ) : '' );
		$end   = self::clean_date( isset( $_POST['pri_end_date'] ) ? wp_unslash( $_POST['pri_end_date'] ) : '' );

		// An end date before the start date is a typing slip; fall back to a one-day program.
		if ( $start && ( ! $end || $end < $start ) ) {
			$end = $start;
		}

		update_post_meta( $post_id, '_pri_start_date', $start );
		update_post_meta( $post_id, '_pri_end_date', $end );
		update_post_meta( $post_id, '_pri_start_time', self::clean_time( isset( $_POST['pri_start_time'] ) ? wp_unslash( $_POST['pri_start_time'] ) : '' ) );
		update_post_meta( $post_id, '_pri_location', sanitize_text_field( isset( $_POST['pri_location'] ) ? wp_unslash( $_POST['pri_location'] ) : '' ) );
		update_post_meta( $post_id, '_pri_summary', sanitize_textarea_field( isset( $_POST['pri_summary'] ) ? wp_unslash( $_POST['pri_summary'] ) : '' ) );
	}

	/** Accept only a real Y-m-d date; anything else becomes empty. */
	private static function clean_date( $value ) {
		$value = sanitize_text_field( $value );
		$d     = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/** Accept only HH:MM; anything else becomes empty. */
	private static function clean_time( $value ) {
		$value = sanitize_text_field( $value );
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}
}
