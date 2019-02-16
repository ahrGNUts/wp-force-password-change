<?php
/*
Plugin Name:  Force Password Change
Description:  Require users to change their password on first login.
Version:      0.8
License:      GPL v2 or later
Plugin URI:   https://github.com/lumpysimon/wp-force-password-change
Author:       Simon Blackbourn
Author URI:   https://twitter.com/lumpysimon
Author Email: simon@lumpylemon.co.uk
Text Domain:  force-password-change
Domain Path:  /languages/



	About this plugin
	-----------------

	This plugin redirects newly-registered users to the Admin -> Edit Profile page when they first log in.
	Until they have changed their password, they will not be able to access either the front-end or other admin pages.
	An admin notice is also displayed informing them that they must change their password.

	New administrators must also change their password, but as a safety measure they can also access the Admin -> Plugins page.

	Please report any bugs on the WordPress support forum at http://wordpress.org/support/plugin/force-password-change or via GitHub at https://github.com/lumpysimon/wp-force-password-change/issues

	Development takes place at https://github.com/lumpysimon/wp-force-password-change (all pull requests will be considered)



	About me
	--------

	I'm Simon Blackbourn, co-founder of Lumpy Lemon, a small & friendly UK-based
	WordPress design & development company specialising in custom-built WordPress CMS sites.
	I work mainly, but not exclusively, with not-for-profit organisations.

	Find me on Twitter, Skype & GitHub: lumpysimon



	License
	-------

	Copyright (c) Lumpy Lemon Ltd. All rights reserved.

	Released under the GPL license:
	http://www.opensource.org/licenses/gpl-license.php

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.



*/

// bail if accessed directly
defined( 'ABSPATH' ) || exit;

