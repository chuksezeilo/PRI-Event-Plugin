<?php
defined( 'ABSPATH' ) || exit;

/**
 * v0.4 - Enquiry form.
 *
 * Shown automatically on the page of every Upcoming/Ongoing program, and anywhere
 * with the shortcode:
 *   [pri_enquiry]              a form with a "Which program?" dropdown
 *   [pri_enquiry program="12"] a form fixed to program 12
 *
 * The form posts back to the same page (works without JavaScript). On a mistake the
 * page reloads with what the visitor typed, so nothing is lost. On success the visitor
 * is redirected, so pressing refresh cannot send the enquiry twice.
 *
 * Spam protection: a hidden "honeypot" field only bots fill in, a signed timestamp
 * (too fast = bot), a per-IP hourly limit, and a cap on links in the message.
 *
 * Why no WordPress nonce: caching plugins (this site uses SpeedyCache) store a copy of the
 * page, and a nonce inside it expires after 12-24 hours, so genuine visitors would be told
 * "session expired". The signed timestamp stays valid for 30 days, so cached pages are safe.
 * A public enquiry form changes nothing for a logged-in user, so there is no account that a
 * forged request could abuse (the reason nonces exist).
 */
class PRI_PM_Enquiry {

	const MIN_SECONDS  = 3;       // Faster than this is almost certainly a bot.
	const MAX_AGE      = 2592000; // 30 days: long enough for cached pages.
	const MAX_PER_HOUR = 8;

	/** Result of the submission handled on this request (so the form can show errors). */
	private static $state = null;

	public static function init() {
		add_shortcode( 'pri_enquiry', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . PRI_PM_Post_Types::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 1 );
	}

	/* ------------------------------------------------------------------ *
	 * Pure helpers (no database or request access, easy to test)
	 * ------------------------------------------------------------------ */

