<?php
defined( 'ABSPATH' ) || exit;

/**
 * v0.5 - Photos and videos for a program.
 *
 * Admin: an "Event Gallery" box on the program edit screen.
 *   - Photos: picked from the Media Library (several at once), drag to reorder.
 *   - Videos: a YouTube or Vimeo link, or a video file uploaded to the Media Library.
 * Front end: shown on the program page once the program is Past (the summary is shown
 * there too). Photos open in a lightbox; videos play in place.
 *
 * Storage (plain post meta, no extra tables):
 *   _pri_photos  = array of attachment IDs, in display order
 *   _pri_videos  = array of array( 'id' => attachment ID or 0, 'url' => link or '', 'label' => caption )
 */
class PRI_PM_Gallery {

	const PHOTOS_KEY   = '_pri_photos';
	const VIDEOS_KEY   = '_pri_videos';
	const NONCE_ACTION = 'pri_pm_save_gallery';
	const NONCE_NAME   = 'pri_pm_gallery_nonce';
	const MAX_PHOTOS   = 200;
	const MAX_VIDEOS   = 30;

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . PRI_PM_Post_Types::POST_TYPE, array( __CLASS__, 'save' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'redirect_post_location', array( __CLASS__, 'flag_rejected' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'rejected_notice' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Pure helper: understand a YouTube / Vimeo link (no WordPress calls)
	 * ------------------------------------------------------------------ */

	/**
	 * Returns array( 'provider' => 'youtube'|'vimeo', 'id' => ..., 'embed' => iframe URL ) or null.
	 * Only these two providers are accepted, so a pasted link can never put an arbitrary
	 * website inside an iframe on your pages.
	 */
	public static function parse_video( $url ) {
		$url = trim( (string) $url );
		$p   = parse_url( $url );
		if ( ! $p || empty( $p['host'] ) || empty( $p['scheme'] ) || ! in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) ) {
			return null;
		}
		$host = strtolower( preg_replace( '/^(www\.|m\.|music\.)/i', '', $p['host'] ) );
		$path = isset( $p['path'] ) ? $p['path'] : '';
		parse_str( isset( $p['query'] ) ? $p['query'] : '', $q );

		if ( 'youtu.be' === $host && preg_match( '#^/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $m ) ) {
			$id = $m[1];
		} elseif ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com' ), true ) ) {
			if ( '/watch' === $path && isset( $q['v'] ) && preg_match( '/^[A-Za-z0-9_-]{11}$/', $q['v'] ) ) {
				$id = $q['v'];
			} elseif ( preg_match( '#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})(?:/|$)#', $path, $m ) ) {
				$id = $m[1];
			}
		}
		if ( isset( $id ) ) {
			return array( 'provider' => 'youtube', 'id' => $id, 'embed' => 'https://www.youtube-nocookie.com/embed/' . $id );
		}

		if ( in_array( $host, array( 'vimeo.com', 'player.vimeo.com' ), true ) ) {
			// vimeo.com/123456, vimeo.com/channels/x/123456, player.vimeo.com/video/123456,
			// and unlisted videos: vimeo.com/123456/abcdef1234 (or ?h=abcdef1234).
			if ( preg_match( '#^/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{5,12})(?:/([A-Za-z0-9]+))?/?$#', $path, $m ) ) {
				$hash  = isset( $m[2] ) ? $m[2] : ( isset( $q['h'] ) ? $q['h'] : '' );
				$embed = 'https://player.vimeo.com/video/' . $m[1];
				if ( $hash && preg_match( '/^[A-Za-z0-9]+$/', $hash ) ) {
					$embed .= '?h=' . $hash;
				}
				return array( 'provider' => 'vimeo', 'id' => $m[1], 'embed' => $embed );
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------ *
	 * Reading
	 * ------------------------------------------------------------------ */

	/** Photo attachment IDs that still exist, in order. */
	public static function photo_ids( $post_id ) {
		$raw = get_post_meta( $post_id, self::PHOTOS_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( $id && wp_get_attachment_url( $id ) ) { // Skips photos deleted from the Media Library.
				$out[] = $id;
			}
		}
		return $out;
	}

	/** Videos ready for display: array( 'kind' => 'embed'|'file', 'src' => URL, 'label' => ... ). */
	public static function videos( $post_id ) {
		$raw = get_post_meta( $post_id, self::VIDEOS_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			$label = isset( $row['label'] ) ? (string) $row['label'] : '';
			$id    = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id ) {
				$src = wp_get_attachment_url( $id );
				if ( $src ) {
					$out[] = array( 'kind' => 'file', 'src' => $src, 'label' => $label, 'poster' => '' );
				}
			} elseif ( ! empty( $row['url'] ) ) {
				$v = self::parse_video( $row['url'] );
				if ( $v ) {
					$out[] = array( 'kind' => 'embed', 'src' => $v['embed'], 'label' => $label, 'provider' => $v['provider'] );
				}
			}
		}
		return $out;
	}

	/** array( photos, videos ) counts, for cards. */
	public static function counts( $post_id ) {
		return array( count( self::photo_ids( $post_id ) ), count( self::videos( $post_id ) ) );
	}

	/* ------------------------------------------------------------------ *
	 * Front end
	 * ------------------------------------------------------------------ */

	public static function register_assets() {
		wp_register_script( 'pri-pm-gallery', PRI_PM_URL . 'assets/js/gallery.js', array(), PRI_PM_VERSION, true );
	}

	/** Photo grid + video grid. Returns '' when there is nothing to show. */
	public static function render( $post_id ) {
		$photos = self::photo_ids( $post_id );
		$videos = self::videos( $post_id );
		if ( ! $photos && ! $videos ) {
			return '';
		}
		wp_enqueue_style( 'pri-pm-frontend' );
		wp_enqueue_script( 'pri-pm-gallery' );

		$title = get_the_title( $post_id );
		$html  = '<section id="pri-gallery" class="pri-single-section pri-gallery">';
		$html .= PRI_PM_Single::title( __( 'Gallery', 'pri-programs-manager' ) );

		if ( $photos ) {
			$html .= '<h3 class="pri-gallery-sub">' . esc_html__( 'Photos', 'pri-programs-manager' ) . '</h3>';
			$html .= '<div class="pri-photos">';
			foreach ( $photos as $i => $aid ) {
				$full    = wp_get_attachment_image_url( $aid, 'full' );
				$large   = wp_get_attachment_image_url( $aid, 'large' );
				$caption = wp_get_attachment_caption( $aid );
				$alt     = get_post_meta( $aid, '_wp_attachment_image_alt', true );
				if ( '' === $alt ) {
					/* translators: 1: program title, 2: photo number */
					$alt = sprintf( __( '%1$s, photo %2$d', 'pri-programs-manager' ), $title, $i + 1 );
				}
				$img = wp_get_attachment_image( $aid, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => $alt ) );
				// The link points at the large image, so photos still open without JavaScript.
				$html .= sprintf(
					'<a class="pri-photo" href="%s" data-full="%s" data-caption="%s">%s</a>',
					esc_url( $large ? $large : $full ),
					esc_url( $full ? $full : $large ),
					esc_attr( $caption ),
					$img // Core-generated <img>.
				);
			}
			$html .= '</div>';
		}

		if ( $videos ) {
			$html .= '<h3 class="pri-gallery-sub">' . esc_html__( 'Videos', 'pri-programs-manager' ) . '</h3>';
			$html .= '<div class="pri-videos">';
			foreach ( $videos as $n => $v ) {
				$label = $v['label'] ? $v['label'] : sprintf( /* translators: %d: video number */ __( 'Video %d', 'pri-programs-manager' ), $n + 1 );
				$html .= '<figure class="pri-video"><div class="pri-video-frame">';
				if ( 'file' === $v['kind'] ) {
					$html .= '<video controls preload="metadata" playsinline src="' . esc_url( $v['src'] ) . '"></video>';
				} else {
					// loading=lazy: the video player only loads when scrolled into view.
					$html .= '<iframe src="' . esc_url( $v['src'] ) . '" title="' . esc_attr( $label ) . '" loading="lazy" '
						. 'allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" '
						. 'referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
				}
				$html .= '</div>';
				if ( $v['label'] ) {
					$html .= '<figcaption>' . esc_html( $v['label'] ) . '</figcaption>';
				}
				$html .= '</figure>';
			}
			$html .= '</div>';
		}
		return $html . '</section>';
	}

	/* ------------------------------------------------------------------ *
	 * Admin: the meta box
	 * ------------------------------------------------------------------ */

	public static function add() {
		add_meta_box(
			'pri_pm_gallery',
			__( 'Event Gallery (photos and videos)', 'pri-programs-manager' ),
			array( __CLASS__, 'render_box' ),
			PRI_PM_Post_Types::POST_TYPE,
			'normal',
			'default'
		);
	}

	public static function enqueue_admin( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || PRI_PM_Post_Types::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'pri-pm-admin-gallery', PRI_PM_URL . 'assets/js/admin-gallery.js', array( 'jquery', 'jquery-ui-sortable', 'wp-data' ), PRI_PM_VERSION, true );
		wp_localize_script( 'pri-pm-admin-gallery', 'priPmGallery', array(
			'photoTitle'  => __( 'Choose photos', 'pri-programs-manager' ),
			'photoButton' => __( 'Add to gallery', 'pri-programs-manager' ),
			'videoTitle'  => __( 'Choose a video file', 'pri-programs-manager' ),
			'videoButton' => __( 'Use this video', 'pri-programs-manager' ),
			'remove'      => __( 'Remove', 'pri-programs-manager' ),
			'noFile'      => __( 'No file chosen', 'pri-programs-manager' ),
			'badLink'     => __( 'This is not a YouTube or Vimeo video link, so it will not be saved. Paste the link from the video page, e.g. https://www.youtube.com/watch?v=... or https://vimeo.com/123456789', 'pri-programs-manager' ),
			'badLinkNotice' => __( 'A video link was not saved: only YouTube links, Vimeo links and uploaded video files are accepted. Please check the link in the Event Gallery box.', 'pri-programs-manager' ),
		) );
	}

	public static function render_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$photos = self::photo_ids( $post->ID );
		$raw    = get_post_meta( $post->ID, self::VIDEOS_KEY, true );
		$raw    = is_array( $raw ) ? $raw : array();
		?>
		<style>
			.pri-g-thumbs{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0;min-height:10px}
			.pri-g-thumb{position:relative;width:90px;height:90px;border:1px solid #dcdcde;border-radius:4px;overflow:hidden;cursor:move;background:#f6f7f7}
			.pri-g-thumb img{width:100%;height:100%;object-fit:cover;display:block}
			.pri-g-thumb button{position:absolute;top:2px;right:2px;width:22px;height:22px;line-height:18px;padding:0;border:0;border-radius:50%;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;font-size:14px}
			.pri-g-thumb button:hover{background:#d63638}
			.pri-v-row{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:8px;margin-bottom:6px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px}
			.pri-v-row .pri-v-url{flex:2 1 260px}.pri-v-row .pri-v-label{flex:1 1 160px}
			.pri-v-file{color:#50575e}
			.pri-g-h{margin:18px 0 4px;font-weight:600}
		</style>

		<p class="description"><?php esc_html_e( 'These appear on the program page once the program is Past, below the event summary. You can add them any time before that.', 'pri-programs-manager' ); ?></p>

		<p class="pri-g-h"><?php esc_html_e( 'Photos', 'pri-programs-manager' ); ?></p>
		<div class="pri-g-thumbs" id="pri-g-thumbs">
			<?php foreach ( $photos as $aid ) : ?>
				<div class="pri-g-thumb">
					<?php echo wp_get_attachment_image( $aid, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- core <img>. ?>
					<input type="hidden" name="pri_photos[]" value="<?php echo esc_attr( $aid ); ?>">
					<button type="button" class="pri-g-remove" title="<?php esc_attr_e( 'Remove', 'pri-programs-manager' ); ?>">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button button-secondary" id="pri-g-add"><?php esc_html_e( '+ Add photos', 'pri-programs-manager' ); ?></button>
		<span class="description"><?php esc_html_e( 'Select several at once. Drag to reorder. Captions come from the Media Library.', 'pri-programs-manager' ); ?></span>

		<p class="pri-g-h"><?php esc_html_e( 'Videos', 'pri-programs-manager' ); ?></p>
		<p class="description"><?php esc_html_e( 'Paste a YouTube or Vimeo link, or upload a video file (MP4 works everywhere). Large videos play better from YouTube or Vimeo.', 'pri-programs-manager' ); ?></p>
		<div id="pri-v-list">
			<?php
			foreach ( $raw as $i => $row ) {
				self::video_row( 'r' . $i, $row );
			}
			?>
		</div>
		<button type="button" class="button button-secondary" id="pri-v-add"><?php esc_html_e( '+ Add video', 'pri-programs-manager' ); ?></button>

		<script type="text/template" id="pri-v-template">
			<?php self::video_row( '__INDEX__', array() ); ?>
		</script>
		<?php
	}

	private static function video_row( $index, $row ) {
		$id    = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$url   = isset( $row['url'] ) ? $row['url'] : '';
		$label = isset( $row['label'] ) ? $row['label'] : '';
		$file  = $id ? wp_basename( (string) get_attached_file( $id ) ) : '';
		$n     = esc_attr( $index );
		?>
		<div class="pri-v-row">
			<span class="pri-v-handle dashicons dashicons-menu" style="cursor:move;color:#8c8f94"></span>
			<input type="url" class="pri-v-url" name="pri_videos[<?php echo $n; // phpcs:ignore WordPress.Security.EscapeOutput ?>][url]"
				value="<?php echo esc_attr( $url ); ?>" placeholder="<?php esc_attr_e( 'YouTube or Vimeo link', 'pri-programs-manager' ); ?>"
				<?php echo $id ? 'style="display:none"' : ''; ?>>
			<input type="hidden" class="pri-v-id" name="pri_videos[<?php echo $n; // phpcs:ignore WordPress.Security.EscapeOutput ?>][id]" value="<?php echo esc_attr( $id ); ?>">
			<span class="pri-v-file" <?php echo $id ? '' : 'style="display:none"'; ?>><?php echo esc_html( $file ); ?></span>
			<input type="text" class="pri-v-label" name="pri_videos[<?php echo $n; // phpcs:ignore WordPress.Security.EscapeOutput ?>][label]"
				value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Caption (optional)', 'pri-programs-manager' ); ?>">
			<button type="button" class="button pri-v-upload"><?php esc_html_e( 'Upload video file', 'pri-programs-manager' ); ?></button>
			<button type="button" class="button-link button-link-delete pri-v-remove"><?php esc_html_e( 'Remove', 'pri-programs-manager' ); ?></button>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ *
	 * Admin: saving
	 * ------------------------------------------------------------------ */

	private static $rejected = 0;

	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Photos: only real image attachments. Without this check a crafted request could list any post ID.
		$photos = array();
		$in     = isset( $_POST['pri_photos'] ) && is_array( $_POST['pri_photos'] ) ? wp_unslash( $_POST['pri_photos'] ) : array();
		foreach ( $in as $id ) {
			$id = absint( $id );
			if ( $id && 'attachment' === get_post_type( $id ) && 0 === strpos( (string) get_post_mime_type( $id ), 'image/' ) ) {
				$photos[ $id ] = $id; // Keyed by ID, so a photo added twice is kept once.
			}
		}
		$photos = array_slice( array_values( $photos ), 0, self::MAX_PHOTOS );

		// Videos: an uploaded video file, or a YouTube/Vimeo link we can understand.
		$videos   = array();
		$rejected = 0;
		$rows     = isset( $_POST['pri_videos'] ) && is_array( $_POST['pri_videos'] ) ? wp_unslash( $_POST['pri_videos'] ) : array();
		foreach ( $rows as $row ) {
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$id    = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';

			if ( $id ) {
				if ( 'attachment' === get_post_type( $id ) && 0 === strpos( (string) get_post_mime_type( $id ), 'video/' ) ) {
					$videos[] = array( 'id' => $id, 'url' => '', 'label' => $label );
				} else {
					$rejected++;
				}
			} elseif ( '' !== $url ) {
				if ( self::parse_video( $url ) ) {
					$videos[] = array( 'id' => 0, 'url' => $url, 'label' => $label );
				} else {
					$rejected++;
				}
			}
		}
		$videos = array_slice( $videos, 0, self::MAX_VIDEOS );

		$photos ? update_post_meta( $post_id, self::PHOTOS_KEY, $photos ) : delete_post_meta( $post_id, self::PHOTOS_KEY );
		$videos ? update_post_meta( $post_id, self::VIDEOS_KEY, $videos ) : delete_post_meta( $post_id, self::VIDEOS_KEY );

		self::$rejected = $rejected;
	}

	/** Tell the editor if a video link was not accepted (otherwise it would just vanish). */
	public static function flag_rejected( $location, $post_id ) {
		return self::$rejected ? add_query_arg( 'pri_vid_rejected', self::$rejected, $location ) : $location;
	}

	public static function rejected_notice() {
		if ( isset( $_GET['pri_vid_rejected'] ) && absint( $_GET['pri_vid_rejected'] ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'A video was not saved: only YouTube links, Vimeo links and uploaded video files are accepted. Please check the link.', 'pri-programs-manager' )
			);
		}
	}
}
