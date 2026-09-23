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
				<th scope="row"><?php esc_html_e( 'Page Builder', 'astra-portfolio' ); ?></th>
				<td>
					<fieldset>
						<select name="page-builder">
							<option value="" data-value=""><?php esc_html_e( 'All', 'astra-portfolio' ); ?></option>
							<?php
							$page_builders = Astra_Portfolio_Page::get_instance()->get_page_builders();
							foreach ( $page_builders as $page_builder_id => $page_builder_data ) {
								?>
								<option value="<?php echo esc_attr( $page_builder_id ); ?>" data-value="<?php echo esc_attr( $page_builder_data['slug'] ); ?>" <?php selected( $page_builder_id, $data['page-builder'] ); ?>><?php echo esc_html( $page_builder_data['name'] ); ?></option>
							<?php } ?>
						</select>
						<?php /* translators: %s is the documentation link. */ ?>
						<p class="description"><?php esc_html_e( 'Choose your preferred page builder from the list. Starter templates built with chosen page builder will only be imported. Choose "All" to import available starter templates with different page builders.', 'astra-portfolio' ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Import Starter Templates', 'astra-portfolio' ); ?></th>
				<td>
					<fieldset>					
						<?php
							$batch_status = get_option( 'astra-portfolio-batch-process-string', '' );
							$message      = __( 'Sync', 'astra-portfolio' );
							$disabled     = '';
						if ( ! empty( $batch_status ) ) {
							$message  = $batch_status;
							$disabled = 'is-disabled astra-sites-batch-processing';
						}

						$_nonce = wp_create_nonce( 'astra-portfolio-batch-process' );

						$sync_type = isset( $_GET['sync_type'] ) ? sanitize_key( $_GET['sync_type'] ) : 'batch'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

						$cron_status_status = self::test_cron();
						if ( is_wp_error( $cron_status_status ) && 'ajax' !== $sync_type ) {
							if ( 'wp_portfolio_cron_error' === $cron_status_status->get_error_code() ) {
								echo '<p class="description">' . wp_kses_post( $cron_status_status->get_error_message() ) . '</p>';
							} else {
								echo '<p class="description">';
								printf(
									/* translators: 1: Error message text. */
									esc_html__( 'ERROR! There was a problem while testing the cron schedules on your website. The problem is: %s', 'astra-portfolio' ),
									'<br><strong>' . esc_html( $cron_status_status->get_error_message() ) . '</strong>'
								);
								echo '</p>';
							}
						}

						if ( ! is_wp_error( $cron_status_status ) || 'ajax' === $sync_type ) {
							?>
							<span class="button astra-portfolio astra-portfolio-sync-library <?php echo esc_attr( $disabled ); ?>"><?php echo esc_html( $message ); ?></span>
							<?php /* translators: %s is the documentation link. */ ?>
							<p class="description"><?php printf( wp_kses_post( 'Import Starter Templates as portfolio items. <a href="%s" target="_blank">Read how this works</a>.', 'astra-portfolio' ), esc_url( 'https://wpportfolio.net/?p=24600' ) ); ?></p>
							<?php
						}
						?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Shortcode', 'astra-portfolio' ); ?></th>
				<td>
					<fieldset>
						<input type="text" onfocus="this.select();" readonly="readonly" class="regular-text astra-portfolio-shortcode-text" value="[wp_portfolio]" />
						<?php /* translators: %s is the documentation link. */ ?>
						<p class="description"><?php printf( wp_kses_post( 'Paste the shortcode on the page where you need to display portfolio items. See the complete list of shortcode attributes <a href="%s" target="_blank">here</a>.', 'astra-portfolio' ), esc_url( 'https://wpportfolio.net/?p=28498' ) ); ?></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Display', 'astra-portfolio' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="categories" value="1" <?php checked( $data['categories'], 1 ); ?> /> <?php esc_html_e( 'Enable sorting by categories.', 'astra-portfolio' ); ?>
						</label>
					</fieldset>
					<fieldset>
						<label>
							<input type="checkbox" name="other-categories" value="1" <?php checked( $data['other-categories'], 1 ); ?> /> <?php esc_html_e( 'Enable sorting by other categories.', 'astra-portfolio' ); ?>
						</label>
					</fieldset>
					<fieldset>
						<label>
							<input type="checkbox" name="show-search" value="1" <?php checked( $data['show-search'], 1 ); ?> /> <?php esc_html_e( 'Display sites search box.', 'astra-portfolio' ); ?>
						</label>
					</fieldset>
					<fieldset>
						<label>
							<input type="checkbox" name="responsive-button" value="1" <?php checked( $data['responsive-button'], 1 ); ?> /> <?php esc_html_e( 'Display responsive buttons.', 'astra-portfolio' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<input type="hidden" name="message" value="saved" />
		<input type="hidden" name="tab_slug" value="general" />
		<?php wp_nonce_field( 'astra-portfolio-importing', 'astra-portfolio-import' ); ?>

		<?php submit_button(); ?>
	</form>
</div>
