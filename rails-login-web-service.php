<?php

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
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class RailsLoginWebService {
	public $user_info;
	public $server_path;
	public $cookie_name;

	public function __construct( $url, $cookie_name ) {
		$this->server_path = $url;
		$this->cookie_name = $cookie_name;
	}

	//------------- Public API ---------------

	public function is_logged_in() {
		return ! ( $this->user_info() == null );
	}

	public function user_info() {
		if ( ! $this->user_info ) { // Cache this value so there is never two calls for the same page.
			if ( $this->rails_cookie_value() == null ) {
				return null;
			}
			$json_data = $this->call_http( ".json?id=" . $this->rails_cookie_value() );

			$this->user_info = $json_data->{'user'};
		}
		return $this->user_info;
	}

	//------------- Private methods -------------

	function rails_cookie_value() {
		return $_COOKIE[ $this->cookie_name ];
	}

	function warn_if_debugging( $msg ) {
		if ( defined( 'WP_DEBUG' ) and WP_DEBUG == true ) {
			echo $msg;
		}
	}

	function call_http( $URL ) {
		// Initialize the library if we've never called it before.
		if ( ! class_exists( 'WP_Http' ) ) {
			include_once( ABSPATH . WPINC . '/class-http.php' );
		}

		$request = new WP_Http;
		$result = $request->request( $this->server_path . $URL, array('sslverify' => false) );
		if ( is_wp_error( $result ) ) {
			$this->warn_if_debugging( "Error connecting to (" . $this->server_path . $URL . "): " . $result->get_error_message() );

			return json_decode('user: {}');
		} else if ( $result['response']['code'] == 200 ) {
			return json_decode($result['body']);
		} else {
			$this->warn_if_debugging( "Error connecting to (" . $this->server_path . $URL . "): " . $result['response']['message'] );

			return json_decode('user: {}');
		}
	}

}

?>