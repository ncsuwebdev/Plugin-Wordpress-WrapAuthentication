<?php
class WRAPAuthenticationOptionsPage {
	var $plugin;
	var $group;
	var $page;
	var $title;

	function WRAPAuthenticationOptionsPage($plugin, $group, $page, $title = 'WRAP Authentication') {
		$this->plugin = $plugin;
		$this->group = $group;
		$this->page = $page;
		$this->title = $title;

		add_action('admin_init', array(&$this, 'register_options'));
		add_action('admin_menu', array(&$this, 'add_options_page'));
	}

	/*
	 * Register the options for this plugin so they can be displayed and updated below.
	 */
	function register_options() {
		register_setting($this->group, $this->group);

		$section = 'wrap_authentication_main';
		add_settings_section($section, 'Main Options', array(&$this, '_display_options_section'), $this->page);
		add_settings_field('wrap_authentication_auto_create_user', 'Automatically create accounts?', array(&$this, '_display_option_auto_create_user'), $this->page, $section);
	}

	/*
	 * Add an options page for this plugin.
	 */
	function add_options_page() {
		if (function_exists('is_site_admin') && is_site_admin()) {
			add_submenu_page('wpmu-admin.php', $this->title, $this->title, 'manage_options', $this->page, array(&$this, '_display_options_page'));
			add_options_page($this->title, $this->title, 'manage_options', $this->page, array(&$this, '_display_options_page'));
		}
		else {
			add_options_page($this->title, $this->title, 'manage_options', $this->page, array(&$this, '_display_options_page'));
		}
	}

	/*
	 * Display the options for this plugin.
	 */
	function _display_options_page() {
?>
<div class="wrap">
  <h2>WRAP Authentication Options</h2>
  <form action="options.php" method="post">
    <?php settings_fields($this->group); ?>
    <?php do_settings_sections($this->page); ?>
    <p class="submit">
      <input type="submit" name="Submit" value="<?php esc_attr_e('Save Changes'); ?>" class="button-primary" />
    </p>
  </form>
</div>
<?php
	}

	/*
	 * Display explanatory text for the main options section.
	 */
	function _display_options_section() {
	}

	/*
	 * Display the automatically create accounts checkbox.
	 */
	function _display_option_auto_create_user() {
		$auto_create_user = $this->plugin->get_plugin_option('auto_create_user');
?>
<input type="checkbox" name="<?php echo htmlspecialchars($this->group); ?>[auto_create_user]" id="wrap_authentication_auto_create_user"<?php if ($auto_create_user) echo ' checked="checked"' ?> value="1" /><br />
Should a new user be created automatically if not already in the WordPress database?<br />
Created users will obtain the role defined under &quot;New User Default Role&quot; on the <a href="options-general.php">General Options</a> page.
<?php
	}
}
?>
