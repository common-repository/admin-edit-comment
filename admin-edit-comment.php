<?php
/*
 * Plugin Name: Admin Edit Comment
 * Description: Adding an extra comment functionality in post screen exclusively for your editorial team.
 * Version: 2.0.1
 * Author: PRESSMAN
 * Author URI: https://www.pressman.ne.jp/
 * License: GNU GPL v2 or higher
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: admin-edit-comment
 * Domain Path: /languages
 *
 * @author    PRESSMAN
 * @link      https://www.pressman.ne.jp/
 * @copyright Copyright (c) 2020, PRESSMAN
 */

// Deny accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Admin Edit Comment Class.
 *
 * @class Admin_Edit_Comment
 */
class Admin_Edit_Comment {

	const POST_TYPE_NAME = 'admin_edit_comment';
	const ADMIN_EDIT_COMMENT_OPTIONS = 'admin_edit_comment_options';

	const PLUGIN_ABBR = 'aec';
	const POSTS_PER_PAGE_FOR_COLUMN = 5;
	const COLUMN_NAME = 'Recent Edit Comments';
	const AEC_LIMIT_PER_POST = 100;

	const TYPE_NAME_COMMENT = 'comment';
	const TYPE_NAME_REVISION = 'revision';
	const TYPE_NAME_STATUS = 'status';

	/**
	 * This plugin is enabled by default on 'post' and 'page'.
	 */
	const DEFAULT_ACTIVE_POST_TYPE = array( 'post', 'page' );

	/**
	 * Version of this plugin.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * active post_type
	 *
	 * @var array
	 */
	public $active_post_types;

	/**
	 * The single instance of the class.
	 *
	 * @var Admin_Edit_Comment
	 */
	protected static $instance = null;

