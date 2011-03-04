<?php
/***************************************************************************
 * Plugin Name: NC State WRAP Authentication
 * Version: 2.0.0
 * Plugin URI: http://ot.ncsu.edu
 * Description: Authenticate NC State users using their WRAP credentials.
 * Author: OIT Outreach Technology
 * Author URI: http://ot.ncsu.edu
 **************************************************************************/

/**
 * Create the NcstateWrapAuthentication class
 */
class NcstateWrapAuthentication
{
    /**
     * Default values for options
     *
     * @var array
     */
    protected $_defaults = array(
        'autoCreateUser' => true,
    );

    /**
     * Selected options for the plugin
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $options = get_option('ncstate-wrap-authentication');
        $this->_options = (is_array($options)) ? $options : $this->_defaults;

        add_filter('login_url', array($this, 'bypassReauth'));
        add_filter('show_password_fields', array($this, 'disable'));
        add_filter('allow_password_reset', array($this, 'disable'));

        // Register WP hooks
        add_action('check_passwords', array($this, 'generatePassword'), 10, 3);
        add_action('wp_logout', array($this, 'logout'));
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_post_ncstate-wrap-authentication', array($this, 'formSubmit'));
    }

    /**
     * Creates an admin menu item in the settings list
     *
     */
    public function addAdminMenu() {
        add_submenu_page(
            'options-general.php',
            __('NC State WRAP Auth', 'ncstate-wrap-authentication'),
            __('NC State WRAP Auth', 'ncstate-wrap-authentication'),
            'read',
            'ncstate-wrap-authentication',
            array($this, 'settingsPage')
        );
    }

    /**
     * Handles the submission of the form, then redirects back to
     * plugin configuration page.
     *
     */
    public function formSubmit() {

        check_admin_referer('ncstate-wrap-authentication');

        $newOptions = array(
            'autoCreateUser' => (bool)$_POST['nwa_autoCreateUser'],
        );

        update_option('ncstate-wrap-authentication', $newOptions);

        wp_safe_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    }

    /**
     * Displays the form for configuring the bar.
     *
     * @uses form.phtml
     */
    public function settingsPage() {
        require_once 'form.phtml';
    }

    /**
     * Remove the reauth parameter from the login URL if it exists, firing
     * immediately after the wp_signon attempt.
     *
     * @param string $loginUrl URL of the login request
     * @return string Url without the reauth parameter
     */
    public function bypassReauth($loginUrl)
    {
        return remove_query_arg('reauth', $loginUrl);
    }

    /**
     * Used to disable display elements on profile screen, or functions
     *
     * @param string flag to disable
     * @return false
     */
    public function disable($flag)
    {
        return false;
    }

	/**
	 * Generate a password for the user. This plugin does not require the
	 * administrator to enter this value, but we need to set it so that user
	 * creation and editing works.
	 */
	public function generatePassword($username, $password1, $password2)
	{
		$password1 = $password2 = wp_generate_password();
	}

    /**
     * Logout the user by removing their WRAP cookies
     *
     */
    public function logout()
    {
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

    /**
     * Authenticates the user.  If logged in, will pull their user ID from the
     * WRAP cookie.  If not logged in, will redirect the user to the WRAP
     * authenitcation page then direct them back once they have authenticated.
     *
     * @return WPUser object
     */
    public function wrapAuthenticate()
    {
        $username = (getenv('WRAP_USERID') == '') ? getenv('REDIRECT_WRAP_USERID') : getenv('WRAP_USERID');

        if ($username == '') {
            $username = getenv('REDIRECT_WRAP_USERID');
        }

        if ($username == '') {
            setrawcookie('WRAP_REFERER', $this->_getUrl(), 0, '/', '.ncsu.edu');
            header('location:https://webauth.ncsu.edu/wrap-bin/was16.cgi');
            die();
        }

        // Create new users automatically, if configured
        $user = get_userdatabylogin($username);
        if (!$user) {
            if (isset($this->_options['autoCreateUser']) && (bool)$this->_options['autoCreateUser']) {
                $user = $this->_createUser($username);
            }
            else {
                die("User $username does not exist in the WordPress database");
            }
        }

        return $user;
    }

    /**
     * Create a new WordPress account for the logged-in-user
     *
     * @param string $username Username from WRAP
     * @return WPUser object
     */
    protected function _createUser($username)
    {
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
    protected function _getUrl()
    {
        $s = empty($_SERVER['HTTPS']) ? '' : ($_SERVER['HTTPS'] == 'on') ? 's' : '';

        $protocol = substr(strtolower($_SERVER['SERVER_PROTOCOL']), 0, strpos(strtolower($_SERVER['SERVER_PROTOCOL']), '/')) . $s;

        $port = ($_SERVER['SERVER_PORT'] == '80') ? '' : (':'.$_SERVER['SERVER_PORT']);

        return $protocol . '://' . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    }
}

// Start this plugin
$ncstateWrapAuthentication = new NcstateWrapAuthentication();


// Override pluggable function to avoid ordering problem with 'authenticate' filter
if (!function_exists('wp_authenticate')) {

    function wp_authenticate($username, $password) {
        global $ncstateWrapAuthentication;

        $user = $ncstateWrapAuthentication->wrapAuthenticate();
        if (!is_wp_error($user)) {
            $user = new WP_User($user->ID);
        }

        return $user;
    }
}
