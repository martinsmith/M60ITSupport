
(function($) {

	AstraPortfolio = {

		_ref			: null,

		/**
		 * _api_params = {
		 * 		'search'                  : '',
		 * 		'per_page'                : '',
		 * 		'astra-portfolio-categories'     : '',
		 * 		'astra-portfolio-other-categories' : '',
		 * 		'page'                    : '',
		 *   };
		 *
		 * E.g. per_page=<page-id>&astra-portfolio-categories=<category-ids>&astra-portfolio-other-categories=<page-builder-ids>&page=<page>
		 */
		_api_params           : {},
		_breakpoint           : 768,
		_iconUploader         : null,
		items                 : [],
		nextPage    		  : null,
	
		init: function()
		{
			this._shwoLoader();
			this._showSiteOnLoad();
			this._masonry();
			this._resetPagedCount();
			this._bind();
			this._display();
			this._handleBackButton();
		},

		_handleBackButton: function() 
		{
			// Always set up the popstate listener
			window.addEventListener('popstate', function(event) {
				var portfolioOpen = $("#TB_window");
				if( portfolioOpen.length && portfolioOpen.hasClass('astra-portfolio') ) {
					tb_remove();
				}				
			});
		},

		_shwoLoader: function()
		{
			if( ! $( '#astra-portfolio .spinner' ).length ) {
				$( '#astra-portfolio' ).append( wp.template('astra-portfolio-spinner') );
			}
		},

		/**
		 * Show Site On Load.
		 *
		 * @since 1.0.2
		 */
		_showSiteOnLoad: function()
		{
			if( AstraPortfolio._getParamFromURL('portfolio') )
			{
				var slug      = AstraPortfolio._getParamFromURL('portfolio');

				var api_params = {
					slug : slug,
				};

				// API Request.
				var api_url = astraPortfolio.apiEndpoint + 'astra-portfolio?' + decodeURIComponent( $.param( api_params ) );
				$.ajax({
					url   : api_url,
					cache : false
				})
				.done(function( items, status, XHR ) {

					if( 'success' === status && items.length && items[0] ) {

						// Add to temporary object.
						$.each(items, function(index, val) {
							AstraPortfolio.items[ val.id ] = val;
						});

						var post_id        = ( 'id' in items[0] ) ? items[0]['id'] : '';
						var portfolio_type = ( 'portfolio-type' in items[0] ) ? items[0]['portfolio-type'] : '';
						var site_url       = ( 'astra-site-url' in items[0] ) ? items[0]['astra-site-url'] + 'TB_iframe=true&width=600&height=550' : '';
						var title          = ( 'title' in items[0] ) ? items[0]['title']['rendered'] : '';
						var rel            = false;

						switch( portfolio_type ) {
							case 'image':
											var lightbox_url = ( 'lightbox-image-url' in items[0] ) ? items[0]['lightbox-image-url'] : '';
											jQuery.magnificPopup.open({
												items: {
													src: lightbox_url
												},
												type: 'image'
											}, 0);
								break;
							case 'video':
											var video_url = ( 'portfolio-video-url' in items[0] ) ? items[0]['portfolio-video-url'] : '';
											jQuery.magnificPopup.open({
												items: {
													src: video_url
												},
												type: 'iframe'
											}, 0);
								break;
							case 'iframe':
										if( title && site_url )
										{
											var site_data = {
												'title'          : title,
												'href'           : site_url,
												'rel'            : rel,
												'slug'           : slug,
												'post_id'        : post_id,
												'portfolio_type' : portfolio_type,
											}

											AstraPortfolio._showSingleSite( site_data );
										}
								break;
						}
					}
				});
			} 
		},

		/**
		 * Preview Open
		 * 
		 * @param  {object} event Object.
		 */
		_showSingleSite: function( data )
		{
			var title          = data.title || '';
			var href           = data.href || '';
			var rel            = data.rel || '';
			var slug           = data.slug || '';
			var post_id        = data.post_id || '';
			var portfolio_type = data.portfolio_type || '';

			if( href )
			{
				$('html').addClass('processing');

				var location = astraPortfolio.settings["preview-bar-loc"] || 'bottom';

				tb_show( title, href, rel );

				var currentSiteTitle = $("#TB_ajaxWindowTitle").text();

				if( undefined !== post_id && undefined !== AstraPortfolio.items[ post_id ] ) {
					var call_to_action = AstraPortfolio.items[ post_id ]['astra-site-call-to-action'] || '';
					if( call_to_action ) {
						$('#TB_ajaxWindowTitle').after( '<div class="astra-call-to-action">' + call_to_action + '</div>' );
					}
					
					setTimeout(function() {
						AstraPortfolio._setCallToAction();
					}, 100);
				}

				var responseive_buttons = astraPortfolio.settings['responsive-button'] || false;
				if( responseive_buttons ) {
					$('#TB_closeAjaxWindow').prepend( wp.template('astra-portfolio-responsive-view') );
				}
		
				$('#TB_iframeContent').wrap('<div id="TB_iframeContent-wrapper"></div>');
		
				$('#TB_window')
					.addClass( location )
					.addClass('astra-slug-' + slug )
					.addClass('astra-site-id-' + post_id )
					.addClass('astra-portfolio-type-' + portfolio_type )
					.addClass('astra-portfolio' )
					.addClass('desktop');
				
				if( astraPortfolio.siteLoadingEnabled ) {
					$('#TB_window')
						.addClass('astra-portfolio-thickbox-loading')
						.append('<div class="site-loading"><h3>'+astraPortfolio.siteLoadingTitle+'</h3><p>'+astraPortfolio.siteLoadingMessage+'</p></div>');
				}
			}
		},

		/**
		 * Set call to action button.
		 */
		_setCallToAction: function()
		{
			if( ! $( '.astra-call-to-action' ).length ) {
				return;
			}

			var windowHeight = $( window ).outerWidth();
			if( windowHeight <= 768 ) {
				var ctaHeight = $( '.astra-call-to-action' ).outerHeight();
				if( $( '#TB_window' ).hasClass('top') ) {
					$('#TB_iframeContent-wrapper').css( 'top', (ctaHeight + 100) );
				} else {
					$('#TB_iframeContent-wrapper').css( 'bottom', (ctaHeight + 100) );
				}				
				$('#TB_window #TB_title').css( 'height', (ctaHeight + 100) );
			} else {
				$('#TB_iframeContent-wrapper').css( 'bottom', '' );
				$('#TB_iframeContent-wrapper').css( 'top', '' );
				$('#TB_window #TB_title').css( 'height', '' );
			}
		},

		/**
		 * Get URL param.
		 */
		_getParamFromURL: function(name, url)
		{
		    if (!url) url = window.location.href;
		    name = name.replace(/[\[\]]/g, "\\$&");
		    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
		        results = regex.exec(url);
		    if (!results) return null;
		    if (!results[2]) return '';
		    return decodeURIComponent(results[2].replace(/\+/g, " "));
		},

		_display: function() {

			// Show portfolios, If Category (disabled), Other Category(disabled) & Search(enabled).
			if( 'no' === astraPortfolio.settings['show-other-categories'] &&
				'no' === astraPortfolio.settings['show-categories'] &&
				'yes' === astraPortfolio.settings['show-search']
			) {
				AstraPortfolio._showSites();
			}

			// Show portfolios, If Category (disabled), Other Category(disabled) & Search(disabled).
			if( ! $('.astra-portfolio-filters').length ) {
				AstraPortfolio._showSites();
				return;
			}

			/**
			 * Categories
			 */
			if( 'yes' === astraPortfolio.settings['show-categories'] ) {
				var category_data = 'categories';
				var category_slug = 'astra-portfolio-categories';
				var category = {
					slug          : category_slug + AstraPortfolio._getCategoryParams( category_data ),
					id            : category_slug + '-wrap',
					class         : category_slug,
					trigger       : 'astra-api-all-category-loaded',
					wrapper_class : 'filter-links',
					show_all      : AstraPortfolio._getCategoryAllSelectStatus(),
				};
				AstraPortfolioAPI._api_request( category );
			}

			/**
			 * Other Categories
			 */
			if( 'yes' === astraPortfolio.settings['show-other-categories'] ) {
				var category_data = 'other-categories';
				var category_slug = 'astra-portfolio-other-categories';
				var category = {
					slug          : category_slug + AstraPortfolio._getCategoryParams( category_data ),
					id            : category_slug + '-wrap',
					class         : category_slug,
					trigger       : 'astra-api-category-loaded',
					wrapper_class : 'filter-links',
					show_all      : AstraPortfolio._getOtherCategoryAllSelectStatus(),
				};

				AstraPortfolioAPI._api_request( category );
			}
		},
		
		/**
		 * Binds events for the Astra Portfolio.
		 *
		 * @since 1.0.0
		 * @access private
		 * @method _bind
		 */
		_bind: function()
		{
			$( window ).on('resize', 									AstraPortfolio._resize );

			$( document ).on('astra-portfolio-api-request-fail',		AstraPortfolio._apiFailed );
			$( document ).on('astra-api-post-loaded-on-scroll',			AstraPortfolio._reinitGridScrolled );
			$( document ).on('astra-api-post-loaded', 					AstraPortfolio._reinitGrid );
			$( document ).on('astra-api-category-loaded', 				AstraPortfolio._addFilters );
			$( document ).on('astra-api-all-category-loaded', 			AstraPortfolio._loadFirstGrid );

			$( document ).on('click', '.iframe:not(.open-in-new-tab) .site-preview', 			AstraPortfolio._previewOpen );
			$( document ).on('click', '.actions a', 					AstraPortfolio._previewResponsive );
			$( 'body' ).on('thickbox:removed', 							AstraPortfolio._previewClose );
			$( 'body' ).on('thickbox:iframe:loaded', 					AstraPortfolio._previewLoaded );

			// Event's for API request.
			$( document ).on('keyup input', '.astra-portfolio-search', 	AstraPortfolio._search );
			$( document ).on('click', '.filter-links a', 				AstraPortfolio._filterClick );

			if( 'click' === astraPortfolio.settings["show-portfolio-on"] ) {
				$( document ).on('click', '.astra-portfolio-load-more-sites', AstraPortfolio._next_page );
			} else {
				$( document ).on('scroll', 									AstraPortfolio._scroll );
			}

		},

		/**
		 * Remove thickbox loading class
		 * 
		 * @param  object event Event object.
		 * @return void.
		 */
		_previewLoaded: function( event ) {
			event.preventDefault();
			$('#TB_window').removeClass('astra-portfolio-thickbox-loading');
		},

		/**
		 * Lightbox init.
		 */
		_lightboxInit: function( ) {

			$('.site-single.image').magnificPopup({
				delegate: 'a',
				type: 'image',
				tLoading: 'Loading image #%curr%...',
				mainClass: 'astra-portfolio-lightbox mfp-img-mobile',
				gallery: {
					enabled: true,
					navigateByImgClick: true,
					preload: [0,1] // Will preload 0 - before current, and 1 after the current image
				},
				image: {
					verticalFit: true,
					tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
					titleSrc: function(item) {
						return item.el.attr('title');
					}
				},
				callbacks: {
					change: function( item ) {
						var slug = $( item.el ).parents('.site-single').attr('data-slug') || '';
						if( slug ) {

							AstraPortfolio._clean_url_params( 'portfolio' );

							var url_params = {
								'portfolio' : slug
							};

							// Change URL.
							if( ! AstraPortfolio._getParamFromURL('portfolio') ) {
								var current_url = window.location.href;
								var current_url_separator = ( window.location.href.indexOf( "?" ) === -1 ) ? "?" : "&";

								var new_url = current_url + current_url_separator + decodeURIComponent( $.param( url_params ) );
								AstraPortfolio._changeURL( new_url );
							}
						}
					},
					open: function( ) {
						var slug = $( this.currItem.el ).parents('.site-single').attr('data-slug') || '';
						if( slug ) {
							var url_params = {
								'portfolio' : slug
							};

							// Change URL.
							if( ! AstraPortfolio._getParamFromURL('portfolio') ) {
								var current_url = window.location.href;
								var current_url_separator = ( window.location.href.indexOf( "?" ) === -1 ) ? "?" : "&";

								var new_url = current_url + current_url_separator + decodeURIComponent( $.param( url_params ) );
								AstraPortfolio._changeURL( new_url );
							}
						}
					},
					close: function( ) {
						AstraPortfolio._clean_url_params( 'portfolio' );
					}
				}
			});

			$('.site-single.video').magnificPopup({
				delegate: 'a',
				disableOn: 700,
				type: 'iframe',
				mainClass: 'astra-portfolio-popup mfp-fade',
				removalDelay: 160,
				preloader: false,

				fixedContentPos: false,
				callbacks: {
					open: function( ) {
						var slug = $( this.currItem.el ).parents('.site-single').attr('data-slug') || '';
						if( slug ) {
							var url_params = {
								'portfolio' : slug
							};

							// Change URL.
							if( ! AstraPortfolio._getParamFromURL('portfolio') ) {
								var current_url = window.location.href;
								var current_url_separator = ( window.location.href.indexOf( "?" ) === -1 ) ? "?" : "&";

								var new_url = current_url + current_url_separator + decodeURIComponent( $.param( url_params ) );
								AstraPortfolio._changeURL( new_url );
							}
						}
					},
					close: function( ) {
						AstraPortfolio._clean_url_params( 'portfolio' );
					}
				}
			});

		},

		_clean_url_params: function( single_param )
		{
			var url_params = AstraPortfolio._getQueryStrings();
			delete url_params[ single_param ];
			delete url_params[''];		// Removed extra empty object.

			var current_url = window.location.href;
			var root_url = current_url.substr(0, current_url.indexOf('?')); 
			if( $.isEmptyObject( url_params ) ) {
				var new_url = root_url + decodeURIComponent( $.param( url_params ) );
			} else {
				var current_url_separator = ( root_url.indexOf( "?" ) === -1 ) ? "?" : "&";
				var new_url = root_url + current_url_separator + decodeURIComponent( $.param( url_params ) );
			}

			AstraPortfolio._changeURL( new_url );
		},

		/**
		 * Responsive On Click.
		 */
		_previewResponsive: function( event ) {

			event.preventDefault();

			var icon = $(this).find('.dashicons');

			var viewClass = icon.attr('data-view') || '';

			$('#TB_window').removeClass( 'desktop' );
			$('#TB_window').removeClass( 'tablet' );
			$('#TB_window').removeClass( 'mobile' );
			$('#TB_window').addClass( viewClass );

			$('.actions .dashicons').removeClass('active');
			icon.addClass('active');

			$('#TB_iframeContent').removeClass();
			$('#TB_iframeContent').addClass( viewClass );

		},

		/**
		 * On Filter Clicked
		 */
		_filterClick: function( event ) {

			event.preventDefault();

			$(this).parents('.filter-links').find('a').removeClass('active');
			$(this).addClass('active');

			// Clean data before process request.
			$('.astra-portfolio').addClass('hide-me');

			$( 'body' ).removeClass('astra-portfolio-loaded-all-sites');

			if( ! $( '#astra-portfolio .spinner' ).length ) {
				$( '#astra-portfolio' ).append( wp.template('astra-portfolio-spinner') );
			}
			if( $( '#astra-portfolio .astra-portfolio-load-more-sites' ).length ) {
				$( '#astra-portfolio .astra-portfolio-load-more-sites' ).remove();
			}
			if( $( '#astra-portfolio .astra-portfolio-not-found' ).length ) {
				$( '#astra-portfolio .astra-portfolio-not-found' ).remove();
			}
			if( $( '#astra-portfolio .no-more-demos' ).length ) {
				$( '#astra-portfolio .no-more-demos').remove();
			}

			$('.astra-portfolio-search').val('');
			$('body').addClass( 'astra-portfolio-loading' );

	        // Show sites.
			AstraPortfolio._showSites();

			if( astraPortfolio.scrollToTop ) {
				if( $('.filters-wrap').length ) {
					
					$('html, body').animate({
						scrollTop: $('.filters-wrap').offset().top - 100
					});
				}
			}
		},

		/**
		 * On Resize
		 */
		_resize: function() {

			AstraPortfolio._masonry();

			AstraPortfolio._setCallToAction();
		},

		/**
		 * Preview Close
		 * 
		 * @param  {object} event Object.
		 */
		_previewClose: function( event )
		{
			event.preventDefault();
			$('html').removeClass('processing');

			var url_params = AstraPortfolio._getQueryStrings();
			delete url_params['portfolio'];

			var current_url = window.location.href;
			var root_url = current_url.substr(0, current_url.indexOf('?')); 
			if( $.isEmptyObject( url_params ) ) {
				var new_url = root_url + decodeURIComponent( $.param( url_params ) );
			} else {
				var current_url_separator = ( root_url.indexOf( "?" ) === -1 ) ? "?" : "&";
				var new_url = root_url + current_url_separator + decodeURIComponent( $.param( url_params ) );
			}

			// Change URL.
			AstraPortfolio._changeURL( new_url );
		},

		/**
		 * Preview Open
		 * 
		 * @param  {object} event Object.
		 */
		_previewOpen: function( event ) {
			event.preventDefault();
			
			// Site Preview.
			var title 			= $(this).data('title') || $(this).data('name') || null,
				href  			= $(this).data('href') || $(this).data('alt'),
				rel   			= $(this).data('rel') || false,
				slug  			= $(this).parents('.site-single').attr('data-slug') || '';
				id    			= $(this).parents('.site-single').attr('data-id') || '';
				portfolio_type  = $(this).parents('.site-single').attr('data-portfolio-type') || '';

			var site_data = {
				'title'          : title,
				'href'           : href,
				'rel'            : rel,
				'slug'           : slug,
				'post_id'        : id,
				'portfolio_type' : portfolio_type,
			}

			AstraPortfolio._showSingleSite( site_data );

			var url_params = {
				'portfolio' : slug
			};

			// Change URL.
			if( ! AstraPortfolio._getParamFromURL('portfolio') ) {
				var current_url = window.location.href;
				var current_url_separator = ( window.location.href.indexOf( "?" ) === -1 ) ? "?" : "&";

				var new_url = current_url + current_url_separator + decodeURIComponent( $.param( url_params ) );
				
				AstraPortfolio._changeURL( new_url );
			}
		},

		/**
		 * Get query strings.
		 * 
		 * @param  string string Query string.
		 * @return string     	 Check and return query string.
		 */
		_getQueryStrings: function( string )
		{
			return ( string || document.location.search).replace(/(^\?)/,'').split("&").map(function(n){return n = n.split("="),this[n[0]] = n[1],this}.bind({}))[0];
		},

		/**
		 * Clean the URL.
		 * 
		 * @param  string url URL string.
		 * @return string     Change the current URL.
		 */
		_changeURL: function( url )
		{
			History.pushState(null, null, url);
		},

		/**
		 * Lazy Load Images
		 *
		 * @see  http://jquery.eisbehr.de/lazy/#features
		 */
		_lazyLoad: function()
		{
			$('.astra-portfolio img').Lazy({
				effect: 'fadeIn',
				onFinishedAll: function() {
					if( AstraPortfolio._process_masonry() ) {
						$('.astra-portfolio').masonry('reloadItems');
						$('.astra-portfolio').masonry('layout');
					}
				}
			});
		},

		/**
		 * Init Masonry.
		 * 
		 * @see  /wp-includes/js/jquery/jquery.masonry.min.js (Source http://masonry.desandro.com).
		 */
		_masonry: function() {
			if( AstraPortfolio._process_masonry() ) {
				$('.astra-portfolio').masonry({
					horizontalOrder : false,
					percentPosition : false
				});
			}
		},

		/**
		 * Process Masonry
		 *
		 * @since 1.6.0
		 * 
		 * @return {boolean}
		 */
		_process_masonry: function() {
			var windowHeight = $( window ).outerWidth();
			if( astraPortfolio.settings['enable-masonry'] && ( windowHeight >= 768 ) ) {
				return true;
			}

			return false;
		},

		// Add 'search'
		_apiAddParam_search: function() {
			var search_val = $('.astra-portfolio-search').val() || '';
			if( '' !== search_val ) {
				AstraPortfolio._api_params['search'] = search_val;
			}
		},

		_apiAddParam_per_page: function() {
			// Add 'per_page'
			var per_page_val = 3;
			if( astraPortfolio.settings && astraPortfolio.settings["per-page"] ) {
				per_page_val = parseInt( astraPortfolio.settings["per-page"] );
			}
			AstraPortfolio._api_params['per_page'] = per_page_val;
		},

		_apiAddParam_astra_site_tags: function() {
			// Add 'astra-portfolio-tags'
			var tags = '' + $('.astra-portfolio-wrap').data( 'tags' ) || '';
			if( '' !== tags ) {
				if ( tags.indexOf(',') > -1) {
					tags = tags.replace(/^,|,$/g,'');;
				}

				AstraPortfolio._api_params['astra-portfolio-tags'] =  tags;
			}
		},

		_apiAddParam_astra_site_category: function()
		{
			if( $('.filter-links.astra-portfolio-categories').length ) {
				var selected_category_id = $('.filter-links.astra-portfolio-categories').find('.active').data('group') || '';
				if( '' !== selected_category_id && 'all' !== selected_category_id ) {
					AstraPortfolio._api_params['astra-portfolio-categories'] =  selected_category_id;
				} else {
					var categories = $('.astra-portfolio-wrap').data( 'categories' );
					if( '' !== categories ) {
						AstraPortfolio._api_params['astra-portfolio-categories'] =  categories;
					}
				}

			} else {
				
				var tags = '' + $('.astra-portfolio-wrap').data( 'categories' ) || '';
				if( '' !== tags ) {
					if ( tags.indexOf(',') > -1) {
						tags = tags.replace(/^,|,$/g,'');;
					}

					AstraPortfolio._api_params['astra-portfolio-categories'] =  tags;
				}
			}
		},

		_apiAddParam_astra_site_page_builder: function() {

			if( $('.filter-links.astra-portfolio-other-categories').length ) {
				var selected_category_id = $('.filter-links.astra-portfolio-other-categories').find('.active').data('group') || '';
				if( '' !== selected_category_id && 'all' !== selected_category_id ) {
					AstraPortfolio._api_params['astra-portfolio-other-categories'] =  selected_category_id;
				}

			} else {
				
				var tags = '' + $('.astra-portfolio-wrap').data( 'other-categories' ) || '';
				if( '' !== tags ) {
					if ( tags.indexOf(',') > -1) {
						tags = tags.replace(/^,|,$/g,'');;
					}

					AstraPortfolio._api_params['astra-portfolio-other-categories'] =  tags;
				}
			}
		},

		_apiAddParam_page: function() {
			// Add 'page'
			var page_val = parseInt($('body').attr('data-astra-demo-paged')) || 1;
			AstraPortfolio._api_params['page'] = page_val;
		},

		/**
		 * Show Sites
		 * 
		 * 	Params E.g. per_page=<page-id>&astra-portfolio-categories=<category-ids>&astra-portfolio-other-categories=<page-builder-ids>&page=<page>
		 *
		 * @param  {Boolean} resetPagedCount Reset Paged Count.
		 * @param  {String}  trigger         Filtered Trigger.
		 */
		_showSites: function( resetPagedCount, trigger ) {

			if( undefined === resetPagedCount ) {
				resetPagedCount = true
			}

			if( undefined === trigger ) {
				trigger = 'astra-api-post-loaded';
			}

			if( resetPagedCount ) {
				AstraPortfolio._resetPagedCount();
			}

			// Add Params for API request.
			AstraPortfolio._api_params = {};

			AstraPortfolio._apiAddParam_search();
			AstraPortfolio._apiAddParam_per_page();
			AstraPortfolio._apiAddParam_astra_site_tags( );
			AstraPortfolio._apiAddParam_astra_site_category();
			AstraPortfolio._apiAddParam_astra_site_page_builder();
			AstraPortfolio._apiAddParam_page();

			// API Request.
			var api_post = {
				slug: 'astra-portfolio' + astraPortfolio.ApiURLSep + decodeURIComponent( $.param( AstraPortfolio._api_params ) ),
				trigger: trigger,
				per_page: AstraPortfolio._api_params['per_page'],
				page: AstraPortfolio._api_params['page']
			};

			AstraPortfolioAPI._api_request( api_post );

		},

		/**
		 * Get Category Params
		 * 
		 * @param  {string} category_data Category Slug.
		 * @return {mixed}               Add `include=<category-ids>` in API request.
		 */
		_getCategoryParams: function( category_data ) {

			// Con-cat to convert number into string.
			var categories = '' + $('.astra-portfolio-wrap').data( category_data ) || '';

			var wpml_lang = astraPortfolio.current_lang ? '&lang=' + astraPortfolio.current_lang : '';

			if( categories ) {
				if ( categories.indexOf(',') > -1) {
					return astraPortfolio.ApiURLSep + 'per_page=100&include='+categories.replace(/^,|,$/g,'') + wpml_lang;
				} else {
					return astraPortfolio.ApiURLSep + 'per_page=100&include='+categories + wpml_lang;
				}
			}

			return astraPortfolio.ApiURLSep + 'per_page=100'  + wpml_lang;
		},

		/**
		 * Get All Select Status
		 * 
		 * @return {boolean}              Return true/false.
		 */
		_getCategoryAllSelectStatus: function( ) {

			// Has category?
			if( 'category-show-all' in astraPortfolio.settings ) {
				if( 'yes' === astraPortfolio.settings['category-show-all'] ) {
					return true;
				}
			}

			return false;
		},

		/**
		 * Get All Select Status
		 * 
		 * @return {boolean}              Return true/false.
		 */
		_getOtherCategoryAllSelectStatus: function( ) {

			// Has category?
			if( 'other-category-show-all' in astraPortfolio.settings ) {
				if( 'yes' === astraPortfolio.settings['other-category-show-all'] ) {
					return true;
				}
			}

			return false;
		},

		/**
		 * Load First Grid.
		 *
		 * This is triggered after all category loaded.
		 * 
		 * @param  {object} event Event Object.
		 */
		_loadFirstGrid: function( event, data ) {

			AstraPortfolio._addFilters( event, data );
			setTimeout(function() {
				AstraPortfolio._showSites();
			}, 100);
		},

		/**
		 * Append filters.
		 * 
		 * @param  {object} event Object.
		 * @param  {object} data  API response data.
		 */
		_addFilters: function( event, data ) {
			event.preventDefault();

			// Show portfolios, If Category (disabled), Other Category(enabled) & Search(enabled/disabled).
			if( ! astraPortfolio.settings['categories'] ) {
				setTimeout(function() {
					AstraPortfolio._showSites( );
				}, 100);
			}

			if( $('.' + data.args.id).length ) {
				var template = wp.template('astra-portfolio-filters');
				$('.' + data.args.id).html(template( data )).find('li:first a').addClass('active');
			}

		},

		/**
		 * Append sites on scroll.
		 * 
		 * @param  {object} event Object.
		 * @param  {object} data  API response data.
		 */
		_reinitGridScrolled: function( event, data ) {

			var template = wp.template('astra-portfolio-list');

			if( data.items_count > 0 ) {
				$('body').addClass('astra-portfolio-has-items').removeClass('astra-portfolio-not-has-items');
			} else {
				$('body').removeClass('astra-portfolio-has-items').addClass('astra-portfolio-not-has-items');
			}

			$('.astra-portfolio').removeClass('hide-me');

			if( data.items.length > 0 ) {

				// Add to temporary object.
				$.each(data.items, function(index, val) {
					AstraPortfolio.items[ val.id ] = val;
				});

				$('.filter-count .count').text( data.items_count );

				setTimeout(function() {
					$('.astra-portfolio').append(template( data ));
					if( 'default' === astraPortfolio.settings["grid-style"] ) {
						AstraPortfolio._lazyLoad();
						AstraPortfolio._imagesLoaded( data );
					} else {
						AstraPortfolio.processed( data );
					}
				}, 800);
			} else {

				if( $( '#astra-portfolio .spinner' ).length ) {
					$( '#astra-portfolio .spinner').remove();
				}

				if( ! $( '#astra-portfolio .no-more-demos' ).length ) {
					$( '#astra-portfolio' ).append( wp.template('astra-portfolio-no-more-demos') );
				}

				if( $( '#astra-portfolio .astra-portfolio-not-found' ).length ) {
					$( '#astra-portfolio .astra-portfolio-not-found' ).remove();
				}

			}
		},

		/**
		 * Update Astra sites list.
		 * 
		 * @param  {object} event Object.
		 * @param  {object} data  API response data.
		 */
		_reinitGrid: function( event, data ) {

			if( data.items_count > 0 ) {
				$('body').addClass('astra-portfolio-has-items').removeClass('astra-portfolio-not-has-items');
			} else {
				$('body').removeClass('astra-portfolio-has-items').addClass('astra-portfolio-not-has-items');
			}

			$('.astra-portfolio').removeClass('hide-me');

			if( data.items.length > 0 ) {

				// Add to temporary object.
				$.each(data.items, function(index, val) {
					AstraPortfolio.items[ val.id ] = val;
				});

				var template = wp.template('astra-portfolio-list');

				$('.filter-count .count').text( data.items_count );
				$('body').attr('data-astra-demo-last-request', data.items_count);

				$('.astra-portfolio-shortcode-wrap').html(template( data ));
				if( 'default' === astraPortfolio.settings["grid-style"] ) {
					AstraPortfolio._lazyLoad();
					AstraPortfolio._imagesLoaded( data );
				} else {
					AstraPortfolio.processed( data );
				}
			} else {
				
				if( $( '#astra-portfolio .spinner' ).length ) {
					$( '#astra-portfolio .spinner' ).remove();
				}

				$( '.astra-portfolio-shortcode-wrap' ).html( wp.template('astra-portfolio-not-found') );
			}
		},

		/**
		 * Check image loaded with function `imagesLoaded()`
		 */
		_imagesLoaded: function( data ) {

			$('.astra-portfolio-grid').imagesLoaded()
			.always( function( instance ) {
				AstraPortfolio.processed( data );
			})
			.progress( function( instance, image ) {
				var result = image.isLoaded ? 'loaded' : 'broken';
			});
		},

		processed: function( data ) {
			
			$('.astra-portfolio').removeClass('hide-me');
			if( AstraPortfolio._process_masonry() ) {
				$('.astra-portfolio').masonry('reloadItems');
				$('.astra-portfolio').masonry('layout');
			}
			
			if( $( '#astra-portfolio .spinner' ).length ) {
				$( '#astra-portfolio .spinner' ).remove();
			}
			AstraPortfolio._lightboxInit();

			if( ('click' === astraPortfolio.settings["show-portfolio-on"] && ! $( '#astra-portfolio .astra-portfolio-load-more-sites' ).length) && data.next_page ) {
				$( '#astra-portfolio' ).append( wp.template('astra-portfolio-load-more-sites') );
			}

			$('body').removeClass( 'astra-portfolio-loading' );
		},

		/**
		 * API Request Failed/Not found any demos.
		 */
		_apiFailed: function(e, args, jqXHR, textStatus) {

			var status = jqXHR.status || 0;

			if( $( '#astra-portfolio .spinner' ).length ) {
				$( '#astra-portfolio .spinner' ).remove();
			}

			if( $('.astra-portfolio .site-single').length ) {
				if( ! $( '#astra-portfolio .no-more-demos' ).length ) {
					$( '#astra-portfolio' ).append( wp.template('astra-portfolio-no-more-demos') );
				}
			} else {
				if( ! $( '#astra-portfolio .astra-portfolio-not-found' ).length ) {
					$( '#astra-portfolio' ).append( wp.template('astra-portfolio-not-found') );
				}
			}

			$( 'body' ).addClass('astra-portfolio-loaded-all-sites');
		},

		/**
		 * Search Site.
		 */
		_search: function() {
					
			$this = $('.astra-portfolio-search').val();

			$('.filter-links.astra-portfolio-categories a').removeClass('active');
			$( 'body' ).removeClass('astra-portfolio-loaded-all-sites');

			// Clean data before process request.
			$('.astra-portfolio').addClass('hide-me');
			if( ! $( '#astra-portfolio .spinner' ).length ) {
				$( '#astra-portfolio' ).append( wp.template('astra-portfolio-spinner') );
			}
			if( $( '#astra-portfolio .astra-portfolio-load-more-sites' ).length ) {
				$( '#astra-portfolio .astra-portfolio-load-more-sites' ).remove();
			}
			if( $( '#astra-portfolio .astra-portfolio-not-found' ).length ) {
				$( '#astra-portfolio .astra-portfolio-not-found').remove();
			}
			if( $( '#astra-portfolio .no-more-demos' ).length ) {
				$( '#astra-portfolio .no-more-demos').remove();
			}

			$('body').addClass( 'astra-portfolio-loading' );

			window.clearTimeout(AstraPortfolio._ref);
			AstraPortfolio._ref = window.setTimeout(function () {
				AstraPortfolio._ref = null;

				$('body').addClass('astra-portfolio-loading');
				$('body').attr('data-astra-demo-search', $this);

				AstraPortfolio._showSites();

			}, 500);

		},

		_next_page: function(event) {

			AstraPortfolio._updatedPagedCount();

			$( 'body' ).removeClass('astra-portfolio-loaded-all-sites');

			$('body').addClass( 'astra-portfolio-loading' );

			if( ! $( '#astra-portfolio .spinner' ).length ) {
				$( '#astra-portfolio' ).append( wp.template('astra-portfolio-spinner') );
			}
			if( $( '#astra-portfolio .astra-portfolio-load-more-sites' ).length ) {
				$( '#astra-portfolio .astra-portfolio-load-more-sites' ).remove();
			}
			if( $( '#astra-portfolio .astra-portfolio-not-found' ).length ) {
				$( '#astra-portfolio .astra-portfolio-not-found' ).remove();
			}
			if( $( '#astra-portfolio .no-more-demos' ).length ) {
				$( '#astra-portfolio .no-more-demos').remove();
			}
			
			/**
			 * @see _reinitGridScrolled() which called in trigger 'astra-api-post-loaded-on-scroll'
			 */
			AstraPortfolio._showSites( false, 'astra-api-post-loaded-on-scroll' );
		
		},

		/**
		 * On Scroll
		 */
		_scroll: function(event) {
			if( ! $('.astra-portfolio').length ) {
				return;
			}

			var scrollDistance = $(window).scrollTop();

			var themesBottom = Math.abs($(window).height() - $('.astra-portfolio').offset().top - $('.astra-portfolio').height());

			ajaxLoading = $('body').data('scrolling');

			if (scrollDistance > themesBottom && ajaxLoading == false && ! $( 'body' ).hasClass('astra-portfolio-loaded-all-sites') ) {
				AstraPortfolio._updatedPagedCount();

				if( ! $( '#astra-portfolio .spinner' ).length ) {
					$( '#astra-portfolio' ).append( wp.template('astra-portfolio-spinner') );
				}
				if( $( '#astra-portfolio .astra-portfolio-load-more-sites' ).length ) {
					$( '#astra-portfolio .astra-portfolio-load-more-sites' ).remove();
				}
				if( $( '#astra-portfolio .astra-portfolio-not-found' ).length ) {
					$( '#astra-portfolio .astra-portfolio-not-found' ).remove();
				}
				if( $( '#astra-portfolio .no-more-demos' ).length ) {
					$( '#astra-portfolio .no-more-demos').remove();
				}
				
				$('body').data('scrolling', true);

				/**
				 * @see _reinitGridScrolled() which called in trigger 'astra-api-post-loaded-on-scroll'
				 */
				AstraPortfolio._showSites( false, 'astra-api-post-loaded-on-scroll' );
			}
		
		},
		
		/**
		 * Update Page Count.
		 */
		_updatedPagedCount: function() {
			paged = parseInt($('body').attr('data-astra-demo-paged'));
			$('body').attr('data-astra-demo-paged', paged + 1);
			window.setTimeout(function () {
				$('body').data('scrolling', false);
			}, 800);
		},

		/**
		 * Reset Page Count.
		 */
		_resetPagedCount: function() {
			$('body').attr('data-astra-demo-last-request', '1');
			$('body').attr('data-astra-demo-paged', '1');
			$('body').attr('data-astra-demo-search', '');
			$('body').attr('data-scrolling', false);
			$('body').removeClass( 'astra-portfolio-loading' );

		}

	};

	/**
	 * Initialize AstraPortfolio.
	 */
	$(function(){
		AstraPortfolio.init();

		// Listen for popstate event.
		window.addEventListener( 'popstate', function( event ) {
			if ($.magnificPopup.instance.isOpen) {
				var hasAstraPortfolioPopup = $('.mfp-wrap').siblings('.astra-portfolio-lightbox, .astra-portfolio-popup').length > 0;
				if (hasAstraPortfolioPopup) {
					$.magnificPopup.close();
				}
			}
		});
	});

})(jQuery);