if( !class_exists( 'Force_Password_Change' ) ){
	register_activation_hook( __FILE__, array( 'Force_Password_Change', 'activate_plugin' ) );
	register_uninstall_hook( __FILE__, array( 'Force_Password_Change', 'delete_plugin' ) );
	
	final class Force_Password_Change {
	
		private static $instance = null;
		
		// just a bunch of functions called from various hooks
		function __construct() {
	
			add_action( 'init',                     	  	array( $this, 'init' ) );
			add_action( 'user_register',            	  	array( $this, 'registered' ) );
			add_action( 'personal_options_update',  	  	array( $this, 'updated' ) );
			add_action( 'template_redirect',        		array( $this, 'redirect' ) );
			add_action( 'current_screen',           		array( $this, 'redirect' ) );
			add_action( 'admin_notices',            		array( $this, 'notice' ) );
			add_action( 'admin_menu', 			    		array( $this, 'menu_item' ) );
			add_action( 'admin_enqueue_scripts',    		array( $this, 'enqueue_scripts' ) );
			add_action( 'edit_user_profile', 	    	  	array( $this, 'show_change_password_cb' ) );
			add_action( 'edit_user_profile_update', 	  	array( $this, 'process_password_cb' ) );
			add_action( 'admin_post_process_fpc_options',	array( $this, 'process_fpc_options' ) );
			add_action( 'user_profile_update_errors',		array( $this, 'validate_password' ), 10, 3 );
		}
		
		/**
		 * Return class instance.
		 *
		 * @return static Instance of class.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}
	
			return self::$instance;
		}
		
		/**
		 * Creates options that can be changed in the admin menu.
		 */
		public static function activate_plugin() {
			add_option( '_enforce_admin_pw_change', 1 );
			add_option( '_allow_weak_admin_pw', 0 );
			add_option( '_allow_weak_user_pw', 1 );
			add_option( '_custom_redirect_page_id', 0 );
			add_option( '_custom_pw_redirect_link', '' );
		}
		
		/**
		 * Cleans up options created during plugin activation.
		 */
		public static function delete_plugin() {
			delete_option( '_enforce_admin_pw_change' );
			delete_option( '_allow_weak_admin_pw' );
			delete_option( '_allow_weak_user_pw' );
			delete_option( '_custom_redirect_page_id' );
			delete_option( '_custom_pw_redirect_link' );
		}
	
		// load localisation files
		public function init() {
	
			load_plugin_textdomain(
				'force-password-change',
				false,
				dirname( plugin_basename( __FILE__ ) ) . '/languages'
				);
	
		}
	
		// add a user meta field when a new user is registered
		public function registered( $user_id ) {
			$user_meta = get_userdata( $user_id );

			if( ( get_option( '_enforce_admin_pw_change' ) && in_array( 'administrator', $user_meta->roles ) ) || !in_array( 'administrator', $user_meta->roles ) )
				add_user_meta( $user_id, 'force-password-change', 1 );
		}
	
		// delete the user meta field when a user successfully changes their password
		public function updated( $user_id ) {
	
			$pass1 = $pass2 = '';
	
			if ( isset( $_POST['pass1'] ) )
				$pass1 = $_POST['pass1'];
	
			if ( isset( $_POST['pass2'] ) )
				$pass2 = $_POST['pass2'];
	
			if (
				$pass1 != $pass2
				or
				empty( $pass1 )
				or
				empty( $pass2 )
				or
				false !== strpos( stripslashes( $pass1 ), "\\" )
				or 
				isset( $_POST['pw_weak'] ) && !get_option( '_allow_weak_user_pw' ) && !current_user_can( 'administrator' ) 
				or
				isset( $_POST['pw_weak'] ) && get_option( '_enforce_admin_pw_change' ) && !get_option( '_allow_weak_admin_pw' ) && current_user_can( 'administrator' )
				)
				return;
	
			delete_user_meta( $user_id, 'force-password-change' );
		}
	
		// if:
		// - we're logged in,
		// - the user meta field is present,
		// - we're on the front-end or any admin screen apart from the edit profile page or plugins page,
		// then redirect to the edit profile page
		public function redirect() {
		
			if ( ! is_user_logged_in() )
				return;
			
			if ( get_user_meta( get_current_user_id(), 'force-password-change', true ) ) {
				if( is_admin() ) {
					$screen = get_current_screen();
					if ( 'profile' == $screen->base )
						return;
					if ( 'plugins' == $screen->base )
						return;
				} else {
					wp_redirect( admin_url( 'profile.php' ) );
					exit; // never forget this after wp_redirect!
				}
			}
		}
	
		// if the user meta field is present, display an admin notice
		public function notice() {
	
			if ( get_user_meta( get_current_user_id(), 'force-password-change', true ) ) {
				printf(
					'<div class="error"><p>%s</p></div>',
					__( 'Please change your password in order to continue using this website', 'force-password-change' )
					);
			}
	
		}
		
		public function menu_item(){
			add_menu_page(
				__( 'Force Password Change Settings', 'force-password-change' ),
				'Force Password Change',
				'manage_options',
				'force-password-change',
				array( $this, 'admin_menu_page_content' ),
				'dashicons-admin-settings'
			);
		}
			
		public function admin_menu_page_content() {
			require( 'views/fpc-admin-settings.php' );
		}
		
		private function set_checked_status( $option ){
			return empty( $option ) ? '' : 'checked';
		}
		
		private function set_element_visibility( $option ) {
			return empty( $option ) ? 'style="display:none;"' : '';
		}
		
		public function enqueue_scripts( $hook ) {
			if( $hook == "toplevel_page_force-password-change" && is_admin() && current_user_can( 'administrator' ) ){
				wp_enqueue_script( 'fpc_admin_menu', plugin_dir_url( __FILE__ ) . 'assets/js/force-password-change.js', array( 'jquery' ) );
				wp_enqueue_style( 'fpc_admin_styles', plugin_dir_url( __FILE__ ) . 'assets/css/force-password-change.css' );
			} else if( is_admin() && $hook == "profile.php" ){
				wp_enqueue_script( 'fpc_badpw_cb', plugin_dir_url( __FILE__ ) . 'assets/js/badpw_cb.js', array( 'jquery' ) );
			}
	
		}
		
		/**
		 *	Show markup for a checkbox displayed on the edit user page that will force the user to change their passsword when checked.
		 *	Checkbox will only be visible to admin users
		 *	
		 *	@since 0.8
		 *  @author Patrick Strube
		 */
		public function show_change_password_cb( $user ) {
			include( 'views/fpc-change-password-cb.php' );
		}
		
		/**
		 *	Process immediate password change option on user's profile edit page
		 *  
		 *  @since 0.8
		 *	@author Patrick Strube
		 */ 
		public function process_password_cb( $user_id ){ 
			if( !current_user_can( 'administrator' ) )
				return false;
			
			if( isset( $_POST['change_pw_cb'] ) ){
				update_user_meta( $user_id, 'force-password-change', 1 );
			} else {
				delete_user_meta( $user_id, 'force-password-change' );
			}
		}
		
		/**
		 *	Process settings update on main FPC settings page
		 *	
		 *	@since 0.8
		 *	@author Patrick Strube
		 */
		public function process_fpc_options() {
			if( !wp_verify_nonce( $_POST['_fpc_options_nonce'], 'process_fpc_options' ) || !current_user_can( 'administrator' ) ){
				wp_die( 
					__( 'Invalid nonce.', 'force-password-change' ), 
					__( 'Error', 'force-password-change' ), 
					array(
						'response' => 403,
						'back_link' => 'admin.php?page=force-password-change'
					)
				);
			}
				
			// Admin pw
			if( isset( $_POST['enforce_admin_pw_change'] ) ){
				update_option( '_enforce_admin_pw_change', 1 );
				
				if( isset( $_POST['allow_weak_admin_pw'] ) ){
					update_option( '_allow_weak_admin_pw', 1 );
				} else {
					update_option( '_allow_weak_admin_pw', 0 );
				}
			} else {
				update_option( '_enforce_admin_pw_change', 0 );
			}
			
			// user pw
			if( isset( $_POST['allow_weak_user_pw'] ) ){
				update_option( '_allow_weak_user_pw', 1 );
			} else {
				update_option( '_allow_weak_user_pw', 0 );
			}
			
			// custom redirect
			if( isset( $_POST['custom_pw_redirect_picker'] ) && !empty( $_POST['custom_pw_redirect_picker'] ) ){
				$picker_value = $_POST['custom_pw_redirect_picker'];
				
				update_option( '_custom_redirect_page_id', $picker_value );
				if( $picker_value == "custom" ){
					$url = sanitize_text_field( $_POST['custom_url_redirect'] );
					
					update_option( '_custom_pw_redirect_link', $url );
				} else {
					$url = get_permalink( $picker_value );
					
					update_option( '_custom_pw_redirect_link', $url );
				}
			} else {
				update_option( '_custom_redirect_page_id', 0 );
				update_option( '_custom_pw_redirect_link', '' );
			}
			
			wp_safe_redirect( $_SERVER['HTTP_REFERER'] );
			exit;
		}
		
		public function validate_password( &$errors, $update, $user ) {
			
			if( $update ){
				if( isset( $user->role ) )
					$is_administrator = $user->role == 'administrator';
				else if( isset( $user->roles ) )
					$is_administrator = in_array( 'administrator', $user->roles );
				
				if( $is_administrator && ( get_option( '_enforce_admin_pw_change' ) && get_option( '_allow_weak_admin_pw' ) == 0 ) && isset( $_POST['pw_weak'] ) ){
					// if admin and weak admin passwords not allowed
					$errors->add( 'admin_pw_error', __( 'Weak administrator passwords are not allowed. Please choose a stronger password and try again.', 'force-password-change' ) );
				} else if( !$is_administrator && is_user_logged_in() && get_option( '_allow_weak_user_pw' ) == 0 && isset( $_POST['pw_weak'] ) ){
					// if not admin and weak user passwords not allowed
					$errors->add( 'user_pw_error', __( 'Weak password detected! Please choose a stronger password and try again.', 'force-password-change' ) );
				}
			}
		}
	} // class
	
	Force_Password_Change::instance();
}
