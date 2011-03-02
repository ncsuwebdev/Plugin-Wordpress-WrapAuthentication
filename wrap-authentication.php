<?php
/*
Credit goes to Daniel Westermann-Clark (http://dev.webadmin.ufl.edu/~dwc/) for
writing the initial plugin that this plugin was based off of. 

Plugin Name: WRAP Authentication
Version: 1.0
Plugin URI: http://webapps.ncsu.edu
Description: Authenticate users using WRAP authentication.
Author: Outreach Technology
Author URI: http://webapps.ncsu.edu
*/
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'options-page.php');

class WRAPAuthenticationPlugin {
	function WRAPAuthenticationPlugin() {
		
        register_activation_hook(__FILE__, array(&$this, 'initialize_options'));
		add_action('init', array(&$this, 'migrate_options'));

		$options_page = new WRAPAuthenticationOptionsPage(&$this, 'wrap_authentication_options', __FILE__);

		add_filter('login_url', array(&$this, 'bypass_reauth'));
		add_filter('show_password_fields', array(&$this, 'disable'));
		add_filter('allow_password_reset', array(&$this, 'disable'));
		add_action('check_passwords', array(&$this, 'generate_password'), 10, 3);
		add_action('wp_logout', array(&$this, 'logout'));		
	}


	/*************************************************************
	 * Plugin hooks
	 *************************************************************/

	/*
	 * Add the default options to the database.
	 */
	function initialize_options() {
		$options = array(
			'auto_create_user'         => false,
		);

		// Copy old options
		foreach (array_keys($options) as $key) {
			$old_value = get_site_option("wrap_authentication_$key");
			if ($old_value !== false) {
				$options[$key] = $old_value;
			}

			delete_site_option("wrap_authentication_$key");
		}

		update_site_option('wrap_authentication__options', $options);
	}	
	
	/*
	 * Migrate options for in-place upgrades of the plugin.
	 */
	function migrate_options() {
		// Check if we've migrated options already
		$options = get_site_option('wrap_authentication_options');
		if ($options !== false) return;

		$this->initialize_options();
	}

	/*
	 * Remove the reauth=1 parameter from the login URL, if applicable. This allows
	 * us to transparently bypass the mucking about with cookies that happens in
	 * wp-login.php immediately after wp_signon when a user e.g. navigates directly
	 * to wp-admin.
	 */
	function bypass_reauth($login_url) {
		$login_url = remove_query_arg('reauth', $login_url);

		return $login_url;
	}

	/*
	 * Used to disable certain display elements, e.g. password
	 * fields on profile screen, or functions, e.g. password reset.
	 */
	function disable($flag) {
		return false;
	}

	/*
	 * Generate a password for the user. This plugin does not require the
	 * administrator to enter this value, but we need to set it so that user
	 * creation and editing works.
	 */
	function generate_password($username, $password1, $password2) {
		$password1 = $password2 = wp_generate_password();
	}

	/*
	 * Logout the user by redirecting them to the logout URI.
	 */
	function logout() {
	    foreach (array_keys($_COOKIE) as $name) {
            if (preg_match('/^WRAP.*/',$name)) {

                // set the expiration date to one hour ago
                setcookie($name, "", time() - 3600, "/", "ncsu.edu");
            }
        }
        
        wp_clearcookie();
        nocache_headers();

        header('Location:' . get_option('siteurl'));
        exit();	    
	}

	/*************************************************************
	 * Functions
	 *************************************************************/

	/*
	 * Get the value of the specified plugin-specific option.
	 */
	function get_plugin_option($option) {
		$options = get_site_option('wrap_authentication_options');

		return $options[$option];
	}

	/*
	 * If the REMOTE_USER or REDIRECT_REMOTE_USER evironment
	 * variable is set, use it as the username. This assumes that
	 * you have externally authenticated the user.
	 */
	function check_remote_user() {

	    $username = (getenv('WRAP_USERID') == '') ? getenv('REDIRECT_WRAP_USERID') : getenv('WRAP_USERID');

        if ($username == '') {
            $username = getenv('REDIRECT_WRAP_USERID');
        }
        /*
        echo "<pre>";
        print_r($_ENV);
        echo $username;
        die();
        */
        if ($username == '') {
            setrawcookie('WRAP_REFERER', $this->_getUrl(), 0, '/', '.ncsu.edu');
            header('location:https://webauth.ncsu.edu/wrap-bin/was16.cgi');
            die();
        }

		// Create new users automatically, if configured
		$user = get_userdatabylogin($username);
		if (! $user) {
			if ((bool) $this->get_plugin_option('auto_create_user')) {
				$user = $this->_create_user($username);
			}
			else {
				// Bail out to avoid showing the login form
				die("User $username does not exist in the WordPress database");
			}
		}

		return $user;
	}

	/*
	 * Create a new WordPress account for the specified username.
	 */
	function _create_user($username) {
		$password = wp_generate_password();
		$email = $username . '@ncsu.edu';

		require_once(WPINC . DIRECTORY_SEPARATOR . 'registration.php');
		$user_id = wp_create_user($username, $password, $email);
		$user = get_user_by('id', $user_id);
		
		return $user;
	}
	
    /**
     * Gets the current URL
     *
     * @return string
     */
    function _getURL()
    {
        $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";

        $protocol = substr(strtolower($_SERVER["SERVER_PROTOCOL"]), 0, strpos(strtolower($_SERVER["SERVER_PROTOCOL"]), "/")) . $s;

        $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);

        return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
    }	
}

// Load the plugin hooks, etc.
$wrap_authentication_plugin = new WRAPAuthenticationPlugin();

// Override pluggable function to avoid ordering problem with 'authenticate' filter
if (! function_exists('wp_authenticate')) {
	function wp_authenticate($username, $password) {
		global $wrap_authentication_plugin;

		$user = $wrap_authentication_plugin->check_remote_user();
		if (! is_wp_error($user)) {
			$user = new WP_User($user->ID);
		}

		return $user;
	}
}	
