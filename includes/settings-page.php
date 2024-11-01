<?php 
/**
 * Drop-in settings page
 * It createes an options page under "settings" in the wp-admin.
 * Add the options you need for your plugin to the parent static $options variable.
 * @link http://ottopress.com/2009/wordpress-settings-api-tutorial/
 * @link https://codex.wordpress.org/Settings_API
 */



// Options_Page::init();



class Settings_Page
{
	static $instance = NULL;

	public $saved_options = array();

	public $page = array();

	public $sections = array();

	private $looping_section_id, $looping_field_id;



	public function add_page($page_title = 'Settings', $page_basename = NULL, $page_path = 'options-general.php', $page_description = '')
	{
		if ( empty($page_basename) )
		{
			$page_basename = sanitize_title($page_title);
		}

		$this->page = array(
			'basename' => $page_basename,
			'title' => $page_title,
			'description' => $page_description,
			'path' => $page_path
		);

		return $this;
	}



	public function render()
	{
		add_action( 'admin_menu', array($this, 'add_admin_menu') );
		add_action( 'admin_init', array($this, 'add_settings_section') );

		// // add settings link
		// add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'add_plugin_settings_link'));
	}



	public function add_fieldset($id = 'main', $title = 'main', $description = '')
	{
		if ( !isset($this->sections[$id]) )
		{
			$this->sections[$id] = array(
				'title' => '',
				'description' => '',
				'fields' => array()
			);
		}

		$this->sections[$id]['title'] = $title;
		$this->sections[$id]['description'] = $description;

		return $this;
	}



	public function add_field($type, $section_title, $id, $title, $value)
	{
		if ( !isset($this->sections[$section_title]) )
		{
			$this->add_fieldset($section_title);
		}

		$this->sections[$section_title]['fields'][$id] = array(
			'type'=> $type,
			'id' => $id,
			'title' => $title,
			'value' => $value
		);

		return $this;
	}



	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}



	public function add_admin_menu()
	{
		add_submenu_page(
			$this->page['path'], // The ID of the top-level menu page to which this submenu item belongs
			$this->page['title'], // The value used to populate the browser's title bar when the menu page is active
			$this->page['title'], // The label of this submenu item displayed in the menu
			'manage_options', // What roles are able to access this submenu item
			$this->page['basename'], // The ID used to represent this submenu item
			array($this, 'menu_page_html') // The callback function used to render the options for this submenu item
		);
	}



	public function menu_page_html()
	{
		?>
		<div class="wrap">
			<h1><?php echo $this->page['title'] ?></h1>

			<?php // settings_errors(); ?>

			<?php if ( !empty($this->page['description']) ) : ?>
			<?php echo $this->page['description'] ?>
			<?php endif // description ?>

			<form method="post" action="options.php">
				<?php
				settings_fields($this->page['basename']);
				do_settings_sections($this->page['basename']);
				submit_button();
				?>
			</form>
		</div>
		<?php
	}



	public function add_settings_section()
	{
		register_setting(
			$this->page['basename'], // option_group
			$this->page['basename'], // option_name
			array($this, 'validate_form_values')
		);

		foreach ($this->sections as $section_id => $section_data)
		{
			$section_data = (object) $section_data;

			$this->looping_section_id = $section_id;

			add_settings_section(
				$section_id, // id
				$section_data->title, // title
				array($this, 'render_section'),
				$this->page['basename'] // page
			);



			foreach ( $section_data->fields as $field_id => $field_data)
			{
				$field_data = (object) $field_data;

				$this->looping_field_id = $field_id;

				$field_data->section_id = $section_id;
				$field_data->id = $field_id;

			 	add_settings_field(
					$field_id,
					$field_data->title,
					array($this, sprintf('render_%s', $field_data->type)),
					$this->page['basename'], // pagename
					$section_id, // id of section to add it to
					(array) $field_data
				);
			} // foreach
		}
	}



	public function render_section()
	{
		$description = $this->sections[$this->looping_section_id]['description'];

	 	echo $description; // <p>Instructions go here</p>
	}



	public function render_checkbox($params)
	{
		$options = $this->get_options();
		extract($params);

		$checked = checked( $options[$section_id][$id], 1, FALSE );

		echo sprintf('<input name="%s[%s][%s]" id="%s" type="checkbox" value="1" %s>',
	 		$this->page['basename'], $section_id, $id, $id, $value, $checked
	 	);
	}



	public function render_text($params)
	{
		$options = $this->get_options();
		extract($params);

		if ( !empty($options[$section_id][$id]) )
		{
			$value = $options[$section_id][$id];
		}

		if ( is_array($value) )
		{
			$value = implode(', ', $value);
		}

		echo sprintf('<input name="%s[%s][%s]" id="%s" type="text" value="%s">',
	 		$this->page['basename'], $section_id, $id, $id, $value
	 	);
	}



	public function render_checkboxgroup($params)
	{
		$options = $this->get_options();
		extract($params);

		if ( !is_array($options[$section_id][$id]) )
		{
			$options[$section_id][$id] = array();
		}

		foreach ($value as $checkbox_id => $checkbox_label)
		{
			$checked = checked( in_array($checkbox_id, $options[$section_id][$id]), 1, FALSE );
			echo sprintf('<label><input name="%s[%s][%s][]" id="%s-%s" type="checkbox" value="%s" %s> %s</label><br>',
				$this->page['basename'], $section_id, $id, $id, $checkbox_id, $checkbox_id, $checked, $checkbox_label
			);
		}
	}



	public function validate_form_values($input)
	{
		foreach ($input as $key => $value_arr)
		{
			foreach ($value_arr as $value_key => $value)
			{
				$field = $this->sections[$key]['fields'][$value_key];

				if ( empty($field) )
				{
					unset($input[$key][$value_key]);
					continue;
				}

				switch ($field['type'])
				{
					case 'text':
						$input[$key][$value_key] = sanitize_text_field($value);
						break;
					case 'email':
						$input[$key][$value_key] = sanitize_email($value);
						break;
					default:
				}
			}
		}

		return $input;
	}



	public function get_options($flush = FALSE)
	{
		if ( empty($this->saved_options) || $flush )
		{
			$this->saved_options = get_option($this->page['basename']);
		}

		if ( empty($this->saved_options) )
		{
			$this->saved_options = array();
		}

		return $this->saved_options;
	}



	static function add_plugin_settings_link($links)
	{
		$settings_link = sprintf('<a href="%s?page=%s">Settings</a>', self::get_instance()->page['path'], self::get_instance()->page['basename']);
		array_unshift($links, $settings_link);
		return $links;
	}



} // Fileshare_Admin