	/**
	 * Ensures only one instance of this class.
	 *
	 * @return Admin_Edit_Comment
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Admin_Edit_Comment constructor.
	 */
	public function __construct() {

		require_once plugin_dir_path( __FILE__ ) . 'admin/admin.php';

		register_uninstall_hook( __FILE__, 'aec_uninstall' );

		$plugin_data   = get_file_data( __FILE__, array(
			'version'    => 'Version',
			'TextDomain' => 'Text Domain',
		) );
		$this->version = $plugin_data['version'];
		$this->textdomain = $plugin_data['TextDomain'];

		add_action( 'wp', array( $this, 'filter_setting' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_head', array( $this, 'add_comment_box' ) );
		add_action( 'wp_ajax_aec_insert_comment', array( $this, 'insert_comment' ) );
		add_action( 'wp_ajax_aec_delete_comment', array( $this, 'delete_comment' ) );
		add_action( 'wp_ajax_aec_refresh_comment', array( $this, 'refresh_comment' ) );
		add_action( 'save_post', array( $this, 'revision_transitions' ), 11, 3 );
		add_action( 'transition_post_status', array( $this, 'status_transitions' ), 11, 3 );
		add_action( 'plugins_loaded', array( $this, 'load_text_domain' ) );
		add_action( 'admin_head-edit.php', array( $this, 'add_columns' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'css_for_columns' ) );
		add_action( 'save_post', array( $this, 'save_inline_edit_meta' ) );

	}

	/**
	 * Loads translated strings.
	 */
	public function load_text_domain() {
		load_plugin_textdomain( $this->textdomain, false, plugin_basename( plugin_dir_path( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register post type used by this plugin.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE_NAME,
			apply_filters( 'aec_register_post_type_args', [
				'label'        => 'AEC',
				'public'       => false,
				'hierarchical' => false,
				'supports'     => false,
				'rewrite'      => false,
			] )
		);
	}

	/**
	 * Adding comment box at editing screen.
	 */
	public function add_comment_box() {
		$screen            = get_current_screen();
		$active_post_types = apply_filters( 'aec_activate_post_types', get_option( self::ADMIN_EDIT_COMMENT_OPTIONS, self::DEFAULT_ACTIVE_POST_TYPE ) );
		if ( 'post' !== $screen->base || ! in_array( $screen->post_type, $active_post_types ) ) {
			return;
		}

		add_meta_box( 'admin_edit_comment', 'Admin Edit Comment', array(
			$this,
			'add_meta_box'
		), $active_post_types, 'side' );
		wp_enqueue_style( 'aec.css', plugin_dir_url( __FILE__ ) . 'assets/css/aec.css', array(), $this->version );
		wp_enqueue_script( 'aec_edit.js', plugin_dir_url( __FILE__ ) . 'assets/js/edit.js', array('jquery'), $this->version, true );
		wp_localize_script(
			'aec_edit.js',
			'localize',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'delete_failed_msg'  => __( 'Delete failed.', $this->textdomain ),
				'update_failed_msg'  => __( 'Update failed.', $this->textdomain ),
				'comments_limit_msg' => __( 'The number of comments exceeds the limit.', $this->textdomain ),
				'no_empty_msg'       => __( 'No empty.', $this->textdomain ),
			)
		);
	}

	/**
	 * Create meta box.
	 *
	 * @param WP_Post $post
	 */
	public function add_meta_box( WP_Post $post ) {
		$post_type = get_post_type( $post->ID );
		?>
		<div id='aec_checkbox_wrap'>
			<label>
				<input type="checkbox" id="aec_checkbox_<?php echo self::TYPE_NAME_COMMENT;?>" checked>
				<?php echo __( 'Comments' ); ?>
			</label>
		<?php
			if ( $this->is_revision_supported( $post_type ) ) :
		?>
			<label>
				<input type="checkbox" id="aec_checkbox_<?php echo self::TYPE_NAME_REVISION;?>" checked>
				<?php echo __( 'Revisions' ); ?>
			</label>
		<?php
			endif;
		?>
			<label>
				<input type="checkbox" id="aec_checkbox_<?php echo self::TYPE_NAME_STATUS;?>" checked>
				<?php echo __( 'Changed Status' , $this->textdomain ); ?>
			</label>
		</div>
		<div id="aec_comment_wrap">
			<?php echo $this->get_content_html( $post->ID ); ?>
		</div>
		<div id='aec_text_area_wrap'>
			<textarea name='aec_comment_text_area' placeholder='' rows='3'></textarea>
		</div>
		<div id='aec_submit_wrap'>
			<input class='button button-primary' type='button' name='aec_submit' value='<?php echo __( 'Send', $this->textdomain ); ?>'>
		</div>
		<?php
	}

	/**
	 * Get comments content.
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	private function get_content_html( $post_id, $mode = '' ) {
		$comments = get_posts( apply_filters( 'aec_get_post_args', array(
			'posts_per_page' => - 1,
			'post_type'      => self::POST_TYPE_NAME,
			'post_parent'    => $post_id,
		) ) );

		if ( ! $comments ) {
			return __( 'No comments yet.', $this->textdomain );
		}

		$count           = count( $comments );
		$multi_class     = ( $count > 1 ) ? 'has_multiple_item' : '';
		$limit           = ( $count >= apply_filters( 'aec_limit_per_post', self::AEC_LIMIT_PER_POST ) ) ? 'exceeds' : '';
		$content_content = <<<HTML
		<div class="aec-column-wrap {$multi_class}">
			<input type="hidden" name="aec_limit" value="{$limit}">
			<input type="checkbox" id="aec-accordion-switch_{$post_id}">
			<label for="aec-accordion-switch_{$post_id}" class="dashicons"></label>
				<div class="aec-data-wrap" data-posts-num="{$count}">
HTML;
		foreach ( $comments as $comment ) {
			$comment_text = $this->get_comment_text( $comment );

			if ($comment->post_excerpt) {
				$excerpt = $comment->post_excerpt;
			} else {
				$excerpt = self::TYPE_NAME_COMMENT;
			}
			$excerpt_name = __( $excerpt, $this->textdomain );

			if ( (int) wp_get_current_user()->ID === (int) $comment->post_author ) {
				$delete_button = ( 'comment' === $excerpt && 'column' !== $mode ) ? ' <span class="aec_delete dashicons dashicons-trash" comment_id="' . $comment->ID . '"></span>' : '';
				$is_others     = '';
				$author_name   = wp_get_current_user()->display_name;
				$avatar        = get_avatar( wp_get_current_user()->ID, 18 );
			} else {
				$delete_button = '';
				$is_others     = 'others';
				$author        = get_user_by( 'id', $comment->post_author );
				$author_name   = ( $author ) ? $author->display_name : '';
				$avatar        = get_avatar( $comment->post_author, 18 );
			}

			$content_content .= '<article id="aec-' . $comment->ID . '" class="' . $excerpt. ' ' . $is_others. ' aec-single">';

			if ( 'comment' === $excerpt ) {
				$content_content
					.= <<<HTML
						<picture class="aec-avatar">{$avatar}</picture>
						<div class="aec-content">
							<header class="aec-header">
								<div class="aec-author"><strong class="aec-author_name">{$author_name}</strong></div>
							</header>
							<div class="aec-content-body">
								{$comment_text}
								<div class="aec-content-footer"><span class="aec-content-date">{$comment->post_date}</span>{$delete_button}</div>
							</div>
						</div>
HTML;
			} else { // revision & status
				$content_content
					.= <<<HTML
						<div class="aec-content">
							<div class="aec-content-body"><span class="{$excerpt} excerpt-icon">{$excerpt_name}</span> {$comment_text}</div>
							<div class="aec-content-footer"><strong class="aec-author_name">{$author_name}</strong> <span class="aec-content-date">{$comment->post_date}</span>{$delete_button}</div>
						</div>
HTML;
			}
			$content_content .= '</article>';
		}

		$content_content .= '</div></div>';

		return $content_content;
	}

	/**
	 * Get comment text
	 *
	 * @param $post
	 *
	 * @return string|void
	 */
	public function get_comment_text( $post ) {
		switch ( $post->post_excerpt ) {

			case self::TYPE_NAME_REVISION:
				$text = ' <a href="revision.php?revision=' . $post->post_content . '">' . __( 'Content has changed.', $this->textdomain ) . '</a>';
				break;

			case self::TYPE_NAME_STATUS:
				$exp = explode( ',', $post->post_content );
				if ( isset( $exp[1] ) ) {
					$text = $this->get_status_name( $exp[0] ) . ' <span class="raquo">&raquo;</span> <strong>' . __( $this->get_status_name( $exp[1] ), $this->textdomain ) . '</strong>';
				} else {
					$text = $this->get_status_name( $exp[0] );
				}
				break;

			case self::TYPE_NAME_COMMENT:
			default:
				$text = nl2br( htmlspecialchars( $post->post_content, ENT_QUOTES, 'UTF-8' ) );
		}

		return $text;
	}

	/**
	 * Get status name
	 * @since 2.0
	 *
	 * @param $text
	 *
	 * @return mixed|void
	 */
	public function get_status_name( $text ) {
		if ($text == 'trash') {
			$name = __( ucfirst( $text ), $this->textdomain );
		} elseif ($text == 'publish' || $text == 'draft' || $text == 'private') {
			$name = __( ucfirst( $text ) );
		} else {
			$name = __( $text, $this->textdomain );
		}

		return apply_filters( 'aec_status_name', $name);
	}

	/**
	 * Insert comment.
	 */
	public function insert_comment() {
		$post_id = filter_input( INPUT_POST, 'post_id' );
		$comment = filter_input( INPUT_POST, 'comment' );
		if ( ! $post_id || ! $comment ) {
			wp_send_json_error( array( 'message' => __( 'Oops! Failed to get necessary parameter.', $this->textdomain ) ) );
		}

		$user = wp_get_current_user();
		if ( ! $insert_post_id = wp_insert_post( apply_filters( 'aec_insert_post_args', [
			'post_author'  => $user->ID,
			'post_content' => $comment,
			'post_excerpt' => self::TYPE_NAME_COMMENT,
			'post_status'  => 'publish',
			'post_parent'  => $post_id,
			'post_type'    => self::POST_TYPE_NAME,
		] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Insert comment refused.', $this->textdomain ) ) );
		}

		/**
		 * Fires immediately after a comment is registered.
		 *
		 * @param string $post_id
		 * @param WP_User $user
		 * @param int $insert_post_id
		 *
		 * @since 1.0.0
		 *
		 */
		do_action( 'aec_after_insert_comment', $post_id, $user, $insert_post_id );

		wp_send_json_success( array( 'comments' => $this->get_content_html( $post_id ) ) );
	}

	/**
	 * Delete comment.
	 */
	public function delete_comment() {
		$post_id         = filter_input( INPUT_POST, 'post_id' );
		$comment_post_id = filter_input( INPUT_POST, 'comment_id' );
		if ( ! $post_id || ! $comment_post_id ) {
			wp_send_json_error( array( 'message' => __( 'WTH! Failed to get necessary parameter.', $this->textdomain ) ) );
		}

		if ( ! wp_delete_post( $comment_post_id, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete comment.', $this->textdomain ) ) );
		}

		wp_send_json_success( array( 'comments' => $this->get_content_html( $post_id ) ) );
	}

	/**
	 * Refresh comment.
	 * @since 2.0
	 */
	public function refresh_comment() {
		$post_id = filter_input( INPUT_POST, 'post_id' );
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Oops! Failed to get necessary parameter.', $this->textdomain ) ) );
		}

		wp_send_json_success( array( 'comments' => $this->get_content_html( $post_id ) ) );
	}

	/**
	 * Revision transitions
	 * @since 2.0
	 *
	 * @param $post_id
	 * @param $post
	 * @param $updated
	 */
	public function revision_transitions( $post_id, $post, $updated ) {

		if ( 'revision' === $post->post_type && $post->post_parent ) {

			$parent = get_post( $post->post_parent );
			$active_post_types = apply_filters( 'aec_activate_post_types', get_option( self::ADMIN_EDIT_COMMENT_OPTIONS, self::DEFAULT_ACTIVE_POST_TYPE ) );
			if ( ! in_array( $parent->post_type, $active_post_types ) ) {
				return;
			}
			if ( ! $this->is_revision_supported( $parent->post_type ) ) {
				return;
			}

			$user = wp_get_current_user();

			$insert_post_id = wp_insert_post( apply_filters( 'aec_insert_post_revision_args', array(
				'post_author'  => $user->ID,
				'post_content' => $post_id,
				'post_excerpt' => self::TYPE_NAME_REVISION,
				'post_status'  => 'publish',
				'post_parent'  => $post->post_parent,
				'post_type'    => self::POST_TYPE_NAME,
			) ) );
		}

		return;
	}

	/**
	 * Status transitions
	 * @since 2.0
	 *
	 * @param $new_status
	 * @param $old_status
	 * @param $post
	 */
	public function status_transitions( $new_status, $old_status, $post ) {
		$active_post_types = apply_filters( 'aec_activate_post_types', get_option( self::ADMIN_EDIT_COMMENT_OPTIONS, self::DEFAULT_ACTIVE_POST_TYPE ) );
		if ( ! in_array( $post->post_type, $active_post_types ) ) {
			return;
		}

		if ( $new_status != $old_status && $new_status != 'auto-draft' && $old_status != 'auto-draft') {

			$user = wp_get_current_user();

			$insert_post_id = wp_insert_post( apply_filters( 'aec_insert_post_status_args', array(
				'post_author'  => $user->ID,
				'post_content' => $old_status . ',' . $new_status,
				'post_excerpt' => self::TYPE_NAME_STATUS,
				'post_status'  => 'publish',
				'post_parent'  => $post->ID,
				'post_type'    => self::POST_TYPE_NAME,
			) ) );
		}

		return;
	}

	/**
	 * Filter & override setting. @since 2.0
	 */
	public function filter_setting() {
		$this->active_post_types = apply_filters( 'aec_activate_post_types', get_option( self::ADMIN_EDIT_COMMENT_OPTIONS, self::DEFAULT_ACTIVE_POST_TYPE ) );
	}

	/**
	 * Add columns. @since 2.0
	 */
	public function add_columns() {
		$screen = get_current_screen();
		if ( is_array( $this->active_post_types ) && in_array( $screen->post_type, $this->active_post_types ) ) {
			$post_type_prefix = ( 'post' === $screen->post_type ) ? '' : '_' . $screen->post_type;
			add_filter( 'manage' . $post_type_prefix . '_posts_columns', array( $this, 'manage_posts_columns' ), 99 );
			add_filter( 'manage' . $post_type_prefix . '_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 99, 2 );
		}
	}

	/**
	 * Update AEC columns
	 * @param  post_id $post_id
	 * @return void
	 */
	function save_inline_edit_meta( $post_id ) {
		global $pagenow;
		if ( 'admin-ajax.php' === $pagenow && 'inline-save' === $_POST['action'] && 'list' === $_POST['post_view'] ) {
			add_filter( 'manage_posts_columns', array( $this, 'manage_posts_columns' ), 99 );
			add_filter( 'manage_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 99, 2 );
		}
	}

	/**
	 * Load css for columns. @since 2.0
	 *
	 * @var $hook_suffix
	 */
	public function css_for_columns( $hook_suffix ){
		$screen = get_current_screen();
		if ( 'edit.php' === $hook_suffix && is_array( $this->active_post_types ) && in_array( $screen->post_type, $this->active_post_types ) ) {
			wp_enqueue_style( 'aec', plugin_dir_url( __FILE__ ) . 'assets/css/aec.css', array(), $this->version );
		}
	}

	/**
	 * Prepare aec column. @since 2.0
	 *
	 * @var $columns
	 */
	public function manage_posts_columns( $columns ) {
		$columns[ self::PLUGIN_ABBR ] = __( self::COLUMN_NAME, $this->textdomain );
		return $columns;
	}

	/**
	 * Show html for aec column. @since 2.0
	 *
	 * @var string $column
	 * @var int $post_id
	 */
	public function manage_posts_custom_column( $column, $post_id ) {
		if ( self::PLUGIN_ABBR === $column ){
			add_filter( 'aec_get_post_args', array( $this, 'filter_comment_num_for_column' ), 10 );
			echo $this->get_content_html( $post_id, 'column' );
		}
	}

	/**
	 * Filter a number of comments for aec column. @since 2.0
	 *
	 * @var array $args
	 * @return array
	 */
	function filter_comment_num_for_column( $args ){
		$args['posts_per_page'] = apply_filters( 'aec_posts_per_page_for_column', self::POSTS_PER_PAGE_FOR_COLUMN );
		return $args;
	}

	/**
	 * Determines whether the posttype is support the revision. 
	 * @param  post_type $post_type
	 * @return boolean
	 */
	public function is_revision_supported( $post_type ) {
		return post_type_supports( $post_type, 'revisions' );
	}
}

Admin_Edit_Comment::instance();

/**
 * Uninstalls Admin Edit Comment.
 */
function aec_uninstall() {
	if ( is_multisite() ) {
		$sites = get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			delete_option( Admin_Edit_Comment::ADMIN_EDIT_COMMENT_OPTIONS );
			restore_current_blog();
		}
	} else {
		delete_option( Admin_Edit_Comment::ADMIN_EDIT_COMMENT_OPTIONS );
	}
}
