
(function($){

	AstraPortfolioAPI = {

		_api_url  : astraPortfolioApi.ApiURL,

		/**
		 * API Request
		 */
		_api_request: function( args ) {

			var pagination = ( 'undefined' !== typeof args.per_page && 'undefined' !== typeof args.page ) ? true : false;
			var is_page_available = false;

			$.ajax({
				dataType: 'json',
				url: AstraPortfolioAPI._api_url + args.slug,
				cache: false
			})
			.done(function( items, status, XHR ) {

				if( 'success' === status && XHR.getResponseHeader('x-wp-total') ) {

					if ( 'undefined' !== typeof args.class && ( 'astra-portfolio-other-categories' === args.class || 'astra-portfolio-categories' === args.class ) ) {

						// Iterate through each category in the response
						items.forEach( function( category ) {
							// Decode the category name and slug
							category.name = JSON.parse('"' + category.name + '"');
							category.slug = decodeURIComponent( category.slug );
						});
					}

					if( pagination ) {
						var num_posts = args.per_page * args.page;
						var total_posts = XHR.getResponseHeader('x-wp-total') || 0;
						is_page_available = num_posts < total_posts;
					}

					var data = {
						args 		: args,
						items 		: items,
						items_count	: XHR.getResponseHeader('x-wp-total') || 0,
						next_page	: is_page_available
					};

					if( 'undefined' !== args.trigger && '' !== args.trigger ) {
						$(document).trigger( args.trigger, [data] );
					}

				} else {
					$(document).trigger( 'astra-portfolio-api-request-error' );
				}

			})
			.fail(function( jqXHR, textStatus ) {

				$(document).trigger( 'astra-portfolio-api-request-fail', [args, jqXHR, textStatus] );

			})
			.always(function() {

				$(document).trigger( 'astra-portfolio-api-request-always', [args] );

			});

		},

	};

})(jQuery);