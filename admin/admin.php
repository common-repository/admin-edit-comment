<?php

class Admin_Edit_Comment_Settings_Page {

	/**
	 * Admin_Edit_Comment_Settings_Page constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'create_settings' ] );
		add_action( 'admin_init', [ $this, 'setup_sections' ] );
		add_action( 'admin_init', [ $this, 'setup_fields' ] );
	}

	public function create_settings() {
		$page_title = 'Admin Edit Comment';
		$menu_title = 'Admin Edit Comment';
		$capability = 'manage_options';
		$slug       = 'admin-edit-comment';
		$callback   = [ $this, 'settings_content' ];
		add_options_page( $page_title, $menu_title, $capability, $slug, $callback );
	}

	public function settings_content() { ?>
		<div class="wrap">
			<h1>Admin Edit Comment</h1>
			<form method="POST" action="options.php">
				<?php
				settings_fields( 'admin-edit-comment' );
				do_settings_sections( 'admin-edit-comment' );
				?>
				<span style="font-weight: bold;"><?php esc_html_e( 'Customization: ', 'admin-edit-comment' ); ?></span>
				<span><?php esc_html_e( 'You can override the setting above by using a filter hook named \'aec_activate_post_types\'.', 'admin-edit-comment' ); ?></span>
				<?php
				submit_button();
				?>
			</form>
		</div> <?php
	}

	public function setup_sections() {
		add_settings_section( 'admin-edit-comment_section', '', [], 'admin-edit-comment' );
	}

	public function setup_fields() {
		$fields = [
			[
				'label'   => __( 'Enable the function for these post types', 'admin-edit-comment' ),
				'id'      => Admin_Edit_Comment::ADMIN_EDIT_COMMENT_OPTIONS,
				'type'    => 'checkbox',
				'section' => 'admin-edit-comment_section',
			],
		];

		foreach ( $fields as $field ) {
			add_settings_field( $field['id'], $field['label'], [ $this, 'field_callback' ], 'admin-edit-comment', $field['section'], $field );
			register_setting( 'admin-edit-comment', $field['id'] );
		}
	}

	public function field_callback( $field ) {
		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
		$options    = get_option( Admin_Edit_Comment::ADMIN_EDIT_COMMENT_OPTIONS, Admin_Edit_Comment::DEFAULT_ACTIVE_POST_TYPE );

		foreach ( $post_types as $post_type ) {
			if ( Admin_Edit_Comment::POST_TYPE_NAME === $post_type->name || 'attachment' === $post_type->name ) {
				continue;
			}
			?>
			<label>
				<input name="<?php echo $field['id'] . '[]'; ?>"
				       id="<?php echo $field['id']; ?>"
				       type="<?php echo $field['type']; ?>"
				       value="<?php echo esc_html( $post_type->name ); ?>"
					<?php if ( $this->is_post_type_enabled( $options, $post_type->name ) ) {
						echo 'checked="checked"';
					} ?>
				/>
				<?php echo $post_type->labels->name; ?>
			</label>
			<br>
			<?php
		}
	}

	private function is_post_type_enabled( $options, $post_type ) {
		if ( ! is_array( $options ) ) {
			$options = (array) $options;
		}

		return in_array( $post_type, $options, true );
	}
}

if ( is_admin() ) {
	new Admin_Edit_Comment_Settings_Page();
}
