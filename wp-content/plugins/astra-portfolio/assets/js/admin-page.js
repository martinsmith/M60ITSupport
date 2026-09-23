/**
 * AJAX Request Queue
 *
 * - add()
 * - remove()
 * - run()
 * - stop()
 *
 * @since 1.0.0
 */
var AstraPortfolioAjaxQueue = (function() {

	var requests = [];

	return {

		/**
		 * Add AJAX request
		 *
		 * @since 1.0.0
		 */
		add:  function(opt) {
		    requests.push(opt);
		},

		/**
		 * Remove AJAX request
		 *
		 * @since 1.0.0
		 */
		remove:  function(opt) {
		    if( jQuery.inArray(opt, requests) > -1 ) {
		    	requests.splice($.inArray(opt, requests), 1);
		    }
		},

		/**
		 * Run / Process AJAX request
		 *
		 * @since 1.0.0
		 */
		run: function() {
		    var self = this,
		        oriSuc;


		    if( requests.length ) {
		        oriSuc = requests[0].complete;

		        requests[0].complete = function() {
		             if( typeof(oriSuc) === 'function' ) oriSuc();
		             requests.shift();
		             self.run.apply(self, []);
		        };

		        jQuery.ajax(requests[0]);
		    } else {
		    	self.tid = setTimeout(function() {
		    		self.run.apply(self, []);
		    	}, 1000);
		    }

		},

		/**
		 * Stop AJAX request
		 *
		 * @since 1.0.0
		 */
		stop:  function() {
		    requests = [];
		    console.log( 'requests', requests );
		    clearTimeout(this.tid);
		},

		/**
		 * Debugging.
		 *
		 * @param  {mixed} data Mixed data.
		 */
		_log: function( data, level ) {
			var date = new Date();
			var time = date.toLocaleTimeString();

			var color = '#444';

			if (typeof data == 'object') {
				console.log( data );
			} else {
				console.log( data + ' ' + time );
			}
		},
	};

}());

