<?php

/*
Plugin Name: Battleship
Description: An irresponsible use of the Heartbeat API.
Version: 1.0
Author: Evan Solomon
Author URI: http://evansolomon.me/
*/

/**
 * Copyright (c) 2013 Evan Solomon. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */


class ES_Battleship {
	private static $instance;

	static function get_instance() {
		if ( ! static::$instance )
			static::$instance = new self;

		return static::$instance;
	}

	private function __construct() {
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'wp_ajax_battleship_logout', array( $this, 'logout' ) );
		add_action( 'wp_logout', array( $this, 'logout_cleanup' ) );
	}

	function heartbeat_received( $res, $data ) {
		if ( ! isset( $data['battleship'] ) )
			return $res;

		$attendance = $this->update_attendance();
		$attendee_ids = array_keys( $attendance );
		$res['battleship']['attendees'] = $this->get_attendee_names( $attendee_ids );

		$logout = get_transient( $this->get_logout_option_name() );
		$res['battleship']['log_me_out'] = $logout;

		return $res;
	}

	function update_attendance() {
		$option_name = 'battleship_attendance';

		$attendance = get_option( $option_name, array() );
		$attendance[ get_current_user_id() ] = time();
		$attendance = array_filter($attendance, function( $time ) {
			return $time > time() - 30;
		});

		update_option( $option_name, $attendance );

		return $attendance;
	}

	function get_attendee_names($attendees) {
		$attendee_ids = array_filter( $attendees, function( $attendee_id ) {
			return $attendee_id != get_current_user_id();
		});

		$names = array_map( function( $attendee ) {
			return get_user_by( 'id', $attendee )->user_login;
		}, $attendee_ids);

		// Reset indices, otherwise we get a JS object instead of an array
		return array_values( $names );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'battleship-konami', plugins_url( 'lib/konami.js', __FILE__ ) );

		$deps = array( 'heartbeat', 'jquery', 'battleship-konami' );
		wp_enqueue_script( 'battleship', plugins_url( 'battleship.js', __FILE__ ), $deps );

		$nonce = wp_create_nonce( 'battleship_logout' );
		wp_localize_script( 'battleship', 'battleship', array( 'nonce' => $nonce ) );
	}

	function logout() {
		if ( ! wp_verify_nonce( $_POST['_nonce'], 'battleship_logout' ) )
			wp_die( json_encode( array( 'success' => false ) ) );

		$user = get_user_by( 'login', $_POST['name'] );
		set_transient( $this->get_logout_option_name( $user ), true, 30 );

		echo json_encode( array( 'success' => true ) );
		exit();
	}

	function logout_cleanup() {
		delete_transient( $this->get_logout_option_name() );
	}

	function get_logout_option_name( $user = false ) {
		if ( ! $user )
			$user = wp_get_current_user();

		return "battleship_logout_{$user->user_login}";
	}

}

ES_Battleship::get_instance();
