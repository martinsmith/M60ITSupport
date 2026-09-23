(function($){

	AstraPortfolioPost = {

		init: function()
		{
			this._bind();
			this._toggleCallToAction();
		},
		
		/**
		 * Binds events for the Astra Portfolio.
		 *
		 * @since 1.0.1
		 * @access private
		 * @method _bind
		 */
		_bind: function()
		{
			$( document ).on('click', '.astra-portfolio-set-media', AstraPortfolioPost._addImage );
			$( document ).on('click', '.astra-portfolio-remove-media', AstraPortfolioPost._removeImage );
			$( document ).on('click', 'input[name="astra-site-open-in-new-tab"]', AstraPortfolioPost._toggleCallToAction );
			$( document ).on('change', 'select[name="astra-site-open-portfolio-in"]', AstraPortfolioPost._toggleCallToAction );
		},

		/**
		 * Enable/Disable the call to action.
		 * 
		 * @return void
		 */
		_toggleCallToAction: function() {

			var toggleValue = false;

			// Open in new Tab from single `website`.
			if( $('input[name="astra-site-open-in-new-tab"]').length ) {
				toggleValue = $('input[name="astra-site-open-in-new-tab"]').is(":checked") || false;
			} else if( $( 'select[name="astra-site-open-portfolio-in"]').length ) {
				var openIn = $( 'select[name="astra-site-open-portfolio-in"] option:selected').val() || '';
				if( 'iframe' !== openIn ) {
					toggleValue = true;
				}
			}

			if( toggleValue ) {
				$('[name="astra-site-call-to-action"]').parents('.astra-portfolio-row').hide();
			} else {
				$('[name="astra-site-call-to-action"]').parents('.astra-portfolio-row').show();
			}
		},

		/**
		 * Add portfolio image.
		 */
		_addImage: function( event ) {
			event.preventDefault();

			var media_file;
			var selector_image_inner = $( event.target ).parents('.astra-portfolio-image-inner');
			var selector_image_wrap = $( event.target ).parents('.astra-portfolio-image');
			var selector_image_id   = selector_image_wrap.find('.image-id' );
			var selector_image_url  = selector_image_wrap.find('.image-url' );

			// Create the media frame.
			media_file = wp.media( {
				multiple: false
			} );

			// When an image is selected, run a callback.
			media_file.on( 'select', function() {

				var attachment = media_file.state().get( 'selection' ).first().toJSON();
				console.log('here: 2');
				
				if( attachment ) {

					var image_id  = attachment.id || '';
					var image_url = (typeof ( attachment.sizes.medium ) != 'undefined') ? attachment.sizes.medium.url : '';
					if( '' === image_url ) {
						image_url = attachment.url || '';
					}

					if( image_url && image_id ) {
						var template = wp.template('astra-portfolio-remove-media');
						selector_image_inner.html( template( image_url ) ).addClass('testing');

						// Set hidden fields.
						selector_image_id.val( image_id );
						selector_image_url.val( image_url );
					}
				}
			});

			// Finally, open the modal
			media_file.open();
		},

		_removeImage: function( event ) {
			event.preventDefault();

			var selector_image_wrap = $( this ).parents('.astra-portfolio-image');
			var selector_image_id   = selector_image_wrap.find('.image-id' );
			var selector_image_url  = selector_image_wrap.find('.image-url' );
			var selector_image_inner = $( this ).parents('.astra-portfolio-image-inner');

			var image_id 	= selector_image_id.val() || '',
				image_url 	= selector_image_url.val() || '';

			if( image_url && image_id ) {

				var template = wp.template('astra-portfolio-set-media');
				selector_image_inner.html( template( image_url ) );

				// Set hidden fields.
				selector_image_id.val( '' );
				selector_image_url.val( '' );
			}
		}

	};

	/**
	 * Initialize AstraPortfolioPost
	 */
	$(function(){
		AstraPortfolioPost.init();
	});

})(jQuery);