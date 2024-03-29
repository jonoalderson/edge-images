<?php
/**
 * Edge Images plugin file.
 *
 * @package Edge_Images\Views
 */

namespace Edge_Images\Views;

// Avoid direct calls to this file.
if ( ! defined( 'EDGE_IMAGES_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>

<div class="wrap">

	<h1>Edge Images</h1>

	<form method="post" action="options.php">

		<input type="hidden" name="option_page" value="edge_images">
		<input type="hidden" name="action" value="update">

		<input type="hidden" id="_wpnonce" name="_wpnonce" value="XXXXXX">
		<input type="hidden" name="_wp_http_referer" value="XXXX">

		<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="edge_provider">Edge Provider</label></th>
				<td>
					<select id="edge_provider" name="edge_provider">
						<option>Cloudflare</option>
						<option>Accelerated Domains</option>
					</select>
				</td>
			</tr>
		</tbody>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
		</p>

	</form>

</div>
