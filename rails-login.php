<?php
/*
Plugin Name: Rails Login
Version: 1.0
Plugin URI: http://wordpress.paulrosen.net/rails_login
Description: Use an existing Ruby on Rails authentication system to be used to determine the current WordPress user.
Author: Paul Rosen
Author URI: http://wordpress.paulrosen.net
*/

/*  Copyright (C) 2015 Paul Rosen
 This plugin is based on a similar, but unmaintained plugin by Robb Shecter.

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA */

require_once 'rails-login-web-service.php';

$API_DEBUG = false;

if ( ! class_exists( 'RailsLoginPlugin' ) ) {
	class RailsLoginPlugin {
		public $api;

		function RailsLoginPlugin() {
			// Add the options to the admin page.
			add_action( 'admin_menu', array( &$this, 'rails_modify_menu' ) );
			add_action( 'admin_init', array( &$this, 'rails_theme_admin_init' ) );

			add_action( 'wp_authenticate', array( &$this, 'authenticate' ), 10, 2 );
			add_filter( 'check_password', array( &$this, 'skip_password_check' ), 10, 4 );
			add_action( 'wp_logout', array( &$this, 'logout' ) );
			add_action( 'lost_password', array( &$this, 'disable_function' ) );
			add_action( 'retrieve_password', array( &$this, 'disable_function' ) );
			add_action( 'password_reset', array( &$this, 'disable_function' ) );
			add_action( 'check_passwords', array( &$this, 'generate_password' ), 10, 3 );
			add_filter( 'show_password_fields', array( &$this, 'disable_password_fields' ) );
		}

/////////////////////////////////////////////////////
// options in the admin section
////////////////////////////////////////////////////

//------------- Add the admin menu -------------
		function rails_admin_options() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}
			echo '<div class="wrap">';
			echo '<h2>Rails Login Options</h2>';
			echo 'Options for the Rails Login Plugin';
			echo '<form action="options.php" method="post">';
			settings_fields( "rails_login_options" );
			do_settings_sections( "rails_login" );
			echo '<input name="Submit" type="submit" value="Save Changes" />';
			echo '</form>';
			$this->rails_login_documentation_text();
			echo '</div>';
		}

		function rails_modify_menu() {
			add_options_page( 'Rails Login', 'Rails Login', 'manage_options', __FILE__, array( &$this, 'rails_admin_options') );
		}

