<?php
/**
 *  BSF Analytics Stats
 *
 * @package BSF_Core
 */

/**
 * Delete these transients/options for debugging
 * set_site_transient( 'update_plugins', null );
 * set_site_transient( 'update_themes', null );
 * delete_option( 'brainstrom_products' );
 */

if ( ! class_exists( 'BSF_Update_Manager' ) ) {

	/**
	 * Update Manager Class
	 *
	 * @class BSF_Update_Manager
	 */
	class BSF_Update_Manager {

		/**
		 * Constructor function that initializes required sections
		 */
		public function __construct() {
			// update data to WordPress's transient.
			add_filter(
				'pre_set_site_transient_update_plugins',
				array(
					$this,
					'brainstorm_update_plugins_transient',
				)
			);
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'brainstorm_update_themes_transient' ) );

			// display changelog in update details.
			add_filter( 'plugins_api', array( $this, 'bsf_get_plugin_information' ), 10, 3 );

			// display correct error messages.
			add_action( 'load-plugins.php', array( $this, 'bsf_update_display_license_link' ) );

			add_filter( 'upgrader_pre_download', array( $this, 'modify_download_package_message' ), 20, 3 );

			add_action( 'bsf_get_plugin_information', array( $this, 'plugin_information' ) );
		}

		/**
		 * Function to update plugin's transient.
		 *
		 * @param obj $_transient_data Transient Data.
		 * @return $_transient_data.
		 */
		public function brainstorm_update_plugins_transient( $_transient_data ) {

			/**
			 * Whether to skip adding BSF update data to the transient.
			 *
			 * Set during a version rollback, otherwise the rollback package
			 * URL gets overwritten with the latest version's package.
			 *
			 * @since x.x.x
			 * @param bool $skip Whether to skip adding update data. Default false.
			 */
			if ( apply_filters( 'bsf_skip_update_transient_data', false ) ) {
				return $_transient_data;
			}

			if ( ! is_object( $_transient_data ) ) {
				$_transient_data = new stdClass();
			}

			// Refresh stored remote data first (if a check is due) so the product
			// list read below reflects the latest versions. This is idempotent —
			// bsf_update_transient_data() calls it again but the time guard makes
			// the second call a no-op.
			$this->maybe_force_check_bsf_product_updates();

			// Fetch the product list once and reuse it across the three passes below
			// (update check, stale-entry cleanup, no_update population), collapsing
			// the repeated brainstorm_get_all_products() + prepare_plugins_for_update()
			// scans (option read + realpath) from three to one.
			//
			// Note: bsf_get_current_version() still reads each product header once per
			// pass — do NOT memoize it per request. upgrader_process_complete rewrites
			// this transient in the same request that just changed the on-disk version,
			// so a static cache would serve the pre-update version and reintroduce #103.
			$all_products = $this->get_all_products_for_type( 'plugins' );

			$update_data = $this->bsf_update_transient_data( 'plugins', $all_products );

			foreach ( $update_data as $key => $product ) {

				if ( isset( $product['template'] ) && '' !== $product['template'] ) {
					$template = $product['template'];
				} elseif ( isset( $product['init'] ) && '' !== $product['init'] ) {
					$template = $product['init'];
				}

				if ( isset( $_transient_data->response[ $template ] ) ) {
					$other_plugins = $_transient_data->response[ $template ];

					if ( is_object( $other_plugins ) && isset( $other_plugins->id ) && 'w.org/plugins/convertpro' !== $other_plugins->id ) {
						// Skip updating this plugin — it doesn't belong to us.
						continue;
					}
				}

				if ( false === $this->enable_auto_updates( $product['id'] ) ) {
					continue;
				}

				$plugin                 = new stdClass();
				$plugin->id             = isset( $product['id'] ) ? $product['id'] : '';
				$plugin->slug           = $this->bsf_get_plugin_slug( $template );
				$plugin->plugin         = isset( $template ) ? $template : '';
				$plugin->upgrade_notice = '';

				if ( $this->use_beta_version( $plugin->id ) ) {
					$plugin->new_version     = isset( $product['version_beta'] ) ? $product['version_beta'] : '';
					$plugin->upgrade_notice .= 'It is recommended to use the beta version on a staging enviornment only.';
					// A beta must never be installed unattended (#13). The version stays
					// available for a manual update and the toggle stays visible — core reads
					// this flag only in WP_Automatic_Updater::should_update().
					$plugin->disable_autoupdate = true;
				} else {
					$plugin->new_version = isset( $product['remote'] ) ? $product['remote'] : '';
				}

				$plugin->url = isset( $product['purchase_url'] ) ? $product['purchase_url'] : '';

				if ( BSF_License_Manager::bsf_is_active_license( $product['id'] ) === true ) {
					$plugin->package = $this->bsf_get_package_uri( $product['id'] );
				} else {
					$plugin->package = '';
					// No usable package without an active license — tell core not to
					// attempt unattended updates (honoured by WP_Automatic_Updater::should_update()).
					// The manual update flow and the "Enable auto-updates" toggle stay intact.
					$plugin->disable_autoupdate = true;
					$bundled                    = self::bsf_is_product_bundled( $plugin->id );
					if ( ! empty( $bundled ) ) {
						$parent_id              = $bundled[0];
						$parent_name            = brainstrom_product_name( $parent_id );
						$plugin->upgrade_notice = 'This plugin is came bundled with the ' . $parent_name . '. For receiving updates, you need to register license of ' . $parent_name . '.';
					} else {
						$plugin->upgrade_notice .= ' Please activate your license to receive automatic updates.';
					}
				}

				$plugin->tested       = isset( $product['tested'] ) ? $product['tested'] : '';
				$plugin->requires_php = isset( $product['php_version'] ) ? $product['php_version'] : '';

				$plugin->icons = apply_filters(
					"bsf_product_icons_{$product['id']}",
					array(
						'1x'      => ( isset( $product['product_image'] ) ) ? $product['product_image'] : '',
						'2x'      => ( isset( $product['product_image'] ) ) ? $product['product_image'] : '',
						'default' => ( isset( $product['product_image'] ) ) ? $product['product_image'] : '',
					)
				);

				$_transient_data->last_checked          = time();
				$_transient_data->response[ $template ] = $plugin;
			}

			$_transient_data = $this->remove_stale_bsf_updates( $_transient_data, $all_products );

			return $this->populate_no_update( $_transient_data, 'plugins', $all_products );
		}

		/**
		 * Add up to date products to the `no_update` list of the update transient data.
		 *
		 * Since WordPress 5.5, a plugin or theme absent from both the `response`
		 * and the `no_update` lists of its update transient is treated as not
		 * supporting updates, and the "Enable auto-updates" link/toggle is hidden
		 * on the Plugins/Themes screen. Populating `no_update` keeps it visible.
		 *
		 * Handles both product types so the plugin and theme paths cannot drift.
		 *
		 * @since 1.29.18
		 *
		 * @param object     $_transient_data Transient Data.
		 * @param string     $product_type    Product type — 'plugins' or 'themes'.
		 * @param array|null $all_products    Optional. Pre-fetched product list to reuse.
		 *                                    Defaults to null (fetched internally).
		 * @return object $_transient_data
		 */
		private function populate_no_update( $_transient_data, $product_type, $all_products = null ) {

			if ( null === $all_products ) {
				$all_products = $this->get_all_products_for_type( $product_type );
			}

			$is_theme = ( 'themes' === $product_type );

			foreach ( $all_products as $key => $product ) {

				$product_id = isset( $product['id'] ) ? $product['id'] : '';

				if ( bsf_product_updates_disabled( $product_id ) ) {
					continue;
				}

				if ( false === $this->enable_auto_updates( $product_id ) ) {
					continue;
				}

				$template = $this->get_product_template( $product );

				if ( '' === $template ) {
					continue;
				}

				// For themes, use wp_get_theme() so the check honours themes registered
				// in additional roots via register_theme_directory() and confirms a real
				// theme (style.css present), not just an existing directory.
				if ( $is_theme && ! wp_get_theme( $template )->exists() ) {
					continue;
				}

				// Skip products which have an update queued or are managed by another updater.
				if ( isset( $_transient_data->response[ $template ] ) || isset( $_transient_data->no_update[ $template ] ) ) {
					continue;
				}

				$version = bsf_get_current_version( $template, $product_type );

				if ( empty( $version ) ) {
					$version = isset( $product['version'] ) ? $product['version'] : '';
				}

				if ( $is_theme ) {
					$entry                 = array();
					$entry['theme']        = $template;
					$entry['new_version']  = $version;
					$entry['url']          = isset( $product['purchase_url'] ) ? $product['purchase_url'] : '';
					$entry['package']      = '';
					$entry['requires_php'] = isset( $product['php_version'] ) ? $product['php_version'] : '';
				} else {
					$entry               = new stdClass();
					$entry->id           = $product_id;
					$entry->slug         = $this->bsf_get_plugin_slug( $template );
					$entry->plugin       = $template;
					$entry->new_version  = $version;
					$entry->url          = isset( $product['purchase_url'] ) ? $product['purchase_url'] : '';
					$entry->package      = '';
					$entry->tested       = isset( $product['tested'] ) ? $product['tested'] : '';
					$entry->requires_php = isset( $product['php_version'] ) ? $product['php_version'] : '';

					$entry->icons = apply_filters(
						"bsf_product_icons_{$product_id}",
						array(
							'1x'      => ( isset( $product['product_image'] ) ) ? $product['product_image'] : '',
							'2x'      => ( isset( $product['product_image'] ) ) ? $product['product_image'] : '',
							'default' => ( isset( $product['product_image'] ) ) ? $product['product_image'] : '',
						)
					);
				}

				if ( ! isset( $_transient_data->no_update ) || ! is_array( $_transient_data->no_update ) ) {
					$_transient_data->no_update = array();
				}

				$_transient_data->no_update[ $template ] = $entry;
			}

			return $_transient_data;
		}

		/**
		 * Get the template (plugin init path / theme directory) for a product.
		 *
		 * @since 1.29.15
		 *
		 * @param array $product Product data.
		 * @return string Template of the product. Empty string if not found.
		 */
		private function get_product_template( $product ) {
			if ( isset( $product['template'] ) && '' !== $product['template'] ) {
				return $product['template'];
			}

			if ( isset( $product['init'] ) && '' !== $product['init'] ) {
				return $product['init'];
			}

			return '';
		}

		/**
		 * Get the list of registered BSF products for a given type.
		 *
		 * Scanning product files is relatively expensive (each product reads its
		 * plugin/theme header from disk), so callers that need the list more than
		 * once should fetch it here a single time and pass it down.
		 *
		 * @since 1.29.18
		 *
		 * @param string $product_type Product type — 'plugins' or 'themes'.
		 * @return array Products of the requested type.
		 */
		private function get_all_products_for_type( $product_type ) {
			if ( 'themes' === $product_type ) {
				return brainstorm_get_all_products( true, false, true );
			}

			return self::prepare_plugins_for_update( brainstorm_get_all_products( false, true, false ) );
		}

		/**
		 * Remove stale update entries of BSF products from the update_plugins transient data.
		 *
		 * An update entry becomes stale when the version installed on disk is already
		 * up to date with the offered version — e.g. when the entry was injected from
		 * a stale stored product version right after an update. Leaving it in place
		 * makes WordPress offer/auto-install the same version again.
		 *
		 * @since 1.29.15
		 *
		 * @param object     $_transient_data Transient Data.
		 * @param array|null $all_products    Optional. Pre-fetched product list to reuse,
		 *                                    avoiding a repeat scan of every product file.
		 *                                    Defaults to null (fetched internally).
		 * @return object $_transient_data
		 */
		public function remove_stale_bsf_updates( $_transient_data, $all_products = null ) {
			if ( empty( $_transient_data->response ) || ! is_array( $_transient_data->response ) ) {
				return $_transient_data;
			}

			if ( null === $all_products ) {
				$all_products = $this->get_all_products_for_type( 'plugins' );
			}

			foreach ( $all_products as $key => $product ) {
				$product_id = isset( $product['id'] ) ? $product['id'] : '';
				$template   = $this->get_product_template( $product );

				if ( '' === $template || ! isset( $_transient_data->response[ $template ] ) ) {
					continue;
				}

				$entry = $_transient_data->response[ $template ];

				// Only touch entries injected by this updater — never wp.org ones.
				if ( ! is_object( $entry ) || ! isset( $entry->id ) || $entry->id !== $product_id || ! isset( $entry->new_version ) ) {
					continue;
				}

				$live_version = bsf_get_current_version( $template, 'plugins' );

				if ( ! empty( $live_version ) && ! version_compare( $entry->new_version, $live_version, '>' ) ) {
					unset( $_transient_data->response[ $template ] );
				}
			}

			return $_transient_data;
		}

		/**
		 * Function to update theme's transient.
		 *
		 * @param obj $_transient_data Transient Data.
		 * @return $_transient_data.
		 */
		public function brainstorm_update_themes_transient( $_transient_data ) {

			global $pagenow;

			if ( ! is_object( $_transient_data ) ) {
				$_transient_data = new stdClass();
			}

			if ( 'themes.php' !== $pagenow && 'update-core.php' !== $pagenow ) {
				// Still populate `no_update` so the "Enable auto-updates" toggle
				// stays visible on all requests (cron, load-update.php, etc.),
				// not only on themes.php/update-core.php. Needs no remote data, so
				// this path deliberately skips the remote check below.
				return $this->populate_no_update( $_transient_data, 'themes' );
			}

			// Refresh stored remote data first (if a check is due) so the product
			// list read below reflects the latest versions. This is idempotent —
			// bsf_update_transient_data() calls it again but the time guard makes
			// the second call a no-op.
			$this->maybe_force_check_bsf_product_updates();

			// Fetch the product list once and reuse it for both the update check
			// and the no_update population below.
			$all_products = $this->get_all_products_for_type( 'themes' );

			$update_data = $this->bsf_update_transient_data( 'themes', $all_products );

			foreach ( $update_data as $key => $product ) {

				if ( false === $this->enable_auto_updates( $product['id'] ) ) {
					continue;
				}

				if ( isset( $product['template'] ) && '' !== $product['template'] ) {
					$template = $product['template'];
				}

				$themes          = array();
				$themes['theme'] = isset( $template ) ? $template : '';

				if ( $this->use_beta_version( $product['id'] ) ) {
					$themes['new_version'] = isset( $product['version_beta'] ) ? $product['version_beta'] : '';
					// A beta must never be installed unattended (#13). The version stays
					// available for a manual update and the toggle stays visible — core reads
					// this flag only in WP_Automatic_Updater::should_update().
					$themes['disable_autoupdate'] = true;
				} else {
					$themes['new_version'] = isset( $product['remote'] ) ? $product['remote'] : '';
				}

				$themes['url'] = isset( $product['purchase_url'] ) ? $product['purchase_url'] : '';
				if ( BSF_License_Manager::bsf_is_active_license( $product['id'] ) === true ) {
					$themes['package'] = $this->bsf_get_package_uri( $product['id'] );
				} else {
					$themes['package']        = '';
					$themes['upgrade_notice'] = 'Please activate your license to receive automatic updates.';
					// No usable package without an active license — tell core not to
					// attempt unattended updates (honoured by WP_Automatic_Updater::should_update()).
					// The manual update flow and the "Enable auto-updates" toggle stay intact.
					$themes['disable_autoupdate'] = true;
				}
				$_transient_data->last_checked = time();

				if ( isset( $template ) ) {
					$_transient_data->response[ $template ] = $themes;
				}
			}

			return $this->populate_no_update( $_transient_data, 'themes', $all_products );
		}

		/**
		 * Allow autoupdates to be enabled/disabled per product basis.
		 *
		 * @param String $product_id - Product ID.
		 * @return boolean True - IF updates are to be enabled. False if updates are to be disabled.
		 */
		private function enable_auto_updates( $product_id ) {
			return apply_filters( "bsf_enable_product_autoupdates_{$product_id}", true );
		}

		/**
		 *
		 * Updates information on the "View version x.x details" page with custom data.
		 *
		 * @uses api_request()
		 *
		 * @param mixed  $_data Data.
		 * @param string $_action Action.
		 * @param object $_args Arguments.
		 *
		 * @return object $_data
		 */
		public function bsf_get_plugin_information( $_data, $_action = '', $_args = null ) {

			if ( 'plugin_information' !== $_action ) {

				return $_data;

			}

			$brainstrom_products = apply_filters( 'bsf_get_plugin_information', get_option( 'brainstrom_products', array() ) );

			$plugins      = isset( $brainstrom_products['plugins'] ) ? $brainstrom_products['plugins'] : array();
			$themes       = isset( $brainstrom_products['themes'] ) ? $brainstrom_products['themes'] : array();
			$all_products = $plugins + $themes;

			foreach ( $all_products as $key => $product ) {

				$product_slug = isset( $product['slug'] ) ? $product['slug'] : '';

				if ( $product_slug === $_args->slug ) {

					$id = isset( $product['id'] ) ? $product['id'] : '';

					$info = new stdClass();

					if ( $this->use_beta_version( $id ) ) {
						$info->new_version = isset( $product['version_beta'] ) ? $product['version_beta'] : '';
					} else {
						$info->new_version = isset( $product['remote'] ) ? $product['remote'] : '';
					}

					$product_name   = isset( $product['name'] ) ? $product['name'] : '';
					$info->name     = bsf_get_white_lable_product_name( $id, $product_name );
					$info->slug     = $product_slug;
					$info->version  = isset( $product['remote'] ) ? $product['remote'] : '';
					$info->author   = apply_filters( "bsf_product_author_{$id}", 'Brainstorm Force' );
					$info->url      = isset( $product['changelog_url'] ) ? apply_filters( "bsf_product_url_{$id}", $product['changelog_url'] ) : apply_filters( "bsf_product_url_{$id}", '' );
					$info->homepage = isset( $product['purchase_url'] ) ? apply_filters( "bsf_product_homepage_{$id}", $product['purchase_url'] ) : apply_filters( "bsf_product_homepage_{$id}", '' );

					if ( BSF_License_Manager::bsf_is_active_license( $id ) === true ) {
						$package_url         = $this->bsf_get_package_uri( $id );
						$info->package       = $package_url;
						$info->download_link = $package_url;
					}

					$info->sections                = array();
					$product_decription            = isset( $product['description'] ) ? $product['description'] : '';
					$info->sections['description'] = apply_filters( "bsf_product_description_{$id}", $product_decription );
					$product_changelog             = 'Thank you for using ' . $info->name . '. </br></br>To make your experience using ' . $info->name . ' better we release updates regularly, you can view the full changelog <a href="' . $info->url . '">here</a>';
					$info->sections['changelog']   = apply_filters( "bsf_product_changelog_{$id}", $product_changelog );

					$_data = $info;
				}
			}

			return $_data;
		}

		/**
		 * Check if product is bundled.
		 *
		 * @param array  $bsf_product Product.
		 * @param string $search_by Search By.
		 * @return $product_parent.
		 */
		public static function bsf_is_product_bundled( $bsf_product, $search_by = 'id' ) {
			$brainstrom_bundled_products = get_option( 'brainstrom_bundled_products', array() );
			$product_parent              = array();

			foreach ( $brainstrom_bundled_products as $parent => $products ) {

				foreach ( $products as $key => $product ) {

					if ( 'init' === $search_by ) {

						if ( $product->init === $bsf_product ) {
							$product_parent[] = $parent;
						}
					} elseif ( 'id' === $search_by ) {

						if ( $product->id === $bsf_product ) {
							$product_parent[] = $parent;
						}
					} elseif ( 'name' === $search_by ) {

						if ( strcasecmp( $product->name, $bsf_product ) === 0 ) {
							$product_parent[] = $parent;
						}
					}
				}
			}

			$product_parent = apply_filters( 'bsf_is_product_bundled', array_unique( $product_parent ), $bsf_product, $search_by );

			return $product_parent;
		}
		/**
		 * Get package URL
		 *
		 * @param int $product_id Product Id.
		 * @return string $download_path.
		 */
		public function bsf_get_package_uri( $product_id ) {

			$product       = get_brainstorm_product( $product_id );
			$status        = BSF_License_Manager::bsf_is_active_license( $product_id );
			$download_path = '';
			$parent_product = isset( $product['parent'] ) ? $product['parent'] : '';

			if ( $this->use_beta_version( $product_id ) ) {
				$version = isset( $product['version_beta'] ) ? $product['version_beta'] : '';
			} else {
				$version = isset( $product['remote'] ) ? $product['remote'] : '';
			}

			if ( '' !== $version && false !== $status ) {
				$bundled_product = self::bsf_is_product_bundled( $product_id );
				$purchase_key    = $this->get_purchse_key( $product_id );
				$is_bundled      = ( ! empty( $bundled_product ) ) ? '1' : '0';

				$download_params = array(
					'version_no'   => $version,
					'purchase_key' => $purchase_key,
					'site_url'     => get_site_url(),
					'is_bundled'   => $is_bundled,
					'parent_product' => $parent_product,
				);

				$download_path = bsf_get_api_site( false, true ) . 'download/' . $product_id . '?' . http_build_query( $download_params );
				// Clear Product versions from transient after update.
				bsf_clear_versions_cache( $product_id );

				return $download_path;
			}

			return $download_path;
		}
		/**
		 *  Update transient Data.
		 *
		 *  @param string     $product_type Product Type.
		 *  @param array|null $all_products Optional. Pre-fetched product list to reuse,
		 *                                  avoiding a repeat scan of every product file.
		 *                                  Defaults to null (fetched internally).
		 *  @return $update_required.
		 */
		public function bsf_update_transient_data( $product_type, $all_products = null ) {

			$this->maybe_force_check_bsf_product_updates();

			$update_required = array();

			if ( null === $all_products ) {
				$all_products = $this->get_all_products_for_type( $product_type );
			}

			foreach ( $all_products as $key => $product ) {

				$product_id = isset( $product['id'] ) ? $product['id'] : '';

				if ( bsf_product_updates_disabled( $product_id ) ) {
					continue;
				}

				$remote       = isset( $product['remote'] ) ? $product['remote'] : '';
				$local        = isset( $product['version'] ) ? $product['version'] : '';
				$version_beta = isset( $product['version_beta'] ) ? $product['version_beta'] : $remote;

				// The stored version is synced only on `admin_init`, so it can be stale
				// in cron/ajax requests — the version on disk is authoritative.
				$live_version = bsf_get_current_version( $this->get_product_template( $product ), $product_type );
				if ( ! empty( $live_version ) ) {
					$local = $live_version;
				}

				if ( $this->use_beta_version( $product_id ) ) {
					$remote = $version_beta;
				}

				if ( version_compare( $remote, $local, '>' ) ) {
					array_push( $update_required, $product );
				}
			}

			return $update_required;
		}

		/**
		 * Remove plugins from the updates array which are not installed.
		 *
		 * @param Array $plugins Plugins.
		 * @return Array of plugins.
		 */
		public static function prepare_plugins_for_update( $plugins ) {
			foreach ( $plugins as $key => $plugin ) {
				if ( isset( $plugin['template'] ) && ! file_exists( dirname( realpath( WP_PLUGIN_DIR . '/' . $plugin['template'] ) ) ) ) {
					unset( $plugins[ $key ] );
				}
				if ( isset( $plugin['init'] ) && ! file_exists( dirname( realpath( WP_PLUGIN_DIR . '/' . $plugin['init'] ) ) ) ) {
					unset( $plugins[ $key ] );
				}
			}

			return $plugins;
		}
		/**
		 * Force check BSF Product updates.
		 */
		public function maybe_force_check_bsf_product_updates() {
			if ( true === bsf_time_since_last_versioncheck( 2, 'bsf_last_update_check' ) ) {
				global $ultimate_referer;
				$ultimate_referer = 'on-transient-delete-2-hours';
				bsf_check_product_update();
				update_option( 'bsf_last_update_check', (string) current_time( 'timestamp' ) );
			}
		}

		/**
		 * Use Beta version.
		 *
		 * @param int $product_id Product ID.
		 * @return bool.
		 */
		public function use_beta_version( $product_id ) {

			$product = get_brainstorm_product( $product_id );
			$stable  = isset( $product['remote'] ) ? $product['remote'] : '';
			$beta    = isset( $product['version_beta'] ) ? $product['version_beta'] : '';

			// If beta version is not set, return.
			if ( '' === $beta ) {
				return false;
			}

			if ( version_compare( $stable, $beta, '<' ) &&
				self::bsf_allow_beta_updates( $product_id ) ) {

				return true;
			}

			return false;
		}

		/**
		 * Beta version normalized.
		 *
		 * @param array $beta Beta.
		 * @return $version.
		 */
		public function beta_version_normalized( $beta ) {
			$beta_explode = explode( '-', $beta );

			$version = $beta_explode[0] . '.' . str_replace( 'beta', '', $beta_explode[1] );

			return $version;
		}

		/**
		 * Allow Beta updates.
		 *
		 * @param array $product_id Product ID.
		 * @return bool.
		 */
		public static function bsf_allow_beta_updates( $product_id ) {
			return apply_filters( "bsf_allow_beta_updates_{$product_id}", false );
		}

		/**
		 * Get Plugin's slug.
		 *
		 * @param array $template Template.
		 * @return $slug.
		 */
		public function bsf_get_plugin_slug( $template ) {
			$slug = explode( '/', $template );

			if ( isset( $slug[0] ) ) {
				return $slug[0];
			}

			return '';
		}

		/**
		 * Update display license link.
		 */
		public function bsf_update_display_license_link() {
			$brainstorm_all_products = $this->brainstorm_all_products();

			foreach ( $brainstorm_all_products as $key => $product ) {

				if ( isset( $product['id'] ) ) {
					$id = $product['id'];

					if ( isset( $product['template'] ) && '' !== $product['template'] ) {
						$template = $product['template'];
					} elseif ( isset( $product['init'] ) && '' !== $product['init'] ) {
						$template = $product['init'];
					}

					if ( BSF_License_Manager::bsf_is_active_license( $id ) === false ) {

						if ( is_plugin_active( $template ) ) {
							add_action(
								"in_plugin_update_message-$template",
								array(
									$this,
									'bsf_add_registration_message',
								),
								9,
								2
							);
						}
					} else {
						add_action(
							"in_plugin_update_message-$template",
							array(
								$this,
								'add_beta_update_message',
							),
							9,
							2
						);
					}
				}
			}
		}
		/**
		 *  Brainstorm All Products.
		 */
		public function brainstorm_all_products() {
			$brainstrom_products         = get_option( 'brainstrom_products', array() );
			$brainstrom_products_plugins = isset( $brainstrom_products['plugins'] ) ? $brainstrom_products['plugins'] : array();
			$brainstrom_products_themes  = isset( $brainstrom_products['themes'] ) ? $brainstrom_products['themes'] : array();
			$brainstrom_bundled_products = get_option( 'brainstrom_bundled_products', array() );

			$bundled = array();

			foreach ( $brainstrom_bundled_products as $parent => $children ) {

				foreach ( $children as $key => $product ) {
					$bundled[ $product->id ] = (array) $product;
				}
			}

			// array of all the products.
			$all_products = $brainstrom_products_plugins + $brainstrom_products_themes + $bundled;

			return $all_products;
		}
		/**
		 *  Add Registration message.
		 *
		 *  @param array $plugin_data Plugin data.
		 *  @param array $response Response.
		 */
		public function bsf_add_registration_message( $plugin_data, $response ) {

			$plugin_init = isset( $plugin_data['plugin'] ) ? $plugin_data['plugin'] : '';

			if ( '' !== $plugin_init ) {
				$product_id        = brainstrom_product_id_by_init( $plugin_init );
				$bundled           = self::bsf_is_product_bundled( $plugin_init, 'init' );
				$registration_page = bsf_registration_page_url( '', $product_id );
			} else {
				$plugin_name       = isset( $plugin_data['name'] ) ? $plugin_data['name'] : '';
				$product_id        = brainstrom_product_id_by_name( $plugin_name );
				$bundled           = self::bsf_is_product_bundled( $plugin_name, 'name' );
				$registration_page = bsf_registration_page_url( '', $product_id );
			}

			if ( ! empty( $bundled ) ) {
				$parent_id         = $bundled[0];
				$registration_page = bsf_registration_page_url( '', $parent_id );
				$parent_name       = bsf_get_white_lable_product_name( $parent_id, brainstrom_product_name( $parent_id ) );
				/* translators: %1$s: $parent_name %2%s: $registration_page */
				$message = sprintf( __( ' <br>This plugin is came bundled with the <i>%1$s</i>. For receiving updates, you need to register license of <i>%2$s</i> <a href="%3$s">here</a>.', 'astra-addon' ), $parent_name, $parent_name, $registration_page );
			} else {
				/* translators: %1$s: $registration_page %2%s: search term */
				$message = sprintf( ' <i>%s</i>', sprintf( __( 'Please <a href="%1$s">activate your license</a> to update the plugin.', 'astra-addon' ), $registration_page ) );
			}

			if ( true === self::bsf_allow_beta_updates( $product_id ) && $this->is_beta_version( $plugin_data['new_version'] ) ) {
				$message = $message . ' <i>It is recommended to use the beta version on a staging enviornment only.</i>';
			}

			echo wp_kses_post( $message );
		}
		/**
		 * Add Beta update message.
		 *
		 * @param array $plugin_data plugin data.
		 * @param array $response Response.
		 */
		public function add_beta_update_message( $plugin_data, $response ) {
			$plugin_init = isset( $plugin_data['plugin'] ) ? $plugin_data['plugin'] : '';

			if ( '' !== $plugin_init ) {
				$product_id = brainstrom_product_id_by_init( $plugin_init );
			} else {
				$product_id = brainstrom_product_id_by_name( $plugin_name );
			}

			if ( true === self::bsf_allow_beta_updates( $product_id ) && $this->is_beta_version( $plugin_data['new_version'] ) ) {
				echo ' <i>It is recommended to use the beta version on a staging enviornment only.</i>';
			}
		}
		/**
		 * Is Beta version
		 *
		 * @param string $version Version.
		 * @return bool.
		 */
		private function is_beta_version( $version ) {
			return strpos( $version, 'beta' ) ||
				strpos( $version, 'alpha' );
		}

		/**
		 * Modify download package message to hide download URL.
		 *
		 * @param string $reply Reply.
		 * @param string $package Package.
		 * @param string $current Current.
		 * @return string $reply.
		 */
		public function modify_download_package_message( $reply, $package, $current ) {

			// Read atts into separate veriables so that easy to reference below.
			$strings = $current->strings;

			if ( isset( $current->skin->plugin_info ) ) {
				$plugin_info = $current->skin->plugin_info;

				if ( ( isset( $plugin_info['author'] ) && 'Brainstorm Force' === $plugin_info['author'] ) || ( isset( $plugin_info['AuthorName'] ) && 'Brainstorm Force' === $plugin_info['AuthorName'] ) ) {
					$strings['downloading_package'] = __( 'Downloading the update...', 'astra-addon' );
				}
			} elseif ( isset( $current->skin->theme_info ) ) {

				$theme_info   = $current->skin->theme_info;
				$theme_author = $theme_info->get( 'Author' );

				if ( 'Brainstorm Force' === $theme_author ) {
					$strings['downloading_package'] = __( 'Downloading the update...', 'astra-addon' );
				}
			} elseif ( isset( $_GET['action'] ) && 'bsf_rollback' === $_GET['action'] && check_admin_referer( 'bsf_rollback' ) ) {
				// In case of rollback version.
				$strings['downloading_package'] = __( 'Downloading the update...', 'astra-addon' );
			}

			// restore the strings back to WP_Upgrader.
			$current->strings = $strings;

			// We are not changing teh return parameter.
			return $reply;
		}

		/**
		 * Install Pluigns Filter
		 *
		 * Add brainstorm bundle products in plugin installer list though filter.
		 *
		 * @since 1.0.0
		 *
		 * @param  array $brainstrom_products   Brainstorm Products.
		 * @return array                        Brainstorm Products merged with Brainstorm Bundle Products.
		 */
		public function plugin_information( $brainstrom_products = array() ) {

			$main_products = (array) get_option( 'brainstrom_bundled_products', array() );

			foreach ( $main_products as $single_product_key => $single_product ) {
				foreach ( $single_product as $bundle_product_key => $bundle_product ) {

					if ( is_object( $bundle_product ) ) {
						$type = $bundle_product->type;
						$slug = $bundle_product->slug;
					} else {
						$type = $bundle_product['type'];
						$slug = $bundle_product['slug'];
					}

					// Add bundled plugin in installer list.
					if ( 'plugin' === $type ) {
						$brainstrom_products['plugins'][ $slug ] = (array) $bundle_product;
					}
				}
			}

			return $brainstrom_products;
		}

		/**
		 * Get the prurchase key for product download API.
		 * If the product is bundeled then return it's parent product purchase key.
		 *
		 * @param string $product_id Product ID.
		 * @return string Purchase Key.
		 */
		public function get_purchse_key( $product_id ) {
			$all_products = brainstorm_get_all_products();
			$is_bundled   = self::bsf_is_product_bundled( $product_id );
			$product      = isset( $all_products[ $product_id ] ) ? $all_products[ $product_id ] : array();

			if ( ! empty( $is_bundled ) ) {
				$product = isset( $all_products[ $product['parent'] ] ) ? $all_products[ $product['parent'] ] : array();
			}

			return isset( $product['purchase_key'] ) ? $product['purchase_key'] : '';
		}
	} // class BSF_Update_Manager

	new BSF_Update_Manager();
}
