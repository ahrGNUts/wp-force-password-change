<?php
/*
Plugin Name:  Force Password Change
Description:  Require users to change their password on first login.
Version:      0.7
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
	
			add_action( 'init',                    array( $this, 'init' ) );
			add_action( 'user_register',           array( $this, 'registered' ) );
			add_action( 'personal_options_update', array( $this, 'updated' ) );
			add_action( 'template_redirect',       array( $this, 'redirect' ) );
			add_action( 'current_screen',          array( $this, 'redirect' ) );
			add_action( 'admin_notices',           array( $this, 'notice' ) );
			add_action( 'admin_menu', 			   array( $this, 'menu_item' ) );
			add_action( 'admin_enqueue_scripts',   array( $this, 'enqueue_scripts' ) );
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
			add_option( '_custom_pw_redirect_link', '' );
		}
		
		/**
		 * Cleans up options created during plugin activation.
		 */
		public static function delete_plugin() {
			delete_option( '_enforce_admin_pw_change' );
			delete_option( '_allow_weak_admin_pw' );
			delete_option( '_allow_weak_user_pw' );
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
				__( 'Force Password Change', 'force-password-change' ),
				'Force Password Change',
				'manage_options',
				'force-password-change',
				array( $this, 'admin_menu_markup' ),
				'dashicons-admin-settings'
			);
		}
			
		public function admin_menu_markup() {
			if( is_admin() && current_user_can( 'administrator' ) ){ 
			?>
			<div class="wrap">
				<h2>Force Password Change Settings</h2>
				<form method="post" action="<?php echo esc_html( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'process_fpc_options', '_fpc_options_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th>
								<label for="enforce_admin_pw_change"><strong>Enforce For Admin Users</strong></label>
							</th>
							
							<td>
								<input type="checkbox" name="enforce_admin_pw_change" id="enforce_admin_pw_change" <?php echo self::set_option_status( '_enforce_admin_pw_change' ); ?>>
								<p class="description">Enforce password changes for new admin users (Default: enabled)</p>
							</td>
						</tr>
						<?php // TODO: make this dynamically appear/disappear ?>
						<tr id="admin_pw_row" <?php echo self::set_element_visibility( '_enforce_admin_pw_change' ); ?>>
							<th>
								<label for="allow_weak_admin_pw"><strong>Allow Weak Admin Passwords</strong></label>
							</th>
							
							<td>
								<input type="checkbox" name="allow_weak_admin_pw" <?php echo self::set_option_status( '_allow_weak_admin_pw' ); ?>>
								<p class="description">Allow admin users to set weak passwords. If this is unchecked and Wordpress detects a weak password during a password update, the weak password will not be set and the admin user will be prompted to set a stronger password. (Default: disabled)</p>
							</td>
						</tr>
						<tr>
							<th>
								<label for="allow_weak_user_pw"><strong>Allow Weak User Passwords</strong></label>
							</th>
							<td>
								<input type="checkbox" name="allow_weak_user_pw" <?php echo self::set_option_status( '_allow_weak_user_pw' ); ?>>
								<p class="description">Allow non-admin users to set weak passwords. If this is unchecked and Wordpress detects a weak password during a password update, the weak password will not be set and the user will be prompted to set a stronger password. (Default: enabled)</p>
							</td>
						</tr>
						<tr>
							<?php //add_option( '_custom_pw_redirect_link', '' ); ?>
							<th>
								<label for="custom_pw_redirect_picker">Custom Redirect Page</label>
							</th>
							<td>
								<select name="custom_pw_redirect_picker" id="custom_pw_redirect_picker">
									<option value="">N/A</option>
									<option value="custom">Custom URL</option>
									<?php
										$pages = get_posts(
											array(
												'post_type' => 'page',
												'post_status' => 'publish',
												'fields' => array( 'ID', 'post_title' )
											)
										);
										
										foreach( $pages as $page ) {
											echo '<option value="' . $page->ID . '">' . $page->post_title . '</option>'; 
										}
									?>
								</select>
								<p class="description">If your users can change their password on a page other than the backend profile management page, you can set it here. Select 'N/A' to use the standard wp-admin page.</p>
							</td>
						</tr>
						<tr id="redirect_url_row" <?php echo self::set_element_visibility( '_custom_pw_redirect_link' ); ?>>
							<th>
								<label for="custom_url_redirect">Custom Redirect URL</label>
							</th>
							<td>
								<input type="text" name="custom_url_redirect" id="custom_url_redirect">
								<p class="description">If you are using a custom URL for the redirect, please enter it here.</p>
							</td>
						</tr>
					</table>
					<input type="hidden" name="action" value="process_fpc_options">
					<button type="submit" name="submit" class="button button-primary"><?php printf( __( "Update Options", 'force-password-change' ) ); ?></button>
				</form>
			</div>
			<?php 
			} else {
				wp_die( "Sorry, it looks like you're not allowed to access this page." );
			}	
		}
		
		private function set_option_status( $option ){
			return empty( get_option( $option ) ) ? '' : 'checked';
		}
		
		private function set_element_visibility( $option ) {
			return empty( get_option( $option ) ) ? 'style="display:none;"' : '';
		}
		
		public function enqueue_scripts( $hook ) {
			if( $hook == "toplevel_page_force-password-change" && is_admin() && current_user_can( 'administrator' ) ){
				wp_enqueue_script( 'fpc_admin_menu', plugin_dir_url( __FILE__ ) . 'js/force-password-change.js', array( 'jquery' ) );
				wp_enqueue_style( 'fpc_admin_styles', plugin_dir_url( __FILE__ ) . 'css/force-password-change.css' );
			}
				
		}
	} // class
	
	Force_Password_Change::instance();
}
