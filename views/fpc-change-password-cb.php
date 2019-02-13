<?php
/**
  *	Markup for user edit page checkbox. $user is accessible
  *	@since 0.8
  *	@author Patrick Strube
  */
  
defined( 'ABSPATH' ) || exit;

if( is_admin() && current_user_can( 'administrator' ) ){
	$pw_change_required = get_user_meta( $user->ID, 'force-password-change', true ); 
	var_dump( $pw_change_required );
?>

<h3>Force Password Change</h3>
<table class="form-table">
	<tr>
		<th>
			<label for="change_pw_cb">
				Immediately Force Password Change
			</label>
		</th>
		<td>
			<input type="checkbox" name="change_pw_cb" id="change_pw_cb" <?php echo self::set_checked_status( $pw_change_required ); ?>>
			<p class="description">Checking this will force the user to change their password the next time they log in or immediately if they are already logged in.</p>
		</td>
	</tr>
</table>
<?php
}