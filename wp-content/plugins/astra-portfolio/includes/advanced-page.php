<?php
/**
 * Astra Demo View.
 *
 * @package Astra Portfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Ok.
}

// Ensure $data is defined before using it.
$data = isset( $data ) ? $data : array(); // Default to an empty array if not defined.

?>

<div class="wrap">

	<form id="astra-portfolio-settings" enctype="multipart/form-data" method="post">

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Rewrite Slug', 'astra-portfolio' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="text" name="rewrite" value="<?php echo esc_attr( $data['rewrite'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Rewrite portfolio url slug.', 'astra-portfolio' ); ?></p>
						</label>
					</fieldset>
					<fieldset>
						<label>
							<input type="text" name="rewrite-tags" value="<?php echo esc_attr( $data['rewrite-tags'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Rewrite portfolio tags url slug.', 'astra-portfolio' ); ?></p>
						</label>
					</fieldset>
					<fieldset>
						<label>
							<input type="text" name="rewrite-categories" value="<?php echo esc_attr( $data['rewrite-categories'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Rewrite portfolio categories url slug.', 'astra-portfolio' ); ?></p>
						</label>
					</fieldset>
					<fieldset>
						<label>
							<input type="text" name="rewrite-other-categories" value="<?php echo esc_attr( $data['rewrite-other-categories'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Rewrite portfolio other categories url slug.', 'astra-portfolio' ); ?></p>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Contribute to WP Portfolio', 'astra-portfolio' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input id="astra-portfolio-analytics-optin" type="checkbox" value="1" name="astra-portfolio_analytics_optin" <?php checked( get_site_option( 'astra-portfolio_analytics_optin', 'no' ), 'yes' ); ?>>
							<?php
							echo esc_html( sprintf( __( 'Collect non-sensitive information from your website, such as the PHP version and features used, to help us fix bugs faster, make smarter decisions, and build features that actually matter to you.', 'astra-portfolio' ), 'Brainstorm Force' ) );
							?>
						</label>
						<?php
						echo wp_kses_post( sprintf( '<a href="%1s" target="_blank" rel="noreferrer noopener">%2s</a>', esc_url( 'https://store.brainstormforce.com/usage-tracking/?utm_source=wp_dashboard&utm_medium=general_settings&utm_campaign=usage_tracking' ), __( 'Learn More', 'astra-portfolio' ) ) );
						?>
					</fieldset>
				</td>
			</tr>
		</table>

		<input type="hidden" name="message" value="saved" />
		<input type="hidden" name="tab_slug" value="advanced" />
		<?php wp_nonce_field( 'astra-portfolio-importing', 'astra-portfolio-import' ); ?>

		<?php submit_button(); ?>
	</form>
</div>
