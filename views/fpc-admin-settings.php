<?php
/**
  *	Markup for admin settings page
  *	@since 0.8
  *	@author Patrick Strube
  */

defined( 'ABSPATH' ) || exit; 

if( is_admin() && current_user_can( 'administrator' ) ): ?>

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
					<input type="checkbox" name="enforce_admin_pw_change" id="enforce_admin_pw_change" <?php echo self::set_checked_status( get_option( '_enforce_admin_pw_change' ) ); ?>>
					<p class="description">Enforce password changes for new admin users (Default: enabled)</p>
				</td>
			</tr>
			<tr id="admin_pw_row" <?php echo self::set_element_visibility( get_option( '_enforce_admin_pw_change' ) ); ?>>
				<th>
					<label for="allow_weak_admin_pw"><strong>Allow Weak Admin Passwords</strong></label>
				</th>
				
				<td>
					<input type="checkbox" name="allow_weak_admin_pw" <?php echo self::set_checked_status( get_option( '_allow_weak_admin_pw' ) ); ?>>
					<p class="description">Allow admin users to set weak passwords. If this is unchecked and Wordpress detects a weak password during a password update, the weak password will not be set and the admin user will be prompted to set a stronger password. (Default: disabled)</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="allow_weak_user_pw"><strong>Allow Weak User Passwords</strong></label>
				</th>
				<td>
					<input type="checkbox" name="allow_weak_user_pw" <?php echo self::set_checked_status( get_option( '_allow_weak_user_pw' ) ); ?>>
					<p class="description">Allow non-admin users to set weak passwords. If this is unchecked and Wordpress detects a weak password during a password update, the weak password will not be set and the user will be prompted to set a stronger password. (Default: enabled)</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="custom_pw_redirect_picker">Custom Redirect Page</label>
				</th>
				<td>
					<select name="custom_pw_redirect_picker" id="custom_pw_redirect_picker">
						<option value="">N/A</option>
						<?php
							$page_id = get_option( '_custom_redirect_page_id' );
							$selected = $page_id == "custom" ? 'selected' : '';
							
							echo '<option value="custom" ' . $selected . '>Custom URL</option>';
							
							$pages = get_posts(
								array(
									'post_type' => 'page',
									'post_status' => 'publish',
									'fields' => array( 'ID', 'post_title' )
								)
							);
							
							foreach( $pages as $page ) {
								if( $page_id == $page->ID )
									echo '<option value="' . $page->ID . '" selected>' . $page->post_title . '</option>'; 
								else
									echo '<option value="' . $page->ID . '">' . $page->post_title . '</option>';
							}
						?>
					</select>
					<p class="description">If your users can change their password on a page other than the backend profile management page, you can set it here. Select 'N/A' to use the standard wp-admin page.</p>
				</td>
			</tr>
			<tr id="redirect_url_row" <?php echo self::set_element_visibility( get_option( '_custom_pw_redirect_link' ) ); ?>>
				<th>
					<label for="custom_url_redirect">Custom Redirect URL</label>
				</th>
				<td>
					<input type="text" name="custom_url_redirect" id="custom_url_redirect" value="<?php echo get_option( '_custom_pw_redirect_link' );?>">
					<p class="description">If you are using a custom URL for the redirect, please enter it here.</p>
				</td>
			</tr>
		</table>
		<input type="hidden" name="action" value="process_fpc_options">
		<button type="submit" name="submit" class="button button-primary"><?php printf( __( "Update Options", 'force-password-change' ) ); ?></button>
	</form>
</div>
<?php 
	else:
		wp_die( "Sorry, it looks like you're not allowed to access this page." );
	endif;
