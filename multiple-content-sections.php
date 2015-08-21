<?php
/*
Plugin Name: Multiple Content Sections
Plugin URI: http://linchpin.agency
Description: Add multiple content sections on a post by post basis.
Version: 1.1
Author: Linchpin
Author URI: http://linchpin.agency
License: GPLv2 or later
*/

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
exit;
}

define( 'LINCHPIN_MCS_VERSION', '1.2.0' );
define( 'LINCHPIN_MCS_PLUGIN_NAME', 'Multiple Content Sections' );
define( 'LINCHPIN_MCS__MINIMUM_WP_VERSION', '4.0' );
define( 'LINCHPIN_MCS___PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LINCHPIN_MCS___PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Class Multiple_Content_Sections
 */
class Multiple_Content_Sections {

	/**
	 * Store available templates.
	 *
	 * @var array
	 */
	public $templates = array();

	/**
	 * __construct function.
	 *
	 * @access public
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		add_action( 'edit_page_form', array( $this, 'edit_page_form' ) );

		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		add_filter( 'content_edit_pre', array( $this, 'the_content' ) );
		add_filter( 'the_content', array( $this, 'the_content' ), 5 );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ) );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			include_once( 'functions-ajax.php' );
		}
	}

	/**
	 * Init function.
	 *
	 * @access public
	 * @return void
	 */
	function init() {
		register_post_type( 'mcs_section', array(
			'public' => false,
			'hierarchical' => true,
			'supports' => array( 'title','editor','author','thumbnail','excerpt' ),
			'capability_type' => 'post',
			'has_archive' => false,
			'show_in_menus' => false,
			'show_in_nav_menus' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'show_ui' => false,
			'rewrite' => null,
		) );
	}

	/**
	 * edit_form_advanced function.
	 *
	 * @access public
	 * @param mixed $post
	 * @return void
	 */
	function edit_page_form( $post ) {
		$content_sections = mcs_get_sections( $post->ID );
		?>
		<hr />
		<div id="mcs-container">
			<?php wp_nonce_field( 'mcs_content_sections_nonce', 'mcs_content_sections_nonce' ); ?>
			<h2>Multiple Content Sections</h2>
			<div id="description" class="description notice notice-info is-dismissible below-h2">
				<p>Multiple content sections allows you to easily segment your page's contents into different blocks of markup.</p>
				<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
			</div>

			<p class="mcs-section-controls-container">
				<span class="mcs-left">
					<a href="#" class="button mcs-section-reorder"><?php esc_html_e( 'Reorder', 'lincpin-mcs' ); ?></a>
					<span class="spinner mcs-reorder-spinner"></span>
					<a href="#" class="button mcs-section-expand"><?php esc_html_e( 'Expand All', 'lincpin-mcs' ); ?></a>
				</span>

				<span class="mcs-right">
					<span class="spinner mcs-add-spinner"></span>
					<a href="#" class="button mcs-section-add"><?php esc_html_e( 'Add Section', 'lincpin-mcs' ); ?></a>
				</span>
			</p>

			<div id="multiple-content-sections-container">
				<?php foreach ( $content_sections as $key => $section ) : ?>
					<?php mcs_add_section_admin_markup( $section, true ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * save_post function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @return void
	 */
	function save_post( $post_id, $post ) {
		//Skip revisions and autosaves
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		//Users should have the ability to edit listings.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['mcs_content_sections_nonce'] ) || ! wp_verify_nonce( $_POST['mcs_content_sections_nonce'], 'mcs_content_sections_nonce' )  ) {
			return;
		}

		if ( empty( $_POST['mcs-sections'] ) ) {
			return;
		}

		$current_sections = mcs_get_sections( $post_id );
		$current_section_ids = wp_list_pluck( $current_sections, 'ID' );

		foreach ( $_POST['mcs-sections'] as $section_id => $section_data ) {
			$section = get_post( (int) $section_id );

			if ( 'mcs_section' !== $section->post_type ) {
				continue;
			}

			if ( $post_id !== $section->post_parent ) {
				continue;
			}

			$status = $section_data['post_status'];
			if ( ! in_array( $status, array( 'publish', 'draft' ) ) ) {
				$status = 'draft';
			}

			$updates = array(
				'ID' => (int) $section_id,
				'post_title' => sanitize_text_field( $section_data['post_title'] ),
				'post_content' => wp_kses( $section_data['post_content'], array_merge(
					array(
						'iframe' => array( 'src' => true, 'style' => true, 'id' => true, 'class' => true )
					),
					wp_kses_allowed_html( 'post' )
				) ),
				'post_status' => $status,
			);

			wp_update_post( $updates );

			$template = sanitize_text_field( $section_data['template'] );

			if ( empty( $template ) ) {
				delete_post_meta( $section->ID, '_mcs_template' );
			} else {
				update_post_meta( $section->ID, '_mcs_template', $template );
			}
		}

		//Save a page's content sections as post content for searchability
		$section_query = mcs_get_sections( $post_id, 'query' );

		if ( $section_query->have_posts() ) {

			$section_content[] = '<div id="mcs-section-content">';

			foreach ( $section_query->posts as $p ) {
				if ( 'publish' !== $p->post_status ) {
					continue;
				}

				$section_content[] = strip_tags( $p->post_title );
				$section_content[] = strip_tags( $p->post_content );
			}

			$section_content[] = '</div>';

			remove_action( 'save_post', array( $this, 'save_post' ), 10, 2 ); // @todo: Needs review I think remove action only calls for 3 items

			wp_update_post( array(
				'ID' => $post_id,
				'post_content' => $post->post_content . implode( ' ' , $section_content ),
			) );

			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		}
	}

	function the_content( $content ) {
		$pos = strpos( $content, '<div id="mcs-section-content">' );
		if ( false !== $pos ) {
			$content = substr( $content, 0, ( strlen( $content ) - $pos ) * -1 );
		}

		return $content;
	}

	/**
	 * admin_enqueue_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	function admin_enqueue_scripts() {
		global $current_screen, $post;
		if ( 'post' != $current_screen->base ) {
			return;
		}

		wp_enqueue_script( 'admin-mcs', plugins_url( 'assets/js/admin-mcs.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ), '1.0', true );

		wp_localize_script( 'admin-mcs', 'mcs_data', array(
			'post_id' => $post->ID,
			'site_uri' => site_url(),
			'choose_layout_nonce' => wp_create_nonce( 'mcs_choose_layout_nonce' ),
			'remove_section_nonce' => wp_create_nonce( 'mcs_remove_section_nonce' ),
			'add_section_nonce' => wp_create_nonce( 'mcs_add_section_nonce' ),
			'reorder_section_nonce' => wp_create_nonce( 'mcs_reorder_section_nonce' ),
			'featured_image_nonce' => wp_create_nonce( 'mcs_featured_image_nonce' ),
		) );
	}

	/**
	 * admin_enqueue_styles function.
	 *
	 * @access public
	 * @return void
	 */
	function admin_enqueue_styles() {
		wp_enqueue_style( 'admin-mcs', plugins_url( 'assets/css/admin-mcs.css', __FILE__ ), array(), '1.0' );
	}
}
$multiple_content_sections = new Multiple_Content_Sections();

/**
 * Load a list of template files for .
 *
 * @access public
 * @param string $section_templates (default: '')
 * @return void
 */
function mcs_locate_template_files( $section_templates = '' ) {
	$current_theme = wp_get_theme();

	if ( ! is_array( $section_templates ) ) {
		$section_templates = array();

		$files = (array) $current_theme->get_files( 'php', 1 );

		foreach ( $files as $file => $full_path ) {
			if ( ! preg_match( '|MCS Template:(.*)$|mi', file_get_contents( $full_path ), $header ) ) {
				continue;
			}

			$section_templates[ $file ] = _cleanup_header_comment( $header[1] );
		}
	}

	/**
	 * Filter list of page templates for a theme.
	 *
	 * This filter does not currently allow for page templates to be added.
	 *
	 * @since 3.9.0
	 *
	 * @param array        $page_templates Array of page templates. Keys are filenames,
	 *                                     values are translated names.
	 * @param WP_Theme     $this           The theme object.
	 * @param WP_Post|null $post           The post being edited, provided for context, or null.
	 */
	$return = apply_filters( 'mcs_section_templates', $section_templates );

	return array_intersect_assoc( $return, $section_templates );
}

/**
 * Return admin facing markup for a section.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function mcs_add_section_admin_markup( $section, $closed = false ) {
	if ( ! is_admin() ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $section->ID ) ) {
		return;
	}

	$templates = mcs_locate_template_files();
	$selected = get_post_meta( $section->ID, '_mcs_template', true );

	$featured_image_id = get_post_thumbnail_id( $section->ID );

	include LINCHPIN_MCS___PLUGIN_DIR . '/admin/section-container.php';
}

/**
* @param $post_id
* @param string $return_type
 *
*@return array|WP_Query
 */
function mcs_get_sections( $post_id, $return_type = 'array' ) {
	$content_sections = new WP_Query( array(
		'post_type' => 'mcs_section',
		'posts_per_page' => 50,
		'orderby' => 'menu_order',
		'order' => 'ASC',
		'post_parent' => (int) $post_id,
	) );

	switch ( $return_type ) {
		case 'query' :
			return $content_sections;
			break;

		case 'array' :
		default      :
			return $content_sections->posts;
			break;
	}
}

/**
 * Load a specified template file for a section
 *
 * @access public
 * @param string $post_id (default: '')
 * @return void
 */
function the_mcs_content( $post_id = '' ) {
	global $post;

	if ( empty( $post_id ) ) {
		$post_id = $post->ID;
	}

	if ( 'mcs_section' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( ! $template = get_post_meta( $post_id, '_mcs_template', true ) ) {
		$template = 'mcs-default.php';
	}

	$located = locate_template( sanitize_text_field( $template ), true, false );

	if ( $located ) {
		return;
	}

	?>
	<div <?php post_class(); ?>
		<h3 title="<?php the_title_attribute(); ?>"><?php the_title(); ?></h3>
		<div class="entry">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
}

/**
 * mcs_display_sections function.
 *
 * @access public
 * @param string $post_id (default: '')
 * @return void
 */
function mcs_display_sections( $post_id = '' ) {
	global $post, $mcs_section_query;

	if ( empty( $post_id ) ) {
		$post_id = $post->ID;
	}

	if ( ! $mcs_section_query = mcs_get_sections( $post_id, 'query' ) ) {
		return;
	}

	if ( ! empty( $mcs_section_query ) ) {
		if ( $mcs_section_query->have_posts() ) : while ( $mcs_section_query->have_posts() ) : $mcs_section_query->the_post();
			the_mcs_content();
		endwhile; endif; wp_reset_postdata();
	}
}