//----------------- Add the settings for this plugin ---------------------
		function rails_theme_admin_init() {
			register_setting( 'rails_login_options', 'rails_login_options', array( &$this, 'rails_login_options_validate') );
			add_settings_section( 'rails_login_main', 'Settings', array( &$this, 'rails_login_section_text'), 'rails_login' );
			add_settings_field( 'rails_login_url', 'Web Service URL', array( &$this, 'rails_login_url_string'), 'rails_login', 'rails_login_main' );
			add_settings_field( 'rails_login_cookie', 'Cookie Name', array( &$this, 'rails_login_cookie_string'), 'rails_login', 'rails_login_main' );
			add_settings_field( 'rails_login_single_login', 'Enable single login?', array( &$this, 'rails_login_single_login_string'), 'rails_login', 'rails_login_main' );
			add_settings_field( 'rails_login_auto_create', 'Automatically create accounts?', array( &$this, 'rails_login_auto_create_string'), 'rails_login', 'rails_login_main' );
		}

		function rails_login_section_text() {
			echo '<p>Set the connection to the rails web service that will respond with a user object when called with a valid Rails cookie.</p>';
		}

		function rails_login_documentation_text() {
			echo '<h3>Rails Application Setup</h3>';
			echo '<p>The URL specified above should accept the query string "?id=[rails-cookie]" and return a json object formatted like this:</p>';
			echo '<pre>';
			echo '{ "user": {<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"email": "user@example.com",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"first_name": "John",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"last_name": "Doe",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"username": "user@example.com",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"login": "user@example.com",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"nickname": "john",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"display_name": "John Doe",<br>';
			echo '&nbsp;&nbsp;&nbsp;&nbsp;"url": "http://example.com"<br>';
			echo '} }';
			echo '</pre>';
			echo '<p>or, if there is a problem with the cookie or the user is not logged in, it should return:</p>';
			echo '<pre>';
			echo '{"user":""}';
			echo '</pre>';
		}

		function rails_login_url_string() {
			$options = get_option( 'rails_login_options' );
			echo "<input id='rails_login_url' name='rails_login_options[url]' size='40' type='text' value='" . $options['url'] . "' /> e.g., http://example.com/authentication/user [DO NOT include .json]";
		}

		function rails_login_cookie_string() {
			$options = get_option( 'rails_login_options' );
			echo "<input id='rails_login_cookie' name='rails_login_options[cookie]' size='40' type='text' value='" . $options['cookie'] . "' /> e.g., _myapp_session";
		}

		function rails_login_single_login_string() {
			$html = '<input type="checkbox" id="rails_login_single_login" name="rails_login_options[single_login]" value="1"' . checked( 1, getBooleanOption('single_login'), false ) . '/>';
			$html .= '<label for="rails_login_single_login">When this is enabled, users will not have to click <i>login</i> or <i>logout</i>; WordPress will simply recognize their login state.<br><span style="color: red; font-weight: bold">Important:</span> activate this feature only after verifying that login and logout is functioning via the plugin. Otherwise, you may be locked out from WordPress.</label>';
			echo $html;
		}

		function rails_login_auto_create_string() {
			$html = '<input type="checkbox" id="rails_login_auto_create" name="rails_login_options[auto_create]" value="1"' . checked( 1, getBooleanOption('auto_create'), false ) . '/>';
			$html .= '<label for="rails_login_auto_create">Should a new user be created automatically if not already in the WordPress database?<br>Created users will obtain the role defined under &quot;New User Default Role&quot; on the <a href="options-general.php">General Options</a> page.</label>';
			echo $html;
		}

		function rails_login_options_validate( $input ) {
			$input['url'] = trim( $input['url'] );
			$input['cookie'] = trim( $input['cookie'] );

			return $input;
		}

		// Do simple caching of the RailsLoginWebService instance.
		function api() {
			if ( ! $this->api ) {
				$options = get_option( 'rails_login_options' );
				$this->api = new RailsLoginWebService( $options['url'], $options['cookie'] );
			}
			return $this->api;
		}


		/*************************************************************
		 * Plugin hooks
		 *************************************************************/

		function is_logged_in() {
			return $this->api()->is_logged_in();
		}

		function user_name() {
			return $this->api()->user_info()->{'username'};
		}

		// Check if the current person is logged in.  If so,
		// return the corresponding WP_User.
		function authenticate( $username, $password ) {
			if ( $this->is_logged_in() ) {
				$username = $this->user_name();
				//$password = $this->_get_password();
			} else {
				$this->redirect_to_login();
			}

			$user = get_user_by( 'login', $username );

			if ( ! $user or $user->user_login != $username ) {
				// User is logged into the API, but there's no WordPress user for them. Are we allowed to create one?
				if ( getBooleanOption('auto_create') ) {
					$this->_create_user( $username );
					$user = get_user_by( 'login', $username );
				} else {
					// Bail out to avoid showing the login form
					die( "User $username does not exist in the WordPress database and user auto-creation is disabled." );
				}
			}

			return new WP_User( $user->ID );
		}

		// Skip the password check, since we've externally authenticated.
		function skip_password_check( $check, $password, $hash, $user_id ) {
			return true;
		}

		// Logout the user by redirecting them to the logout URL.
		function logout() {
			header( 'Location: ' . "/logout" );
			exit();
		}

		// Send the user to the login page given by the API.
		function redirect_to_login() {
			header( 'Location: ' . "/login" );
			exit();
		}

		// Generate a password for the user. This plugin does not
		// require the user to enter this value, but we want to set it
		// to something non-obvious.
		function generate_password( $username, $password1, $password2 ) {
			$password1 = $password2 = $this->_get_password();
		}

		// Used to disable certain display elements, e.g. password
		// fields on profile screen.
		function disable_password_fields( $show_password_fields ) {
			return false;
		}

		// Used to disable certain login functions, e.g. retrieving a
		// user's password.
		function disable_function() {
			die( 'Disabled' );
		}

		//************************************************************
		// Private methods
		//************************************************************

		// Generate a random password.
		function _get_password( $length = 10 ) {
			return substr( md5( uniqid( microtime() ) ), 0, $length );
		}

		// Create a new WordPress account for the specified username.
		function _create_user( $username ) {
			$api_info            = (array) $this->api()->user_info();
			$u                   = array();
			$u['user_pass']      = $this->_get_password();
			$u['user_login']     = $username;
			$u['user_email']     = $api_info['email'];
			$u['user_url']       = $api_info['url'];
			$u['user_firstname'] = $api_info['first_name'];
			$u['user_lastname']  = $api_info['last_name'];

			$u['nickname']      = $api_info['nickname'];
			$u['display_name']  = $api_info['display_name'];
			$u['user_nicename'] = $u['display_name'];
			$u['description']   = $api_info['description'];

			$user_id = wp_insert_user( $u );
			if ( is_wp_error( $user_id ) ) {
				echo "ERROR: " . var_dump( $user_id );
			}
		}
	}
}

///////////////////////////////////////////////////
// Outside of the plugin class.
//
///////////////////////////////////////////////////

// Initialize the plugin
$rails_login_plugin_instance = new RailsLoginPlugin();

function getBooleanOption($key) {
	$options = get_option( 'rails_login_options' );
	if (array_key_exists($key, $options)) {
		return (bool) $options[$key];
	}
	return false;
}

function determine_current_user_filter() {
	global $rails_login_plugin_instance;
	if ( ! $rails_login_plugin_instance->is_logged_in() ) {
		return 0;
	}
	$user_login = $rails_login_plugin_instance->user_name();
	$rails_user = get_user_by( 'login', $user_login );

	return $rails_user->ID;
}

// Overriding this to provide the single sign-on function.  The user
// doesn't have to click the login link; the system will automatically
// log them in or out to match the current state returned by the API.

if ( getBooleanOption('single_login') ) {
	add_filter('determine_current_user', 'determine_current_user_filter', 21); // The standard cookie method is priority 20, so this is set higher so that it overwrites that.
}

// Extending this function purely for extra error checking; this will
// stop execution if the API is out of sync with Wordpress's "logged in" status.
if ( $API_DEBUG && ( ! function_exists( 'is_user_logged_in()' ) ) ) :
	function is_user_logged_in() {
		global $rails_login_plugin_instance;
		$result = '';
		$user   = wp_get_current_user();

		if ( $user->id == 0 ) {
			$result = false;
		} else {
			$result = true;
		}

		$options = get_option( 'rails_login_options' );
		$api = $rails_login_plugin_instance->api();
		if ( $api->is_logged_in() ) {
			if ( ! $result ) {
				die ( "Integration_API error: api yes, wp no." );
			}
		} else {
			if ( $result ) {
				die( "Integration_API error: api no, wp yes." );
			}
		}

		return $api->is_logged_in();
	}
endif;

?>
