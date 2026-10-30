<?php
defined( 'ABSPATH' ) || exit;

/**
 * Programs > Settings. In v0.4 this holds the enquiry recipient and a test-email button.
 * (Button colours and more arrive in v0.6.)
 */
class PRI_PM_Settings {

	const OPTION = 'pri_pm_settings';
	const PAGE   = 'pri-pm-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_pri_pm_test_email', array( __CLASS__, 'send_test' ) );
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

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . PRI_PM_Post_Types::POST_TYPE,
			__( 'Programs Settings', 'pri-programs-manager' ),
			__( 'Settings', 'pri-programs-manager' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting( 'pri_pm_settings_group', self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => array(),
		) );
	}

	public static function sanitize( $input ) {
		$clean = array();
		$email = isset( $input['enquiry_email'] ) ? sanitize_email( $input['enquiry_email'] ) : '';
		if ( $email && ! is_email( $email ) ) {
			$email = '';
		}
		$clean['enquiry_email'] = $email;
		$clean['thank_you']     = isset( $input['thank_you'] ) ? sanitize_textarea_field( $input['thank_you'] ) : '';
		return $clean;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$email = self::get( 'enquiry_email' );
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

			<form method="post" action="options.php">
				<?php settings_fields( 'pri_pm_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pri_enquiry_email"><?php esc_html_e( 'Send enquiries to', 'pri-programs-manager' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="pri_enquiry_email"
								name="<?php echo esc_attr( self::OPTION ); ?>[enquiry_email]"
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
								name="<?php echo esc_attr( self::OPTION ); ?>[thank_you]"
								placeholder="<?php echo esc_attr( self::thank_you() ); ?>"><?php echo esc_textarea( self::get( 'thank_you' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown to the visitor after the form is sent. Leave empty for the default.', 'pri-programs-manager' ); ?></p>
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
