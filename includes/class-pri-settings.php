<?php
defined( 'ABSPATH' ) || exit;

/**
 * Programs > Settings.
 *   Enquiries   recipient address, thank-you message, test email (v0.4)
 *   Appearance  button colours (v0.6)
 *   Data        delete everything when the plugin is deleted (v0.6)
 *
 * Everything is stored in ONE option (pri_pm_settings), so uninstalling has one thing to remove.
 */
class PRI_PM_Settings {

	const OPTION = 'pri_pm_settings';
	const PAGE   = 'pri-pm-settings';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_pri_pm_test_email', array( __CLASS__, 'send_test' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PRI_PM_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function url() {
		return admin_url( 'edit.php?post_type=' . PRI_PM_Post_Types::POST_TYPE . '&page=' . self::PAGE );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'pri-programs-manager' ) . '</a>' );
		return $links;
	}

	public static function get( $key, $default = '' ) {
		$opts = get_option( self::OPTION, array() );
		return ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	public static function thank_you() {
		return self::get( 'thank_you', __( 'Thank you! Your message has been sent. We will get back to you shortly.', 'pri-programs-manager' ) );
	}

	/**
	 * Who receives enquiries: the program's own address if set,
	 * otherwise the address in Settings, otherwise the site admin's email.
	 * Falling back to the admin email means a form is never silently lost.
	 */
	public static function recipient( $program_id = 0 ) {
		if ( $program_id ) {
			$own = get_post_meta( $program_id, '_pri_enquiry_email', true );
			if ( $own && is_email( $own ) ) {
				return $own;
			}
		}
		$global = self::get( 'enquiry_email' );
		if ( $global && is_email( $global ) ) {
			return $global;
		}
		return get_option( 'admin_email' );
	}

	/* ------------------------------------------------------------------ *
	 * Appearance
	 * ------------------------------------------------------------------ */

	public static function default_colors() {
		return array(
			'from'  => '#d946ef', // Primary button gradient, start.
			'to'    => '#06b6d4', // Primary button gradient, end.
			'muted' => '#6b7280', // "View Gallery & Stories" button.
		);
	}

	public static function colors() {
		$saved = self::get( 'colors', array() );
		$out   = self::default_colors();
		if ( is_array( $saved ) ) {
			foreach ( $out as $k => $default ) {
				if ( ! empty( $saved[ $k ] ) && preg_match( '/^#[0-9a-f]{6}$/i', $saved[ $k ] ) ) {
					$out[ $k ] = $saved[ $k ];
				}
			}
		}
		return $out;
	}

	/**
	 * CSS that overrides the colour variables in frontend.css.
	 * Only hex colours ever reach this string (checked in colors()), so it cannot carry injected CSS.
	 */
	public static function inline_css() {
		$c = self::colors();
		return sprintf(
			'.pri-programs,.pri-enquiry,.pri-single-meta,.pri-single-section{--pri-from:%1$s;--pri-to:%2$s;--pri-btn-muted:%3$s;}',
			$c['from'],
			$c['to'],
			$c['muted']
		);
	}

	/* ------------------------------------------------------------------ *
	 * Admin page
	 * ------------------------------------------------------------------ */

	public static function menu() {
		self::$hook = add_submenu_page(
			'edit.php?post_type=' . PRI_PM_Post_Types::POST_TYPE,
			__( 'Programs Settings', 'pri-programs-manager' ),
			__( 'Settings', 'pri-programs-manager' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue_admin( $hook ) {
		if ( $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_script( 'pri-pm-admin-settings', PRI_PM_URL . 'assets/js/admin-settings.js', array( 'jquery' ), PRI_PM_VERSION, true );
	}

	public static function register() {
		register_setting( 'pri_pm_settings_group', self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => array(),
		) );
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$email = isset( $input['enquiry_email'] ) ? sanitize_email( $input['enquiry_email'] ) : '';
		$clean['enquiry_email'] = ( $email && is_email( $email ) ) ? $email : '';
		$clean['thank_you']     = isset( $input['thank_you'] ) ? sanitize_textarea_field( $input['thank_you'] ) : '';

		// Colours: keep only valid #rrggbb values. "Reset" (or a value equal to the default) stores nothing.
		$clean['colors'] = array();
		if ( empty( $input['reset_colors'] ) && isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
			$defaults = self::default_colors();
			foreach ( $defaults as $k => $default ) {
				$v = isset( $input['colors'][ $k ] ) ? sanitize_text_field( $input['colors'][ $k ] ) : '';
				if ( preg_match( '/^#[0-9a-f]{6}$/i', $v ) && strtolower( $v ) !== $default ) {
					$clean['colors'][ $k ] = strtolower( $v );
				}
			}
		}

		$clean['delete_data'] = empty( $input['delete_data'] ) ? '' : '1';
		return $clean;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$email    = self::get( 'enquiry_email' );
		$colors   = self::colors();
		$opt      = esc_attr( self::OPTION );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Programs Settings', 'pri-programs-manager' ); ?></h1>

			<?php
			// Result of the "Send test email" button.
			if ( isset( $_GET['pri_test'] ) ) {
				if ( '1' === $_GET['pri_test'] ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email handed to the mail system. Check the inbox (and spam folder) of the address below.', 'pri-programs-manager' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'WordPress could not send the test email. Your hosting may block PHP mail: install an SMTP plugin (such as WP Mail SMTP) and try again.', 'pri-programs-manager' ) . '</p></div>';
				}
			}
			settings_errors();
			?>

			<style>
				.pri-color-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
				.pri-color-row input[type=color]{width:48px;height:32px;padding:0;border:1px solid #8c8f94;border-radius:4px}
				.pri-grad-preview{display:inline-block;margin-top:6px;padding:9px 24px;border-radius:999px;color:#fff;font-weight:700}
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( 'pri_pm_settings_group' ); ?>

				<h2><?php esc_html_e( 'Enquiries', 'pri-programs-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pri_enquiry_email"><?php esc_html_e( 'Send enquiries to', 'pri-programs-manager' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="pri_enquiry_email"
								name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[enquiry_email]"
								value="<?php echo esc_attr( $email ); ?>"
								placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description">
								<?php esc_html_e( 'Default address for all enquiries. Leave empty to use the site admin email. A single program can use a different address (see "Enquiry email" on its edit screen).', 'pri-programs-manager' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pri_thank_you"><?php esc_html_e( 'Thank-you message', 'pri-programs-manager' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="pri_thank_you"
								name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[thank_you]"
								placeholder="<?php echo esc_attr( self::thank_you() ); ?>"><?php echo esc_textarea( self::get( 'thank_you' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown to the visitor after the form is sent. Leave empty for the default.', 'pri-programs-manager' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Appearance', 'pri-programs-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Main button colours', 'pri-programs-manager' ); ?></th>
						<td>
							<div class="pri-color-row">
								<input type="color" id="pri_c_from" name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[colors][from]" value="<?php echo esc_attr( $colors['from'] ); ?>">
								<label for="pri_c_from"><?php esc_html_e( 'Start colour', 'pri-programs-manager' ); ?></label>
								<input type="color" id="pri_c_to" name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[colors][to]" value="<?php echo esc_attr( $colors['to'] ); ?>">
								<label for="pri_c_to"><?php esc_html_e( 'End colour', 'pri-programs-manager' ); ?></label>
							</div>
							<span class="pri-grad-preview" id="pri-grad-preview" style="background:linear-gradient(90deg,<?php echo esc_attr( $colors['from'] ); ?>,<?php echo esc_attr( $colors['to'] ); ?>)"><?php esc_html_e( 'Book Your Spot', 'pri-programs-manager' ); ?></span>
							<p class="description"><?php esc_html_e( 'Used by "Book Your Spot" and "Send Enquiry". Pick the same colour twice for a solid button.', 'pri-programs-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pri_c_muted"><?php esc_html_e( 'Gallery button colour', 'pri-programs-manager' ); ?></label></th>
						<td>
							<input type="color" id="pri_c_muted" name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[colors][muted]" value="<?php echo esc_attr( $colors['muted'] ); ?>">
							<p class="description"><?php esc_html_e( 'The "View Gallery & Stories" button on past programs.', 'pri-programs-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"></th>
						<td><label><input type="checkbox" name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[reset_colors]" value="1"> <?php esc_html_e( 'Reset all colours to the defaults when I save', 'pri-programs-manager' ); ?></label></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Data', 'pri-programs-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'When the plugin is deleted', 'pri-programs-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo $opt; // phpcs:ignore WordPress.Security.EscapeOutput ?>[delete_data]" value="1" <?php checked( self::get( 'delete_data' ), '1' ); ?>>
								<?php esc_html_e( 'Also delete all programs, program types, gallery and document links, and these settings', 'pri-programs-manager' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default, so deleting the plugin never removes your programs. Photos, videos and PDFs stay in the Media Library either way. Deactivating the plugin never deletes anything.', 'pri-programs-manager' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Check that email works', 'pri-programs-manager' ); ?></h2>
			<p><?php
				/* translators: %s: email address */
				printf( esc_html__( 'Sends a test message to %s using the saved setting.', 'pri-programs-manager' ), '<strong>' . esc_html( self::recipient() ) . '</strong>' );
			?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pri_pm_test_email">
				<?php wp_nonce_field( 'pri_pm_test_email' ); ?>
				<?php submit_button( __( 'Send test email', 'pri-programs-manager' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function send_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'pri-programs-manager' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'pri_pm_test_email' );

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$sent = wp_mail(
			self::recipient(),
			'[' . $site . '] Test email from PRI Programs Manager',
			"This is a test message.\n\nIf you can read this, enquiries from your program forms will reach this address."
		);
		wp_safe_redirect( add_query_arg( 'pri_test', $sent ? '1' : '0', self::url() ) );
		exit;
	}
}
