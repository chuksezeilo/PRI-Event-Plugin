<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Programs" post type and the "Program Types" taxonomy.
 *
 * A custom post type means programs get the normal WordPress editor,
 * featured image, revisions and permissions for free, with no custom tables.
 */
class PRI_PM_Post_Types {

	const POST_TYPE = 'pri_program';
	const TAXONOMY  = 'pri_program_type';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_post_type( self::POST_TYPE, array(
			'labels'       => array(
				'name'               => __( 'Programs', 'pri-programs-manager' ),
				'singular_name'      => __( 'Program', 'pri-programs-manager' ),
				'add_new'            => __( 'Add New', 'pri-programs-manager' ),
				'add_new_item'       => __( 'Add New Program', 'pri-programs-manager' ),
				'edit_item'          => __( 'Edit Program', 'pri-programs-manager' ),
				'new_item'           => __( 'New Program', 'pri-programs-manager' ),
				'view_item'          => __( 'View Program', 'pri-programs-manager' ),
				'search_items'       => __( 'Search Programs', 'pri-programs-manager' ),
				'not_found'          => __( 'No programs found.', 'pri-programs-manager' ),
				'not_found_in_trash' => __( 'No programs found in Trash.', 'pri-programs-manager' ),
				'all_items'          => __( 'All Programs', 'pri-programs-manager' ),
				'menu_name'          => __( 'Programs', 'pri-programs-manager' ),
			),
			'public'       => true,
			'has_archive'  => false, // Listing is done by shortcode (v0.3), not an archive page.
			'menu_icon'    => 'dashicons-calendar-alt',
			'menu_position'=> 21,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'rewrite'      => array( 'slug' => 'programs' ),
			'show_in_rest' => true, // Block editor support.
		) );

		register_taxonomy( self::TAXONOMY, self::POST_TYPE, array(
			'labels'            => array(
				'name'          => __( 'Program Types', 'pri-programs-manager' ),
				'singular_name' => __( 'Program Type', 'pri-programs-manager' ),
				'add_new_item'  => __( 'Add New Program Type', 'pri-programs-manager' ),
				'all_items'     => __( 'All Program Types', 'pri-programs-manager' ),
			),
			'hierarchical'      => true, // Behaves like categories (tick boxes), simplest for editors.
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'program-type' ),
		) );
	}
}
