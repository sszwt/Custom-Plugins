/**
 * AI ACF Block Generator — Admin JavaScript
 */
( function ( $ ) {
	'use strict';

	var AABG = {
		init: function () {
			this.bindGenerator();
			this.bindDesignUpload();
			this.bindSettings();
			this.bindLibrary();
			this.bindDeviceSwitcher();
			this.bindSlugAuto();
		},

		ajax: function ( action, data, method ) {
			data = data || {};
			data.action = action;
			data.nonce = aabgAdmin.nonce;

			return $.ajax( {
				url: aabgAdmin.ajaxUrl,
				type: method || 'POST',
				data: data,
			} );
		},

		bindSlugAuto: function () {
			$( '#aabg-block-name' ).on( 'input', function () {
				var $slug = $( '#aabg-block-slug' );
				if ( ! $slug.data( 'manual' ) ) {
					$slug.val(
						( function () {
							var s = $( this )
								.val()
								.toLowerCase()
								.replace( /[^a-z0-9]+/g, '-' )
								.replace( /^-|-$/g, '' );
							// Leading digit breaks PHP vars / CSS classes.
							if ( /^[0-9]/.test( s ) ) {
								s = 'block-' + s;
							}
							return s || 'custom-block';
						}.call( this ) )
					);
				}
			} );

			$( '#aabg-block-slug' ).on( 'input', function () {
				$( this ).data( 'manual', true );
			} );
		},

		bindGenerator: function () {
			var self = this;
			var $form = $( '#aabg-generator-form' );

			if ( ! $form.length ) {
				return;
			}

			$form.on( 'submit', function ( e ) {
				e.preventDefault();

				var $btn = $( '#aabg-generate-btn' );
				$btn.addClass( 'is-loading' ).prop( 'disabled', true );

				var formData = new FormData( $form[0] );
				formData.append( 'action', 'aabg_generate_block' );
				formData.append( 'nonce', aabgAdmin.nonce );

				$.ajax( {
					url: aabgAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
				} )
					.done( function ( response ) {
						if ( response.success ) {
							self.showResult( response.data );
							self.loadPreview( response.data.preview_url );
						} else {
							self.showError( response.data?.message || aabgAdmin.i18n.error );
						}
					} )
					.fail( function ( xhr ) {
						var msg = xhr.responseJSON?.data?.message || xhr.responseText || aabgAdmin.i18n.error;
						self.showError( msg );
					} )
					.always( function () {
						$btn.removeClass( 'is-loading' ).prop( 'disabled', false );
					} );
			} );

			$( '#aabg-design-image' ).on( 'change', function () {
				AABG.setDesignFile( this.files[0] );
			} );
		},

		bindDesignUpload: function () {
			var $zone = $( '#aabg-design-dropzone' );
			var $input = $( '#aabg-design-image' );

			if ( ! $zone.length ) {
				return;
			}

			/* Native file input covers the dropzone — no manual trigger needed. */
			$zone.on( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					if ( ! $zone.hasClass( 'has-image' ) ) {
						$input.trigger( 'click' );
					}
				}
			} );

			$zone.on( 'dragover dragenter', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				$zone.addClass( 'is-dragover' );
			} );

			$zone.on( 'dragleave dragend drop', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				$zone.removeClass( 'is-dragover' );
			} );

			$zone.on( 'drop', function ( e ) {
				var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
				if ( files && files.length ) {
					AABG.setDesignFile( files[0] );
				}
			} );

			$( document ).on( 'paste', function ( e ) {
				if ( ! $zone.length || ! $( '#aabg-generator-form' ).length ) {
					return;
				}
				var items = e.originalEvent.clipboardData && e.originalEvent.clipboardData.items;
				if ( ! items ) {
					return;
				}
				for ( var i = 0; i < items.length; i++ ) {
					if ( items[i].type.indexOf( 'image' ) !== -1 ) {
						e.preventDefault();
						AABG.setDesignFile( items[i].getAsFile() );
						break;
					}
				}
			} );

			$( '#aabg-remove-image' ).on( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				AABG.clearDesignFile();
			} );
		},

		setDesignFile: function ( file ) {
			if ( ! file || file.type.indexOf( 'image' ) === -1 ) {
				return;
			}

			var $zone = $( '#aabg-design-dropzone' );
			var $input = $( '#aabg-design-image' );
			var $preview = $( '#aabg-design-preview' );
			var $placeholder = $( '#aabg-upload-placeholder' );
			var dt = new DataTransfer();

			dt.items.add( file );
			$input[0].files = dt.files;

			var reader = new FileReader();
			reader.onload = function ( e ) {
				$preview.find( 'img' ).attr( 'src', e.target.result );
				$preview.removeClass( 'aabg-hidden' );
				$placeholder.addClass( 'aabg-hidden' );
				$zone.addClass( 'has-image' );
			};
			reader.readAsDataURL( file );
		},

		clearDesignFile: function () {
			var $zone = $( '#aabg-design-dropzone' );
			var $input = $( '#aabg-design-image' );
			var $preview = $( '#aabg-design-preview' );
			var $placeholder = $( '#aabg-upload-placeholder' );

			$input.val( '' );
			$preview.addClass( 'aabg-hidden' ).find( 'img' ).attr( 'src', '' );
			$placeholder.removeClass( 'aabg-hidden' );
			$zone.removeClass( 'has-image' );
		},

		bindSettings: function () {
			var $btn = $( '#aabg-test-api-btn' );
			var $syncBtn = $( '#aabg-sync-css-btn' );

			if ( $syncBtn.length ) {
				$syncBtn.on( 'click', function () {
					var $result = $( '#aabg-sync-css-result' );
					$syncBtn.prop( 'disabled', true );
					$result.text( 'Syncing CSS...' );

					AABG.ajax( 'aabg_sync_block_css', {} )
						.done( function ( response ) {
							if ( response.success ) {
								$result.html( '<span class="aabg-status aabg-status--ok">' + response.data.message + '</span>' );
							} else {
								$result.html( '<span class="aabg-status aabg-status--error">' + ( response.data?.message || 'Failed' ) + '</span>' );
							}
						} )
						.fail( function ( xhr ) {
							var msg = xhr.responseJSON?.data?.message || 'Connection failed';
							$result.html( '<span class="aabg-status aabg-status--error">' + msg + '</span>' );
						} )
						.always( function () {
							$syncBtn.prop( 'disabled', false );
						} );
				} );
			}

			if ( ! $btn.length ) {
				return;
			}

			$btn.on( 'click', function () {
				var $result = $( '#aabg-test-api-result' );
				$btn.prop( 'disabled', true );
				$result.text( aabgAdmin.i18n.testing || 'Testing...' );

				AABG.ajax( 'aabg_test_openai', {} )
					.done( function ( response ) {
						if ( response.success ) {
							$result.html( '<span class="aabg-status aabg-status--ok">' + response.data.message + '</span>' );
						} else {
							$result.html( '<span class="aabg-status aabg-status--error">' + ( response.data?.message || 'Failed' ) + '</span>' );
						}
					} )
					.fail( function ( xhr ) {
						var msg = xhr.responseJSON?.data?.message || 'Connection failed';
						$result.html( '<span class="aabg-status aabg-status--error">' + msg + '</span>' );
					} )
					.always( function () {
						$btn.prop( 'disabled', false );
					} );
			} );

			$( '#aabg_ai_provider' ).on( 'change', function () {
				var provider = $( this ).val();
				$( '#aabg-openai-settings' ).toggle( provider === 'openai' );
				$( '#aabg-gemini-settings' ).toggle( provider === 'gemini' );
				$( '#aabg-test-api-result' ).empty();
			} );
		},

		showResult: function ( data ) {
			var $result = $( '#aabg-result' );
			$result.removeClass( 'aabg-hidden' );

			$( '#aabg-result-message' ).html(
				'<div class="aabg-result-success">' + ( data.message || aabgAdmin.i18n.success ) + '</div>'
				+ ( data.warning ? '<div class="aabg-notice aabg-notice--warning">' + data.warning + '</div>' : '' )
			);

			// Suggestions
			var $suggestions = $( '#aabg-suggestions' ).empty();
			var suggest = data.spec?.suggestions || data.manifest?.suggestions;

			if ( suggest ) {
				$suggestions.append( '<h3>AI Suggestions</h3>' );

				[ 'accessibility', 'performance', 'field_names', 'reusable_groups' ].forEach( function ( key ) {
					if ( suggest[ key ] && suggest[ key ].length ) {
						var title = key.replace( /_/g, ' ' );
						$suggestions.append( '<h4>' + title.charAt( 0 ).toUpperCase() + title.slice( 1 ) + '</h4><ul></ul>' );
						var $ul = $suggestions.find( 'ul' ).last();
						suggest[ key ].forEach( function ( tip ) {
							$ul.append( '<li>' + tip + '</li>' );
						} );
					}
				} );
			}

			// Files
			var $files = $( '#aabg-files-list' ).empty();
			if ( data.files ) {
				$files.append( '<h4>Generated Files</h4>' );
				data.files.forEach( function ( file ) {
					$files.append( '<span class="aabg-file-badge">' + file + '</span>' );
				} );
			}

			if ( data.build_note ) {
				$files.append( '<p class="description"><strong>Note:</strong> ' + data.build_note + '</p>' );
			}

			$( '#aabg-preview-panel' ).removeClass( 'aabg-hidden' );
		},

		showError: function ( message ) {
			var $result = $( '#aabg-result' );
			$result.removeClass( 'aabg-hidden' );
			$( '#aabg-result-message' ).html(
				'<div class="aabg-notice aabg-notice--error">' + message + '</div>'
			);
		},

		loadPreview: function ( url ) {
			$( '#aabg-preview-frame' ).attr( 'src', url );
		},

		bindDeviceSwitcher: function () {
			$( document ).on( 'click', '.aabg-device-btn', function () {
				var device = $( this ).data( 'device' );
				var $parent = $( this ).closest( '.aabg-preview-card, .aabg-modal__content, .aabg-modal' );

				$parent.find( '.aabg-device-btn' ).removeClass( 'active' );
				$( this ).addClass( 'active' );
				$parent.find( '.aabg-preview-frame-wrap' ).attr( 'data-device', device );
			} );
		},

		bindLibrary: function () {
			var self = this;

			// Search & filter
			$( '#aabg-library-search, #aabg-library-filter' ).on( 'input change', function () {
				var search = $( '#aabg-library-search' ).val().toLowerCase();
				var category = $( '#aabg-library-filter' ).val();

				$( '.aabg-block-card' ).each( function () {
					var $card = $( this );
					var title = $card.find( 'h3' ).text().toLowerCase();
					var desc = $card.find( '.aabg-block-card__desc' ).text().toLowerCase();
					var slug = $card.data( 'slug' ).toLowerCase();
					var cat = $card.data( 'category' );

					var matchSearch = ! search || title.indexOf( search ) > -1 || desc.indexOf( search ) > -1 || slug.indexOf( search ) > -1;
					var matchCat = ! category || cat === category;

					$card.toggle( matchSearch && matchCat );
				} );
			} );

			// Delete
			$( document ).on( 'click', '.aabg-delete-btn', function () {
				if ( ! confirm( aabgAdmin.i18n.confirmDelete ) ) {
					return;
				}

				var slug = $( this ).data( 'slug' );
				var $card = $( this ).closest( '.aabg-block-card' );

				self.ajax( 'aabg_delete_block', { slug: slug } ).done( function ( response ) {
					if ( response.success ) {
						$card.fadeOut( 300, function () { $( this ).remove(); } );
					}
				} );
			} );

			// Duplicate
			$( document ).on( 'click', '.aabg-duplicate-btn', function () {
				var slug = $( this ).data( 'slug' );
				var $btn = $( this );
				$btn.prop( 'disabled', true );

				self.ajax( 'aabg_duplicate_block', { slug: slug } ).done( function ( response ) {
					if ( response.success ) {
						location.reload();
					}
				} ).always( function () {
					$btn.prop( 'disabled', false );
				} );
			} );

			// Export dropdown
			$( document ).on( 'click', '.aabg-export-toggle', function ( e ) {
				e.stopPropagation();
				$( this ).closest( '.aabg-dropdown' ).toggleClass( 'is-open' );
			} );

			$( document ).on( 'click', function () {
				$( '.aabg-dropdown' ).removeClass( 'is-open' );
			} );

			$( document ).on( 'click', '.aabg-export-btn', function () {
				var slug = $( this ).data( 'slug' );
				var format = $( this ).data( 'format' );

				self.ajax( 'aabg_export_block', { slug: slug, format: format } ).done( function ( response ) {
					if ( response.success && response.data.url ) {
						window.open( response.data.url, '_blank' );
					} else if ( response.success && response.data.content ) {
						self.downloadFile( response.data.filename, response.data.content );
					}
				} );
			} );

			// Preview modal
			$( document ).on( 'click', '.aabg-preview-btn', function () {
				var slug = $( this ).data( 'slug' );
				var url = aabgAdmin.ajaxUrl + '?action=aabg_preview_block&slug=' + slug + '&nonce=' + aabgAdmin.nonce;

				$( '#aabg-modal-preview-frame' ).attr( 'src', url );
				$( '#aabg-preview-modal' ).removeClass( 'aabg-hidden' );
			} );

			$( '.aabg-modal__close, .aabg-modal__backdrop' ).on( 'click', function () {
				$( '#aabg-preview-modal' ).addClass( 'aabg-hidden' );
				$( '#aabg-modal-preview-frame' ).attr( 'src', '' );
			} );

			// Import
			$( '#aabg-import-file' ).on( 'change', function () {
				var file = this.files[ 0 ];
				if ( ! file ) {
					return;
				}

				var formData = new FormData();
				formData.append( 'action', 'aabg_import_block' );
				formData.append( 'nonce', aabgAdmin.nonce );
				formData.append( 'import_file', file );

				$.ajax( {
					url: aabgAdmin.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
				} ).done( function ( response ) {
					if ( response.success ) {
						location.reload();
					} else {
						alert( response.data?.message || aabgAdmin.i18n.error );
					}
				} );
			} );
		},

		downloadFile: function ( filename, content ) {
			var blob = new Blob( [ content ], { type: 'text/plain' } );
			var url = URL.createObjectURL( blob );
			var a = document.createElement( 'a' );
			a.href = url;
			a.download = filename;
			a.click();
			URL.revokeObjectURL( url );
		},
	};

	$( document ).ready( function () {
		AABG.init();
	} );
}( jQuery ) );