(function($){

	AstraPortfolioAdminPage = {

		init: function()
		{
			this._bind();
		},

		/**
		 * Binds events.
		 *
		 * @since 1.11.0
		 * @access private
		 * @method _bind
		 */
		_bind: function() {
			$( document ).on( 'click', '.astra-portfolio-sync-library', AstraPortfolioAdminPage._sync_sites);
			$( document ).on( 'astra-portfolio-process-all-import', AstraPortfolioAdminPage.process_all_import);
			$( document ).on( 'change', '.grid-style-option', AstraPortfolioAdminPage.scroll_speed);
		},

		process_all_import: function() {

			var button = $( '.astra-portfolio-sync-library' );

			button.text('Generating Import List..');

			// Download sites data in 100 sites.
			$.ajax({
				url  : AstraPortfolioAdminPageVars.ajax_url,
				type : 'POST',
				data : {
					action : 'astra-portfolio-get-all-data',
					_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
				},
				beforeSend: function() {
					console.log( 'Get all data..' );
				},
			})
			.fail(function( jqXHR ){
				console.log( jqXHR, 'error' );
		    })
			.done(function ( response ) {
				console.log( 'ALL SITES DATA 100+' );
				console.log( response );

				if( response.success ) {
					var sites = response.data;
					var total_sites = parseInt( Object.keys( sites ).length ) || 0;
					console.log( 'Sites', sites );
					console.log( 'Total Sites', total_sites );

					if( total_sites ) {

						AstraPortfolioAjaxQueue.stop();

						var page_no = 1;
						for( site_id in sites ) {
							console.log( site_id );
							AstraPortfolioAjaxQueue.add({
								url: AstraPortfolioAdminPageVars.ajax_url,
								type: 'POST',
								data: {
									action  : 'astra-portfolio-import-single-site',
									site_id : site_id,
									site_data : sites[ site_id ],
									_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
								},
								success: function( result ){
									button.text( 'Site ' + page_no + ' of ' + total_sites );
									console.log( result );
									console.log( page_no, 'New Site ', page_no, ' of ', total_sites, ' Sites' );

									if( page_no === total_sites ) {
										button.text( 'Updating latest checksums.');

										// Save settings.
										$.ajax({
											url: AstraPortfolioAdminPageVars.ajax_url,
											type : 'POST',
											data: {
												action: 'astra-portfolio-checksums-update',
												_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
											},
										})
										.done(function (result) {
											if( result.success ) {
												console.log( result );
												console.log( 'Complete...' );
												button.text( 'Imported ' + total_sites + ' sites. Refreshing..');
												setTimeout(function() {
													window.location = AstraPortfolioAdminPageVars.settings_page_url;
												}, 3000);
											}
										});
									} else {
										page_no++;
									}
								}
							});
						}

						AstraPortfolioAjaxQueue.run();
					} else {
						button.removeClass('updating-message').text('Imported All Sites..');
					}

				} else {
					console.log( response.data );
				}
			});

		},

		_sync_sites: function( event ) {
			event.preventDefault();

			var button = $( '.astra-portfolio-sync-library' );

			if( button.hasClass('updating-message') ) {
				return;
			}

			button.addClass('updating-message');

			// Verify Checksums.
			button.text( 'Checking Updates..' );

			var form = $('#astra-portfolio-settings');
			var tab_slug = form.find( 'input[name="tab_slug"]').val() || '';
			var other_categories = form.find( 'input[name="other-categories"]:checked').val() || false;
			var categories = form.find( 'input[name="categories"]:checked').val() || false;
			var show_search = form.find( 'input[name="show-search"]:checked').val() || false;
			var page_builder_slug = form.find( 'select[name="page-builder"] option:selected').attr('data-value') || '';
			var page_builder_id = form.find( 'select[name="page-builder"]').val() || '';
			var responsive_buttons = form.find( 'input[name="responsive-button"]:checked').val() || false;

			button.text( 'Saving Settings..' );

			// Save settings.
			$.ajax({
				url: AstraPortfolioAdminPageVars.ajax_url,
				type : 'POST',
				data: {
					action: 'astra-portfolio-save-settings',
					'tab_slug': tab_slug,
					'other-categories': other_categories,
					'categories': categories,
					'show-search': show_search,
					'page-builder': page_builder_id,
					'responsive-button': responsive_buttons,
					_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
				},
			})
			.done(function (result) {
				console.log( result );
				button.text( 'Settings Saved.' );

				// Save settings.
				$.ajax({
					url: AstraPortfolioAdminPageVars.ajax_url,
					type : 'POST',
					data: {
						action: 'astra-portfolio-checksums-check',
						_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
					},
				})
				.done(function (result) {
					console.log( result );

					if( ! result.success ) {
						button.text( 'No new sites available.' );
						button.removeClass('updating-message');
					} else {

						/**
						 * Start either AJAX or Batch process.
						 */
						if( 'batch' === AstraPortfolioAdminPageVars.sync_type ) {
							$('.astra-portfolio-notice.notice-success').remove();
							button.text( 'Importing..' );
							button.addClass('is-disabled');
							button.removeClass('updating-message');
							$( AstraPortfolioAdminPageVars.batch_started_notice ).insertAfter( $('#astra-portfolio-menu-page > h1') );
							return;
						}

						// Import Categories.
						button.text( 'Importing Categories..' );
						$.ajax({
							url: ajaxurl,
							type : 'POST',
							data: {
								action: 'astra_portfolio_import_term',
								new_taxonomy: 'astra-portfolio-categories',
								taxonomy: 'astra-site-category',
								import_status_string: 'Importing Categories..',
								_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
							},
						})
						.done(function (result) {
							console.log( result );
							button.text( 'Categories Imported Successfully.' );

							// Import Other Categories.
							button.text( 'Importing Other Categories..' );
							$.ajax({
								url: ajaxurl,
								type : 'POST',
								data: {
									action: 'astra_portfolio_import_term',
									new_taxonomy: 'astra-portfolio-other-categories',
									taxonomy: 'astra-site-page-builder',
									import_status_string: 'Importing Other Categories..',
									_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
								},
							})
							.done(function (result) {
								console.log( result );

								button.text( 'Other Categories Imported Successfully.' );

								// Download sites data in 100 sites.
								button.text( 'Getting All Sites..' );
								$.ajax({
									url  : AstraPortfolioAdminPageVars.ajax_url,
									type : 'POST',
									data : {
										action : 'astra-portfolio-get-request-count',
										page_builder : page_builder_slug,
										_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
									},
									beforeSend: function() {
										console.log( 'Get Total Sits..' );
									},
								})
								.fail(function( jqXHR ) {
									console.log( jqXHR, 'error' );
							    })
								.done(function ( response ) {
									// console.log( response );
									// return;
									if( response.success ) {
										var total = response.data.total_requests;

										console.log( 'Total Pages: ', total );

										var page_no = 1;

										for( page = 1; page <= total; page++ ) {

											console.log( 'Added in queue ' + page );

											AstraPortfolioAjaxQueue.add({
												url: AstraPortfolioAdminPageVars.ajax_url,
												type: 'POST',
												data: {
													action  : 'astra-portfolio-import-sites',
													page_no : page_no,
													page_builder : page_builder_slug,
													_ajax_nonce: AstraPortfolioAdminPageVars._ajax_nonce,
												},
												success: function( result ){

													console.log( result );
													console.log( 'Total Pages ', total, ' page ', page_no );

													if( page_no === total ) {
														$( document ).trigger( 'astra-portfolio-process-all-import' );
													}

													page_no++;
												}
											});
										}

										// Run the AJAX queue.
										AstraPortfolioAjaxQueue.run();
									} else {
										console.log( response.data );
									}
								});

							})
							.fail( function (e) {
								console.log("error");
								console.log( e );
							});

						})
						.fail( function (e) {
							console.log("error");
							console.log( e );
						});

					}
				});

			});

		},

		scroll_speed: function( event ) {
			if( 'style-1' === $(this).val() ) {
				$('.scroll-speed-option').show();
			} else {
				$('.scroll-speed-option').hide();
			}
		}

	};

	/**
	 * Initialize AstraPortfolioAdminPage
	 */
	$(function(){
		AstraPortfolioAdminPage.init();
	});

})(jQuery);