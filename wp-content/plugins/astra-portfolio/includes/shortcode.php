<?php
/**
 * Shortcode Markup
 *
 * @package Astra Portfolio
 * @since 1.0.0
 */

// Ensure $args is defined before using it.
$args = isset( $args ) ? $args : array(); // Default to an empty array if not defined.

do_action( 'astra_portfolio_shortcode_top', $args );

$style             = isset( $args['data']['grid-style'] ) ? $args['data']['grid-style'] : 'style-1';
$show_portfolio_on = isset( $args['data']['show-portfolio-on'] ) ? $args['data']['show-portfolio-on'] : 'scroll';
?>
<div id="astra-portfolio" class="astra-portfolio-wrap astra-portfolio-<?php echo esc_attr( $style ); ?> astra-portfolio-show-on-<?php echo esc_attr( $show_portfolio_on ); ?>"
	data-other-categories="<?php echo esc_attr( $args['data']['other-categories'] ); ?>"
	data-categories="<?php echo esc_attr( $args['data']['categories'] ); ?>"
	data-tags="<?php echo esc_attr( $args['data']['tags'] ); ?>"
>
	<?php
	if (
		'yes' === $args['data']['show-other-categories'] ||
		'yes' === $args['data']['show-categories'] ||
		'yes' === $args['data']['show-search']
	) {
		?>
	<div class="astra-portfolio-filters" class="wp-filter hide-if-no-js">

		<!-- All Filters -->
		<div class="filters-wrap">
			<?php
			if ( 'yes' === $args['data']['show-other-categories'] ) {
				?>
				<div class="astra-portfolio-other-categories-wrap"></div>
			<?php } ?>				
			<?php if ( 'yes' === $args['data']['show-categories'] ) { ?>
				<div class="astra-portfolio-categories-wrap"></div>
			<?php } ?>				
		</div>

		<?php if ( 'yes' === $args['data']['show-search'] ) { ?>
			<div class="search-form">
				<label class="screen-reader-text" for="astra-portfolio-search"><?php esc_html_e( 'Search', 'astra-portfolio' ); ?> </label>
				<input placeholder="<?php esc_html_e( 'Search...', 'astra-portfolio' ); ?>" type="search" aria-describedby="live-search-desc" class="astra-portfolio-search">
			</div>
		<?php } ?>

	</div>
	<?php } ?>

	<!-- All Astra Portfolio -->
	<div class="astra-portfolio-shortcode-wrap astra-portfolio-grid astra-portfolio <?php echo esc_attr( $args['row_class'] ); ?>"></div><!-- .astra-portfolio -->

</div><!-- .astra-portfolio. -->

<?php do_action( 'astra_portfolio_shortcode_bottom', $args ); ?>

<?php
/**
 * Load More
 */
?>
<script type="text/template" id="tmpl-astra-portfolio-load-more-sites">
	<button class="astra-portfolio-load-more-sites"><?php esc_html_e( 'Load More', 'astra-portfolio' ); ?></button>
</script>

<?php
/**
 * Spinner
 */
?>
<script type="text/template" id="tmpl-astra-portfolio-spinner">
	<span class="spinner is-active"></span>
</script>

<?php
/**
 * No items found
 */
?>
<script type="text/template" id="tmpl-astra-portfolio-not-found">
	<span class="astra-portfolio-not-found">
		<p>
			<?php esc_html_e( 'No items found.', 'astra-portfolio' ); ?><br/>
		</p>
	</span>
</script>

<?php
/**
 * No items demos
 */
?>
<script type="text/template" id="tmpl-astra-portfolio-no-more-demos">
	<span class="no-more-demos">
		<?php
		if ( isset( $args['data']['no-more-sites-message'] ) ) {
			echo do_shortcode( wp_kses_post( $args['data']['no-more-sites-message'] ) );
		}
		?>
	</span>
</script>
