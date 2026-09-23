<?php
/**
 * Add portfolio form
 *
 * @package Astra Portfolio
 * @since 1.0.2
 */

?>
<div class="wrap">

	<h1><?php esc_html_e( 'Add New', 'astra-portfolio' ); ?></h1>

	<p><?php esc_html_e( 'Quick create portfolio using below form.', 'astra-portfolio' ); ?></p>

	<form class="astra-portfolio-new-template-form" name="astra-portfolio-new-template-form" method="POST">

		<table class="widefat astra-portfolio-table">

			<tr class="astra-portfolio-row">
				<th class="astra-portfolio-heading">
					<label for="astra-portfolio-template[title]"><?php esc_html_e( 'Title', 'astra-portfolio' ); ?></label>
				</th>
				<td class="astra-portfolio-content">
					<input class="astra-portfolio-template-title regular-text" type="text" name="astra-portfolio-template[title]" required />
				</td>
			</tr>

			<tr class="astra-portfolio-row">
				<th class="astra-portfolio-heading">
					<label for="astra-portfolio-template[type]"><?php esc_html_e( 'Type', 'astra-portfolio' ); ?></label>
				</th>
				<td class="astra-portfolio-content">
					<select class="astra-portfolio-template-type" name="astra-portfolio-template[type]" required>
						<option value=""><?php esc_html_e( 'Select Type...', 'astra-portfolio' ); ?></option>
						<?php foreach ( $types as $portfolio_type ) : ?>
						<option value="<?php echo esc_attr( $portfolio_type['key'] ); ?>" <?php selected( Astra_Portfolio_Page::get_instance()->get_default_portfolio_type(), $portfolio_type['key'] ); ?>>
							<?php echo esc_html( $portfolio_type['label'] ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>

		</table>

		<p class="submit">
			<input type="submit" class="astra-portfolio-template-add button button-primary button-large" value="<?php esc_attr_e( 'Add Portfolio Item', 'astra-portfolio' ); ?>">
		</p>

		<?php wp_nonce_field( 'astra-portfolio-add-template-nonce', 'astra-portfolio-add-template' ); ?>

	</form>
</div>
