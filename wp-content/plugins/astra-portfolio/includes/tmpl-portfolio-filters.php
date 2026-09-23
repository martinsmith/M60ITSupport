<?php
/**
 * Portfolio filters
 *
 * @package Astra Portfolio
 * @since 1.0.6
 */

$all_text = apply_filters( 'astra_portfolio_filters_all_text', esc_html__( 'All', 'astra-portfolio' ) );

?>
<# if ( data ) { #>

	<ul class="{{ data.args.wrapper_class }} {{ data.args.class }}">

		<# if ( data.args.show_all ) { #>
			<li>
				<a href="#" data-group="all">
					<?php echo esc_html( $all_text ); ?>
				</a>
			</li>
		<# } #>

		<# for ( key in data.items ) { #>

			<# if ( data.items[ key ].count ) { #>

				<li>
					<a href="#" data-group='{{ data.items[ key ].id }}' class="{{ data.items[ key ].name }}">
						{{{ data.items[ key ].name }}}
					</a>
				</li>

			<# } #>

		<# } #>

	</ul>
<# } #>