	private static function len( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s );
	}

	/** "timestamp.signature". The signature stops a bot inventing its own timestamp. */
	public static function make_token( $scope, $time = null ) {
		$t = $time ? (int) $time : time();
		return $t . '.' . hash_hmac( 'sha256', $t . '|' . (int) $scope, wp_salt( 'auth' ) );
	}

	public static function check_token( $token, $scope, $now = null ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $parts[0] . '|' . (int) $scope, wp_salt( 'auth' ) );
		if ( ! hash_equals( $expected, $parts[1] ) ) {
			return false;
		}
		$age = ( $now ? (int) $now : time() ) - (int) $parts[0];
		return $age >= self::MIN_SECONDS && $age <= self::MAX_AGE;
	}

	/** Clean and check the four visitor fields. Returns array( values, errors ). */
	public static function validate( $in ) {
		$errors = array();

		// sanitize_text_field() also removes line breaks, which blocks email header injection.
		$name  = isset( $in['name'] ) ? sanitize_text_field( $in['name'] ) : '';
		$name  = trim( str_replace( array( '<', '>', '"' ), '', $name ) );
		$email = isset( $in['email'] ) ? sanitize_email( $in['email'] ) : '';
		$phone = isset( $in['phone'] ) ? preg_replace( '/[^0-9+\-\s().]/', '', (string) $in['phone'] ) : '';
		$phone = trim( (string) $phone );
		$msg   = isset( $in['message'] ) ? trim( sanitize_textarea_field( $in['message'] ) ) : '';

		if ( self::len( $name ) < 2 || self::len( $name ) > 100 ) {
			$errors['name'] = __( 'Please enter your name.', 'pri-programs-manager' );
		}
		if ( ! $email || ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'pri-programs-manager' );
		}
		if ( self::len( $phone ) > 30 ) {
			$errors['phone'] = __( 'That phone number looks too long.', 'pri-programs-manager' );
		}
		if ( self::len( $msg ) < 5 ) {
			$errors['message'] = __( 'Please write a short message.', 'pri-programs-manager' );
		} elseif ( self::len( $msg ) > 3000 ) {
			$errors['message'] = __( 'Your message is too long (3000 characters maximum).', 'pri-programs-manager' );
		} elseif ( preg_match_all( '#https?://#i', $msg ) > 2 ) {
			// Spam almost always carries several links; a genuine enquiry rarely does.
			$errors['message'] = __( 'Please remove some links from your message.', 'pri-programs-manager' );
		}

		return array(
			array( 'name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $msg ),
			$errors,
		);
	}

	/**
	 * Build the email. The visitor's address goes in Reply-To (not From), so you can just
	 * press Reply, and the message is not rejected as forged mail by the receiving server.
	 *
	 * $program: array( title, dates, location ) or null for a general enquiry.
	 */
	public static function build_mail( $v, $program, $to, $site, $page_url ) {
		$title   = $program ? $program['title'] : __( 'General enquiry', 'pri-programs-manager' );
		$subject = sprintf( '[%s] Enquiry: %s (from %s)', $site, $title, $v['name'] );

		$lines   = array();
		$lines[] = 'New enquiry from the website.';
		$lines[] = '';
		$lines[] = 'Program:  ' . $title;
		if ( $program && ! empty( $program['dates'] ) ) {
			$lines[] = 'Dates:    ' . $program['dates'];
		}
		if ( $program && ! empty( $program['location'] ) ) {
			$lines[] = 'Location: ' . $program['location'];
		}
		$lines[] = '';
		$lines[] = 'Name:     ' . $v['name'];
		$lines[] = 'Email:    ' . $v['email'];
		$lines[] = 'Phone:    ' . ( '' !== $v['phone'] ? $v['phone'] : '-' );
		$lines[] = '';
		$lines[] = 'Message:';
		$lines[] = $v['message'];
		$lines[] = '';
		$lines[] = '--';
		$lines[] = 'Sent from: ' . $page_url;
		$lines[] = 'Reply to this email to answer ' . $v['name'] . ' directly.';

		return array(
			'to'      => $to,
			'subject' => str_replace( array( "\r", "\n" ), ' ', $subject ),
			'body'    => implode( "\n", $lines ),
			'headers' => array( 'Reply-To: "' . $v['name'] . '" <' . $v['email'] . '>' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Handling the submission
	 * ------------------------------------------------------------------ */

	private static function client_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'pri_enq_' . md5( $ip );
	}

	/** True when this visitor has used up their hourly allowance. */
	private static function rate_limited() {
		// REMOTE_ADDR is used (not X-Forwarded-For) because that header can be faked by a bot.
		// Behind a proxy such as Cloudflare everyone may share one address; raise the limit with this filter if so.
		$max = (int) apply_filters( 'pri_pm_enquiry_max_per_hour', self::MAX_PER_HOUR );
		return (int) get_transient( self::client_key() ) >= $max;
	}

	private static function count_attempt() {
		set_transient( self::client_key(), (int) get_transient( self::client_key() ) + 1, HOUR_IN_SECONDS );
	}

	/** Runs on every page load; does nothing unless an enquiry form was posted. */
	public static function handle() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['pri_enquiry_submit'] ) ) {
			return;
		}

		$scope = isset( $_POST['pri_scope'] ) ? absint( $_POST['pri_scope'] ) : 0; // 0 = dropdown form.
		$state = array( 'scope' => $scope, 'errors' => array(), 'values' => array(), 'program' => $scope );

		$fields = array();
		foreach ( array( 'name', 'email', 'phone', 'message' ) as $f ) {
			$fields[ $f ] = isset( $_POST[ 'pri_f_' . $f ] ) ? wp_unslash( $_POST[ 'pri_f_' . $f ] ) : '';
		}
		// Keep what was typed so an error does not wipe the form.
		$state['values']  = array_map( 'sanitize_textarea_field', $fields );
		$state['program'] = $scope ? $scope : ( isset( $_POST['pri_program'] ) ? absint( $_POST['pri_program'] ) : 0 );

		// 1. Honeypot: real visitors never see this field. Pretend success so the bot learns nothing.
		if ( ! empty( $_POST['pri_website'] ) ) {
			self::done( $scope );
		}

		// 2. Signed timestamp: proves the form was loaded from this site a few seconds or more ago.
		$token = isset( $_POST['pri_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pri_token'] ) ) : '';
		if ( ! self::check_token( $token, $scope ) ) {
			$state['errors']['form'] = __( 'Please press Send Enquiry once more.', 'pri-programs-manager' );
			self::$state = $state;
			return;
		}

		// 3. Hourly limit per visitor.
		if ( self::rate_limited() ) {
			$state['errors']['form'] = __( 'Too many enquiries from your connection. Please try again later.', 'pri-programs-manager' );
			self::$state = $state;
			return;
		}

		// 4. Field checks.
		list( $values, $errors ) = self::validate( $fields );
		$state['values'] = $values;

		// 5. The chosen program must be a real, published program (or 0 = general enquiry).
		$program_id = $state['program'];
		$program    = null;
		if ( $program_id ) {
			$post = get_post( $program_id );
			if ( ! $post || PRI_PM_Post_Types::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
				$errors['program'] = __( 'Please choose a program from the list.', 'pri-programs-manager' );
			} else {
				$program = array(
					'title'    => get_the_title( $post ),
					'dates'    => PRI_PM_Helpers::date_range( $program_id ),
					'location' => (string) get_post_meta( $program_id, '_pri_location', true ),
				);
			}
		}

		if ( $errors ) {
			$state['errors'] = $errors;
			self::$state     = $state;
			return;
		}

		// 6. Send.
		self::count_attempt();
		$mail = self::build_mail(
			$values,
			$program,
			PRI_PM_Settings::recipient( $program_id ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$program_id ? get_permalink( $program_id ) : home_url( '/' )
		);
		$sent = wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );

		if ( ! $sent ) {
			$state['errors']['form'] = __( 'Sorry, your message could not be sent. Please try again shortly or contact us directly.', 'pri-programs-manager' );
			self::$state = $state;
			return;
		}

		self::done( $scope );
	}

	/** Redirect back to the page with a success flag (stops double sending on refresh). */
	private static function done( $scope ) {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$url  = ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
		$url  = remove_query_arg( array( 'pri_enquiry', 'pri_scope' ), $url );
		$url  = add_query_arg( array( 'pri_enquiry' => 'sent', 'pri_scope' => $scope ), $url );
		// wp_safe_redirect() refuses any host that is not this site, which guards against a forged Host header.
		wp_safe_redirect( $url . '#pri-enquiry' );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Display
	 * ------------------------------------------------------------------ */

	/** Programs a visitor can still enquire about (Upcoming or Ongoing): id => title. */
	private static function open_programs() {
		$out = array();
		$ids = get_posts( array(
			'post_type'      => PRI_PM_Post_Types::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		foreach ( $ids as $id ) {
			if ( 'past' !== PRI_PM_Status::get_status( $id ) ) {
				$out[ $id ] = get_the_title( $id );
			}
		}
		return $out;
	}

	/** Used by the program page: a form for Upcoming/Ongoing programs, nothing for Past ones. */
	public static function render_section( $program_id, $status ) {
		if ( 'past' === $status ) {
			return '';
		}
		return self::render_form( (int) $program_id, 'pri-enquiry' );
	}

	public static function shortcode( $atts ) {
		$a = shortcode_atts( array( 'program' => 0 ), $atts, 'pri_enquiry' );
		return self::render_form( absint( $a['program'] ), 'pri-enquiry' );
	}

	/**
	 * The form. $scope = a program ID (fixed to that program) or 0 (visitor picks from a dropdown).
	 * $anchor is the HTML id of the wrapper, so "Book Your Spot" can jump straight to it.
	 */
	public static function render_form( $scope, $anchor = 'pri-enquiry' ) {
		wp_enqueue_style( 'pri-pm-frontend' );
		wp_enqueue_script( 'pri-pm-frontend' );

		$state = ( self::$state && self::$state['scope'] === (int) $scope ) ? self::$state : null;
		$err   = $state ? $state['errors'] : array();
		$val   = $state ? $state['values'] : array();
		$val   = array_merge( array( 'name' => '', 'email' => '', 'phone' => '', 'message' => '' ), $val );
		$sel   = $state ? (int) $state['program'] : (int) $scope;

		$sent = isset( $_GET['pri_enquiry'], $_GET['pri_scope'] ) && 'sent' === $_GET['pri_enquiry'] && absint( $_GET['pri_scope'] ) === (int) $scope;

		ob_start();
		echo '<section class="pri-enquiry" id="' . esc_attr( $anchor ) . '">';

		if ( $sent ) {
			echo '<div class="pri-notice pri-notice-ok" role="status">' . esc_html( PRI_PM_Settings::thank_you() ) . '</div></section>';
			return ob_get_clean();
		}

		$open = $scope ? array() : self::open_programs();
		$k    = (int) $scope; // Makes field ids unique when two forms share a page.
		?>
		<h2 class="pri-enquiry-title"><?php esc_html_e( 'Book Your Spot / Enquire', 'pri-programs-manager' ); ?></h2>
		<p class="pri-enquiry-intro"><?php esc_html_e( 'Send us a message and we will reply by email.', 'pri-programs-manager' ); ?></p>

		<?php if ( isset( $err['form'] ) ) : ?>
			<div class="pri-notice pri-notice-err" role="alert"><?php echo esc_html( $err['form'] ); ?></div>
		<?php endif; ?>

		<form class="pri-form pri-enquiry-form" method="post" action="#<?php echo esc_attr( $anchor ); ?>" novalidate>
			<input type="hidden" name="pri_scope" value="<?php echo esc_attr( $scope ); ?>">
			<input type="hidden" name="pri_token" value="<?php echo esc_attr( self::make_token( $scope ) ); ?>">

			<?php if ( ! $scope ) : ?>
				<div class="pri-form-row">
					<label for="pri-f-program-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Program', 'pri-programs-manager' ); ?></label>
					<select id="pri-f-program-<?php echo esc_attr( $k ); ?>" name="pri_program">
						<option value="0"><?php esc_html_e( 'General enquiry', 'pri-programs-manager' ); ?></option>
						<?php foreach ( $open as $id => $title ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $sel, $id ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php self::error_line( $err, 'program' ); ?>
				</div>
			<?php endif; ?>

			<div class="pri-form-row">
				<label for="pri-f-name-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Full name', 'pri-programs-manager' ); ?> <span class="pri-req">*</span></label>
				<input type="text" id="pri-f-name-<?php echo esc_attr( $k ); ?>" name="pri_f_name" value="<?php echo esc_attr( $val['name'] ); ?>" autocomplete="name" required maxlength="100">
				<?php self::error_line( $err, 'name' ); ?>
			</div>

			<div class="pri-field-row">
				<div class="pri-form-row">
					<label for="pri-f-email-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Email', 'pri-programs-manager' ); ?> <span class="pri-req">*</span></label>
					<input type="email" id="pri-f-email-<?php echo esc_attr( $k ); ?>" name="pri_f_email" value="<?php echo esc_attr( $val['email'] ); ?>" autocomplete="email" required>
					<?php self::error_line( $err, 'email' ); ?>
				</div>
				<div class="pri-form-row">
					<label for="pri-f-phone-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Phone (optional)', 'pri-programs-manager' ); ?></label>
					<input type="tel" id="pri-f-phone-<?php echo esc_attr( $k ); ?>" name="pri_f_phone" value="<?php echo esc_attr( $val['phone'] ); ?>" autocomplete="tel" maxlength="30">
					<?php self::error_line( $err, 'phone' ); ?>
				</div>
			</div>

			<div class="pri-form-row">
				<label for="pri-f-message-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Message', 'pri-programs-manager' ); ?> <span class="pri-req">*</span></label>
				<textarea id="pri-f-message-<?php echo esc_attr( $k ); ?>" name="pri_f_message" rows="5" required maxlength="3000"><?php echo esc_textarea( $val['message'] ); ?></textarea>
				<?php self::error_line( $err, 'message' ); ?>
			</div>

			<?php // Honeypot: hidden from people (off-screen, no tab stop). Bots fill every field they find. ?>
			<p class="pri-hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'pri-programs-manager' ); ?>
					<input type="text" name="pri_website" value="" tabindex="-1" autocomplete="off">
				</label>
			</p>

			<p class="pri-submit-row">
				<button type="submit" name="pri_enquiry_submit" value="1" class="pri-submit"><?php esc_html_e( 'Send Enquiry', 'pri-programs-manager' ); ?></button>
			</p>
		</form>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function error_line( $errors, $key ) {
		if ( isset( $errors[ $key ] ) ) {
			echo '<span class="pri-field-error">' . esc_html( $errors[ $key ] ) . '</span>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Per-program recipient (admin)
	 * ------------------------------------------------------------------ */

	public static function add_meta_box() {
		add_meta_box(
			'pri_pm_enquiry_email',
			__( 'Enquiry email', 'pri-programs-manager' ),
			array( __CLASS__, 'render_meta_box' ),
			PRI_PM_Post_Types::POST_TYPE,
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'pri_pm_save_enquiry_email', 'pri_pm_enquiry_email_nonce' );
		$email = get_post_meta( $post->ID, '_pri_enquiry_email', true );
		?>
		<p>
			<label for="pri_enquiry_email_override"><?php esc_html_e( 'Send this program\'s enquiries to:', 'pri-programs-manager' ); ?></label>
			<input type="email" class="widefat" id="pri_enquiry_email_override" name="pri_enquiry_email_override"
				value="<?php echo esc_attr( $email ); ?>" placeholder="<?php echo esc_attr( PRI_PM_Settings::recipient() ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the default address from Programs > Settings.', 'pri-programs-manager' ); ?></p>
		<?php
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['pri_pm_enquiry_email_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pri_pm_enquiry_email_nonce'] ) ), 'pri_pm_save_enquiry_email' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$email = isset( $_POST['pri_enquiry_email_override'] ) ? sanitize_email( wp_unslash( $_POST['pri_enquiry_email_override'] ) ) : '';
		if ( $email && is_email( $email ) ) {
			update_post_meta( $post_id, '_pri_enquiry_email', $email );
		} else {
			delete_post_meta( $post_id, '_pri_enquiry_email' );
		}
	}
}
