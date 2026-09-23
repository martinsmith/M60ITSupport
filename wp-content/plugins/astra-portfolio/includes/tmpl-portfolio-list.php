<?php
/**
 * Portfolio list
 *
 * @package Astra Portfolio
 * @since 1.0.6
 */

// Ensure $args is defined before using it.
$args = isset( $args ) ? $args : array(); // Default to an empty array if not defined.

?>
<# if ( data.items.length ) { #>
	<# for ( key in data.items ) {

		var item_classes = '';
		if( 1 == data.items[ key ]['astra-site-open-in-new-tab'] )
		{
			item_classes = ' open-in-new-tab ';
		}

		var open_in = data.items[ key ]['astra-site-open-portfolio-in'] || '';

		item_classes += ' ' + open_in + ' ';
		var style = '<?php echo esc_html( $args['data']['grid-style'] ); ?>';
		var speed = '<?php echo esc_html( $args['data']['scroll-speed'] ); ?>';
		#>

		<div class="site-single {{ item_classes }} <?php echo esc_attr( $args['column_class'] ); ?> {{ data.items[ key ]['portfolio-type'] }}" data-slug="{{ data.items[ key ]['slug'] }}" data-id="{{data.items[ key ]['id']}}" data-portfolio-type="{{data.items[ key ]['portfolio-type']}}">
			<div class="inner">
				<?php if ( 'top' === $args['data']['portfolio-title-loc'] ) { ?>
					<div class="template-meta">
						<div class="item-title">
							{{{ data.items[ key ].title.rendered }}}
							<# if ( data.items[ key ]['astra-site-type'] ) { #>
								<span class="site-type {{data.items[ key ]['astra-site-type']}}">{{data.items[ key ]['astra-site-type']}}</span>
							<# } #>
						</div>
					</div>
				<?php } ?>
				<#
				var css = '';
				if( 'style-1' == style && '' !== data.items[ key ]['thumbnail-image-url'] ) {
					css = "background-image:url('"+data.items[ key ]['thumbnail-image-url']+"'); ";
					if( '' !== speed ) {
						css += "transition: all ease-in-out " + speed + "s;";
					}
				}

				var type = data.items[ key ]['portfolio-type'] || '';

				switch( type ) {
					case 'page': 
									var permalink = data.items[ key ]['link'] || '';
									var target = '_blank';
									if( 'same-tab' == open_in )
									{
										target = '_self';
									}
									if( 'iframe' == open_in )
									{ #>
										<span class="site-preview" data-href="{{ permalink }}TB_iframe=true&width=600&height=550" data-title="{{ data.items[ key ].title.rendered }}" style="{{css}}">
									<# } else { #>
										<a target="{{ target }}" class="site-preview" title="{{ data.items[ key ].title.rendered }}" href="{{{permalink}}}" data-elementor-open-lightbox="no" style="{{css}}">
									<# } #>
										<# if( 'style-1' !== style && '' !== data.items[ key ]['thumbnail-image-url'] ) { #>
											<img class="lazy" data-src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											<noscript>
												<img src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											</noscript>
										<# } #>
										<?php if ( 'yes' === $args['data']['show-quick-view'] ) { ?>
										<span class="view-demo-wrap">
											<span class="view-demo"> <?php echo esc_html( $args['data']['quick-view-text'] ); ?> </span>
										</span>
										<?php } ?>
									<# if( 'iframe' == open_in )
									{ #>
										</span>
									<# } else { #>
										</a>
									<# }
						break;
					case 'video': 
									var video_url = data.items[ key ]['portfolio-video-url'] || '';
									#>										
									<a class="site-preview" title="{{ data.items[ key ].title.rendered }}" href="{{{video_url}}}" data-elementor-open-lightbox="no" style="{{css}}">
										<# if( 'style-1' !== style && '' !== data.items[ key ]['thumbnail-image-url'] ) { #>
											<img class="lazy" data-src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											<noscript>
												<img src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											</noscript>
										<# } #>
										<?php if ( 'yes' === $args['data']['show-quick-view'] ) { ?>
										<span class="view-demo-wrap">
											<span class="view-demo"> <?php echo esc_html( $args['data']['quick-view-text'] ); ?> </span>
										</span>
										<?php } ?>
									</a>
									<#
						break;
					case 'image': 
									var image_url = data.items[ key ]['lightbox-image-url'] || '';
									if( '' === image_url ) {
										image_url = data.items[ key ]['thumbnail-image-url'] || '';
									}
									#>
									<a class="site-preview" title="{{ data.items[ key ].title.rendered }}" href="{{{image_url}}}" data-elementor-open-lightbox="no" style="{{css}}">
										<# if( 'style-1' !== style && '' !== data.items[ key ]['thumbnail-image-url'] ) { #>
											<img class="lazy" data-src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											<noscript>
												<img src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											</noscript>
										<# } #>
										<?php if ( 'yes' === $args['data']['show-quick-view'] ) { ?>
										<span class="view-demo-wrap">
											<span class="view-demo"> <?php echo esc_html( $args['data']['quick-view-text'] ); ?> </span>
										</span>
										<?php } ?>
									</a>
									<#
						break;

					case 'iframe': 
					default:

									if( 1 == data.items[ key ]['astra-site-open-in-new-tab'] ) { #>
										<a class="site-preview" href="{{ data.items[ key ]['astra-site-url'] }}" target="_blank" data-title="{{ data.items[ key ].title.rendered }}" data-elementor-open-lightbox="no" style="{{css}}" aria-label="Visit {{ data.items[key].title.rendered }} website">
									<# } else { #>
										<span class="site-preview" data-href="{{ data.items[ key ]['astra-site-url'] }}TB_iframe=true&width=600&height=550" data-title="{{ data.items[ key ].title.rendered }}" style="{{css}}" aria-label="Visit {{ data.items[key].title.rendered }} website">
									<# } #>
										<# if( 'style-1' !== style && '' !== data.items[ key ]['thumbnail-image-url'] ) { #>
											<img class="lazy" data-src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											<noscript>
												<img src="{{ data.items[ key ]['thumbnail-image-url'] }}" alt="{{ data.items[ key ]['thumbnail-image-meta']['alt'] }}" title="{{ data.items[ key ]['thumbnail-image-meta']['title'] }}" />
											</noscript>
										<# } #>
										<?php if ( 'yes' === $args['data']['show-quick-view'] ) { ?>
										<span class="view-demo-wrap">
											<span class="view-demo"> <?php echo esc_html( $args['data']['quick-view-text'] ); ?> </span>
										</span>
										<?php } ?>
									<# if( 1 == data.items[ key ]['astra-site-open-in-new-tab'] ) { #>
										</a>
									<# } else { #>
										</span>
									<# }
						break;
				}
				#>
				<?php if ( 'bottom' === $args['data']['portfolio-title-loc'] ) { ?>
					<div class="template-meta">
						<div class="item-title">
							{{{ data.items[ key ].title.rendered }}}
							<# if ( data.items[ key ]['astra-site-type'] ) { #>
								<span class="site-type {{data.items[ key ]['astra-site-type']}}">{{data.items[ key ]['astra-site-type']}}</span>
							<# } #>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>
	<# } #>
<# } else { #>
	<div class="astra-portfolio-not-found">
		<p>
			<?php esc_html_e( 'No items found.', 'astra-portfolio' ); ?><br/>
		</p>
	</div>
<# } #>
