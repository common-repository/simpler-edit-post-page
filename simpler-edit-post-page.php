<?
/*
Plugin Name: Simpler Edit Post Page
Plugin URI: https://github.com/gelform/simpler-edit-post-page-wordpress-plugin
Description: Simplify edit post pages - a single column, and replace the publish box with simpler "save" options
Version: 1.1.1
Author: Corey Maass, Gelform
Author URI: http://gelwp.com
Text Domain: simpler-edit-post-page
License: GPLv2 or later

Simpler Edit Post Page is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Simpler Edit Post Page is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

See https://wordpress.org/about/gpl/

*/



defined( 'ABSPATH' ) or die( 'No script kiddies please!' );



Simpler_Edit_Post_Page::init();



class Simpler_Edit_Post_Page
{
	static $basename = 'simpler-edit-post-page';

	static $post_types;
	static $saved_options;

	static function init()
	{
		add_filter('show_admin_bar', '__return_false');
		add_action('init', array(__CLASS__, 'update_cookie'));

		add_action('init', array(__CLASS__, 'set_view'));

		add_action( 'init', array(__CLASS__, 'add_settings_page') );

		add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_default_scripts') );

		add_action( 'wp_before_admin_bar_render', array(__CLASS__, 'add_switch_view_button') );

		// add settings link
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array('Settings_Page', 'add_plugin_settings_link'));
	}



	static function add_settings_page()
	{
		require_once __DIR__ . '/includes/settings-page.php';

		// get post types for settings page
		$args = array(
		   'public'   => true,
		);

		$post_types_obj = get_post_types( '', 'objects' );

		self::$post_types = array();
		foreach ($post_types_obj as $id => $data)
		{
			self::$post_types[$id] = $data->labels->name;
		}

		$settings_page = Settings_Page::get_instance(); // new Settings_Page('Simpler Admin Settings');

		$settings_page
		->add_page('Simpler Post')
		->add_fieldset(
			'admin_bar',
			__('Admin Bar', 'simpler-edit-post-page'),
			__('What post types do you want access to in the admin bar?', 'simpler-edit-post-page')
			)
			->add_field(
				'checkboxgroup',
				'admin_bar',
				'see_all_post_types',
				__('Post types to include in "See all"', 'simpler-edit-post-page'),
				self::$post_types
			)
			->add_field(
				'checkboxgroup',
				'admin_bar',
				'add_new_post_types',
				__('Post types to include in "Add new"', 'simpler-edit-post-page'),
				self::$post_types
			)
		->add_fieldset(
				'post_edit_page',
				__('Post Edit Pages', 'simpler-edit-post-page'),
				__('Post and Page edit pages will be converted to a simpler, single page layout', 'simpler-edit-post-page')
			)
			->add_field(
				'checkboxgroup',
				'post_edit_page',
				'post_types',
				__('Post types to simplify', 'simpler-edit-post-page'),
				self::$post_types
			)
		->render();
	}



	static function is_enabled()
	{
		return isset($_COOKIE[self::$basename]);
	}



	static function enqueue_default_scripts()
	{
		wp_enqueue_style(
			sprintf('%s', Simpler_Edit_Post_Page::$basename),
			plugin_dir_url(__FILE__) . 'css/admin.css',
			array(),
			'1.0.1',
			'all'
		);
	}



	static function enqueue_simpler_scripts()
	{
		wp_enqueue_style(
			sprintf('%s-enabled', Simpler_Edit_Post_Page::$basename),
			plugin_dir_url(__FILE__) . 'css/enabled-admin.css',
			array(),
			'1.0.1',
			'all'
		);

		wp_enqueue_script(
			sprintf('%s-enabled', Simpler_Edit_Post_Page::$basename),
			plugin_dir_url(__FILE__) . 'js/enabled-admin.js',
			array('jquery'),
			'1.0.1',
			'all'
		);
	}



	static function update_cookie()
	{
		if ( !isset($_GET[self::$basename])) return;

		if ( $_GET[self::$basename] == 'switch-to-admin-view' )
		{
			setcookie(self::$basename, null, -1, '/');
			unset($_COOKIE[self::$basename]);
		}

		if ( $_GET[self::$basename] == 'switch-to-simpler-view' )
		{
			setcookie("simpler-edit-post-page", 1, time()+3600*365, '/');
		}

		$url = remove_query_arg(array(self::$basename));
		wp_safe_redirect($url);
		exit;
	}



	static function add_switch_view_button()
	{
		global $wp_admin_bar;

		if ( self::is_enabled() )
		{
			$switch_url = add_query_arg(array(self::$basename => 'switch-to-admin-view'));
			// $switch_title = '<span>Admin view</span>';
			$class = 'is_simple';
		}
		else
		{
			$switch_url = add_query_arg(array(self::$basename => 'switch-to-simpler-view'));
			// $switch_title = '<span>Simpler view</span>';
			$class = 'is_admin';
		}


		$args_switch_view = array(
			'id'    => self::$basename,
			'title' => sprintf('<span class="simpler_toggle %s"><span class="simpler_toggle_admin"><b>%s</b></span><span class="simpler_toggle_simple"><b>%s</b></span></span>',
				$class,
				__('Admin view', 'simpler-edit-post-page'),
				__('Simpler view', 'simpler-edit-post-page')
			),
			'href'  => $switch_url,
			// 'meta'  => array( 'class' => 'my-toolbar-page' )
		);

		$wp_admin_bar->add_node( $args_switch_view );
	}



	static function set_view()
	{
		if ( !self::is_enabled() || !is_admin() ) return;

		add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_simpler_scripts') );
		add_filter( 'contextual_help', array(__CLASS__, 'customcontext_remove_help'), 999, 3 );
		add_action( 'admin_init', array(__CLASS__, 'remove_dashboard_meta') );
		add_action( 'load-index.php', array(__CLASS__, 'hide_welcome_panel') );
		add_action( 'wp_before_admin_bar_render', array(__CLASS__, 'cleanup_admin_top_bar') );

		add_action('load-post.php', array(__CLASS__, 'remove_widgets') );
		add_action('load-post-new.php', array(__CLASS__, 'remove_widgets') );
	}



	// hide help tab
	static function customcontext_remove_help($old_help, $screen_id, $screen)
	{
		$screen->remove_help_tabs();
		return $old_help;
	}



	// clean up dashboard widgets
	// @link http://codex.wordpress.org/Dashboard_Widgets_API#Advanced:_Removing_Dashboard_Widgets
	static function remove_dashboard_meta()
	{
		remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_primary', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_secondary', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
	}



	static function get_post_type()
	{
		// get post type from URL
		if( isset($_GET['post_type']) )
		{
			$post_type = $_GET['post_type'];
		}

		// try to detect it from the page we're on
		if( empty($post_type ) && function_exists('get_current_screen') )
		{
			$screen = get_current_screen();
			$post_type = $screen->post_type;
		}

		// otherwise, assume it's a post
		if( empty($post_type ) )
		{
			$post_type = 'post';
		}

		return $post_type;
	}



	static function so_screen_layout_columns( $columns )
	{
		$post_type = self::get_post_type();

		$columns[$post_type] = 1;
		return $columns;
	}



	static function so_screen_layout_post()
	{
		return 1;
	}



	static function remove_widgets()
	{
		// get the current post type
		$post_type = self::get_post_type();

		$settings_page = Settings_Page::get_instance();
		$options = $settings_page->get_options();


		if( !empty($options['post_edit_page']['post_types']) && !in_array($post_type, $options['post_edit_page']['post_types']) ) return false;



		// make it one sreeen
		add_filter( 'screen_layout_columns', array(__CLASS__, 'so_screen_layout_columns') );
		add_filter( 'get_user_option_screen_layout_' . $post_type, array(__CLASS__, 'so_screen_layout_post') );



		// add new save metabox
		// http://wordpress.stackexchange.com/questions/2279/customize-edit-post-screen-for-custom-post-types
		add_meta_box(
			'publish_widget',
			__('Publish', 'simpler-edit-post-page'),
			function() {}, // do nothing
			$post_type,
			'normal',
			'low'
		);


	}



	// hide welcome panel
	// http://wordpress.stackexchange.com/a/36404
	static function hide_welcome_panel()
	{
		$user_id = get_current_user_id();

		if ( 1 == get_user_meta( $user_id, 'show_welcome_panel', true ) )
			update_user_meta( $user_id, 'show_welcome_panel', 0 );
	}



	// hide extras in top bar in admin
	static function cleanup_admin_top_bar()
	{
		global $wp_admin_bar;

		$nodes_to_keep = array(
			self::$basename,
			'site-name',
			'user-info',
			'edit-profile',
			'logout'
		);

		foreach ($wp_admin_bar->get_nodes() as $handle => $data)
		{
			if ( in_array($handle, $nodes_to_keep) ) continue;
			$wp_admin_bar->remove_menu($handle);
		}
		// $wp_admin_bar->remove_menu('wp-logo');
		// $wp_admin_bar->remove_menu('comments');
		// $wp_admin_bar->remove_menu('view');
		// $wp_admin_bar->remove_menu('updates');
		// $wp_admin_bar->remove_menu('appearance');
		// $wp_admin_bar->remove_menu('new-content');
		// $wp_admin_bar->remove_menu('my-account');
		// $wp_admin_bar->remove_menu('my-account-with-avatar');
		// $wp_admin_bar->remove_menu('my-blogs');
		// $wp_admin_bar->remove_menu('get-shortlink');
		// $wp_admin_bar->remove_menu('site-name');



		$wp_admin_bar->add_menu( array(
			'title' => sprintf('<span class="ab-icon"></span><span class="ab-label">%s</span>',
				__('See all...', 'simpler-edit-post-page')
			),
			'href' => false,
			'id' => 'simpler-edit-menu',
			'href' => false
		));

		$wp_admin_bar->add_menu( array(
			'title' => sprintf('<span class="ab-icon"></span><span class="ab-label">%s</span>',
				__('Add a new...', 'simpler-edit-post-page')
			),
			'href' => false,
			'id' => 'simpler-new-menu',
			'href' => false
		));



		$settings_page = Settings_Page::get_instance();
		$options = $settings_page->get_options();


		$add_new_post_types = !empty($options['admin_bar']['add_new_post_types']) ? $options['admin_bar']['add_new_post_types'] : self::$post_types;
		$see_all_post_types = !empty($options['admin_bar']['see_all_post_types']) ? $options['admin_bar']['see_all_post_types'] : self::$post_types;



		foreach ( $add_new_post_types as $post_type )
		{
			$post_type_data = get_post_type_object($post_type);

			$wp_admin_bar->add_menu( array(
				'title' => $post_type_data->labels->singular_name,
				'href' => '/wp-admin/post-new.php?post_type=' . $post_type,
				'parent' => 'simpler-new-menu',
				// 'meta' => array('target' => '_blank')
			));
		}



		foreach ( $see_all_post_types as $post_type )
		{
			$post_type_data = get_post_type_object($post_type);

			$wp_admin_bar->add_menu( array(
				'title' => $post_type_data->labels->name,
				'href' => '/wp-admin/edit.php?post_type=' . $post_type,
				'parent' => 'simpler-edit-menu',
				// 'meta' => array('target' => '_blank')
			));
		}



		$args_logout = array(
			'id'    => 'simpler-logout',
			'title' => sprintf('<span class="ab-label">%s</span><span class="ab-icon"></span>',
				__('Logout', 'simpler-edit-post-page')
			),
			'href'  => wp_logout_url(),
			// 'meta'  => array( 'class' => 'my-toolbar-page' )
		);
		$wp_admin_bar->add_node( $args_logout );



	} // cleanup_admin_top_bar



} // class



