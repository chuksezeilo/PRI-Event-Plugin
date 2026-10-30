<?php
defined( 'ABSPATH' ) || exit;

/**
 * v0.2 - PDF documents attached to a program (syllabus, agenda, case study...).
 *
 * The PDFs live in the normal WordPress Media Library. We only store a small
 * list of {label, attachment id} pairs in one meta field, so renaming or
 * replacing a file in the library never breaks the program.
 */
class PRI_PM_Documents {

	const META_KEY     = '_pri_documents';
	const NONCE_ACTION = 'pri_pm_save_docs';
	const NONCE_NAME   = 'pri_pm_docs_nonce';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post_' . PRI_PM_Post_Types::POST_TYPE, array( __CLASS__, 'save' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Documents ready for display: [ ['id'=>, 'label'=>, 'url'=>], ... ].
	 * Rows whose file was deleted from the Media Library are silently skipped.
	 */
	public static function get( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			$id  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$url = $id ? wp_get_attachment_url( $id ) : false;
			if ( ! $url ) {
				continue;
			}
			$label = ! empty( $row['label'] ) ? $row['label'] : get_the_title( $id );
			$out[] = array( 'id' => $id, 'label' => $label, 'url' => $url );
		}
		return $out;
	}

	public static function add() {
		add_meta_box(
			'pri_pm_documents',
			__( 'Program Documents (PDF)', 'pri-programs-manager' ),
			array( __CLASS__, 'render' ),
			PRI_PM_Post_Types::POST_TYPE,
			'normal',
			'default'
		);
	}

	/** Load the media uploader and our small script only on the program edit screen. */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || PRI_PM_Post_Types::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'pri-pm-admin-documents',
			PRI_PM_URL . 'assets/js/admin-documents.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			PRI_PM_VERSION,
			true
		);
		wp_localize_script( 'pri-pm-admin-documents', 'priPmDocs', array(
			'title'  => __( 'Choose a PDF document', 'pri-programs-manager' ),
			'button' => __( 'Use this PDF', 'pri-programs-manager' ),
			'none'   => __( 'No file chosen', 'pri-programs-manager' ),
		) );
	}

	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$raw = get_post_meta( $post->ID, self::META_KEY, true );
		$raw = is_array( $raw ) ? $raw : array();
		?>
		<style>
			.pri-doc-row{display:flex;align-items:center;gap:8px;padding:8px;margin-bottom:6px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px}
			.pri-doc-handle{cursor:move;color:#8c8f94}
			.pri-doc-label{width:200px}
			.pri-doc-file{flex:1;color:#50575e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.pri-doc-empty{color:#646970;font-style:italic;margin:0 0 8px}
		</style>

		<p class="description">
			<?php esc_html_e( 'Attach PDFs such as a syllabus, agenda or case study. Visitors will see them as download links. Drag rows to reorder.', 'pri-programs-manager' ); ?>
		</p>

		<div id="pri-docs-list">
			<?php
			foreach ( $raw as $i => $row ) {
				$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( $id ) {
					self::row( 'r' . $i, isset( $row['label'] ) ? $row['label'] : '', $id );
				}
			}
			?>
		</div>

		<p class="pri-doc-empty" id="pri-docs-empty" <?php echo $raw ? 'style="display:none"' : ''; ?>>
			<?php esc_html_e( 'No documents yet.', 'pri-programs-manager' ); ?>
		</p>

		<button type="button" class="button button-secondary" id="pri-doc-add">
			<?php esc_html_e( '+ Add PDF', 'pri-programs-manager' ); ?>
		</button>

		<datalist id="pri-doc-labels">
			<?php foreach ( array( 'Syllabus', 'Agenda', 'Case Study', 'Brochure', 'Report' ) as $l ) : ?>
				<option value="<?php echo esc_attr( $l ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<!-- Blank row copied by JS when "Add PDF" is clicked. __INDEX__ is swapped for a unique number. -->
		<script type="text/template" id="pri-doc-template">
			<?php self::row( '__INDEX__', '', 0 ); ?>
		</script>
		<?php
	}

	/** One editable row. */
	private static function row( $index, $label, $attachment_id ) {
		$file = $attachment_id ? wp_basename( (string) get_attached_file( $attachment_id ) ) : '';
		?>
		<div class="pri-doc-row">
			<span class="pri-doc-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'pri-programs-manager' ); ?>"></span>
			<input type="text" class="pri-doc-label" list="pri-doc-labels"
				name="pri_docs[<?php echo esc_attr( $index ); ?>][label]"
				value="<?php echo esc_attr( $label ); ?>"
				placeholder="<?php esc_attr_e( 'Label, e.g. Syllabus', 'pri-programs-manager' ); ?>">
			<input type="hidden" class="pri-doc-id"
				name="pri_docs[<?php echo esc_attr( $index ); ?>][id]"
				value="<?php echo esc_attr( $attachment_id ); ?>">
			<span class="pri-doc-file"><?php echo $file ? esc_html( $file ) : esc_html__( 'No file chosen', 'pri-programs-manager' ); ?></span>
			<button type="button" class="button pri-doc-choose"><?php esc_html_e( 'Choose PDF', 'pri-programs-manager' ); ?></button>
			<button type="button" class="button-link button-link-delete pri-doc-remove"><?php esc_html_e( 'Remove', 'pri-programs-manager' ); ?></button>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$clean = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field is sanitised below.
		$rows = isset( $_POST['pri_docs'] ) && is_array( $_POST['pri_docs'] ) ? wp_unslash( $_POST['pri_docs'] ) : array();

		// Rows arrive in the order they appear on screen, so drag-and-drop order is kept.
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			// Only accept real PDF attachments. Without this check, a crafted request could
			// attach any media item (or any post ID) as a "download".
			if ( ! $id || 'attachment' !== get_post_type( $id ) || 'application/pdf' !== get_post_mime_type( $id ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				$label = get_the_title( $id );
			}
			$clean[] = array( 'id' => $id, 'label' => $label );
		}

		if ( $clean ) {
			update_post_meta( $post_id, self::META_KEY, $clean );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}
}
