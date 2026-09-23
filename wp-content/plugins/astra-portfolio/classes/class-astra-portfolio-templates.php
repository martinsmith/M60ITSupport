<?php
/**
 * Astra Portfolio Templates
 *
 * @package Astra Portfolio
 * @since 1.0.6
 */

if ( ! class_exists( 'Astra_Portfolio_Templates' ) ) :

	/**
	 * Astra_Portfolio_Templates
	 *
	 * @since 1.0.6
	 */
	class Astra_Portfolio_Templates {

		/**
		 * Instance
		 *
		 * @since 1.0.6
		 *
		 * @access private
		 * @var object Class object.
		 */
		private static $instance;

		/**
		 * Template path
		 *
		 * @since 1.0.6
		 *
		 * @access private
		 * @var string Template path.
		 */
		private static $template_path;

		/**
		 * Default path
		 *
		 * @since 1.0.6
		 *
		 * @access private
		 * @var string Default path.
		 */
		private static $default_path;

		/**
		 * Filter Prefix
		 *
		 * @since 1.0.6
		 *
		 * @access private
		 * @var string Filter prefix.
		 */
		private static $hook_prefix;

		/**
		 * Initiator
		 *
		 * @since 1.0.6
		 *
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.6
		 */
		public function __construct() {
			self::$template_path = 'astra-portfolio/';
			self::$default_path  = ASTRA_PORTFOLIO_DIR . 'includes/';
			self::$hook_prefix   = 'astra_portfolio';
		}

		/**
		 * Get template part (for templates like the shop-loop).
		 *
		 * Filter `self::$hook_prefix . '_template_debug_mode'` will prevent overrides in themes from taking priority.
		 *
		 * @since 1.0.6
		 *
		 * @access public
		 * @param mixed  $slug Template slug.
		 * @param string $name Template name (default: '').
		 */
		public function get_template_part( $slug, $name = '' ) {
			$template = '';

			// Look in yourtheme/slug-name.php and yourtheme/{self::$template_path}/slug-name.php.
			if ( $name && ! apply_filters( self::$hook_prefix . '_template_debug_mode', false ) ) {
				$template = locate_template( array( "{$slug}-{$name}.php", self::$template_path . "{$slug}-{$name}.php" ) );
			}

			// Get default slug-name.php.
			if ( ! $template && $name && file_exists( self::$default_path . "{$slug}-{$name}.php" ) ) {
				$template = self::$default_path . "{$slug}-{$name}.php";
			}

			// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/{self::$template_path}/slug.php.
			if ( ! $template && ! apply_filters( self::$hook_prefix . '_template_debug_mode', false ) ) {
				$template = locate_template( array( "{$slug}.php", self::$template_path . "{$slug}.php" ) );
			}

			// Allow 3rd party plugins to filter template file from their plugin.
			$template = apply_filters( self::$hook_prefix . '_get_template_part', $template, $slug, $name );

			if ( $template ) {
				load_template( $template, false );
			}
		}

		/**
		 * Get other templates (e.g. product attributes) passing attributes and including the file.
		 *
		 * @since 1.0.6
		 * @access public
		 * @param string $template_name Template name.
		 * @param array  $args          Arguments. (default: array).
		 * @param string $template_path Template path. (default: '').
		 * @param string $default_path  Default path. (default: '').
		 */
		public function get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
			if ( ! empty( $args ) && is_array( $args ) ) {
				// Set template variables explicitly instead of using extract().
				// Only protect variables critical to this method's execution flow.
				// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Replaced with safe alternative.
				$__protected = array( 'template_name', 'template_path', 'default_path', 'located', '__protected', '__key', '__value' );
				foreach ( $args as $__key => $__value ) {
					if ( ! in_array( $__key, $__protected, true ) ) {
						$$__key = $__value;
					}
				}
				unset( $__key, $__value, $__protected );
			}

			$located = $this->locate_template( $template_name, $template_path, $default_path );

			if ( ! file_exists( $located ) ) {
				/* translators: %s template */
				$this->doing_it_wrong( __FUNCTION__, sprintf( __( '%s does not exist.', 'astra-portfolio' ), '<code>' . $located . '</code>' ), '2.1' );
				return;
			}

			// Allow 3rd party plugin filter template file from their plugin.
			$located = apply_filters( self::$hook_prefix . '_get_template', $located, $template_name, $args, $template_path, $default_path );

			do_action( self::$hook_prefix . '_before_template_part', $template_name, $template_path, $located, $args );

			include $located;

			do_action( self::$hook_prefix . '_after_template_part', $template_name, $template_path, $located, $args );
		}

		/**
		 * Locate a template and return the path for inclusion.
		 *
		 * This is the load order:
		 *
		 * yourtheme/$template_path/$template_name
		 * yourtheme/$template_name
		 * $default_path/$template_name
		 *
		 * @access public
		 * @param string $template_name Template name.
		 * @param string $template_path Template path. (default: '').
		 * @param string $default_path  Default path. (default: '').
		 * @return string
		 */
		public function locate_template( $template_name, $template_path = '', $default_path = '' ) {
			if ( ! $template_path ) {
				$template_path = self::$template_path;
			}

			if ( ! $default_path ) {
				$default_path = self::$default_path;
			}

			// Look within passed path within the theme - this is priority.
			$template = locate_template(
				array(
					trailingslashit( $template_path ) . $template_name,
					$template_name,
				)
			);

			// Get default template/.
			if ( ! $template || apply_filters( self::$hook_prefix . '_template_debug_mode', false ) ) {
				$template = $default_path . $template_name;
			}

			// Return what we found.
			return apply_filters( self::$hook_prefix . '_locate_template', $template, $template_name, $template_path );
		}

		/**
		 * Like get_template, but returns the HTML instead of outputting.
		 *
		 * @see get_template
		 * @since 1.0.6
		 * @param string $template_name Template name.
		 * @param array  $args          Arguments. (default: array).
		 * @param string $template_path Template path. (default: '').
		 * @param string $default_path  Default path. (default: '').
		 *
		 * @return string
		 */
		public function get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
			ob_start();
			$this->get_template( $template_name, $args, $template_path, $default_path );
			return ob_get_clean();
		}

		/**
		 * Wrapper for doing_it_wrong.
		 *
		 * @since 1.0.6
		 * @param string $function Function used.
		 * @param string $message Message to log.
		 * @param string $version Version the message was added in.
		 */
		public function doing_it_wrong( $function, $message, $version ) {
			// @codingStandardsIgnoreStart
			$message .= ' Backtrace: ' . $this->debug_backtrace_summary();

			if ( is_ajax() ) {
				do_action( 'doing_it_wrong_run', $function, $message, $version );
				astra_portfolio_log( "{$function} was called incorrectly. {$message}. This message was added in version {$version}." );
			} else {
				_doing_it_wrong( $function, $message, $version );
			}
			// @codingStandardsIgnoreEnd
		}

		/**
		 * Return a comma-separated string of functions that have been called to get
		 * to the current point in code.
		 *
		 * @since 1.0.6
		 *
		 * @see https://core.trac.wordpress.org/ticket/19589
		 *
		 * @param string $ignore_class Optional. A class to ignore all function calls within - useful
		 *                             when you want to just give info about the callee. Default null.
		 * @param int    $skip_frames  Optional. A number of stack frames to skip - useful for unwinding
		 *                             back to the source of the issue. Default 0.
		 * @param bool   $pretty       Optional. Whether or not you want a comma separated string or raw
		 *                             array returned. Default true.
		 * @return string|array Either a string containing a reversed comma separated trace or an array
		 *                      of individual calls.
		 */
		public function debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {
			if ( version_compare( PHP_VERSION, '5.2.5', '>=' ) ) {
				$trace = debug_backtrace( false ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			} else {
				$trace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			}

			$caller      = array();
			$check_class = ! is_null( $ignore_class );
			$skip_frames++; // skip this function.

			foreach ( $trace as $call ) {
				if ( $skip_frames > 0 ) {
					$skip_frames--;
				} elseif ( isset( $call['class'] ) ) {
					if ( $check_class && ( $ignore_class === $call['class'] ) ) {
						continue; // Filter out calls.
					}

					$caller[] = "{$call['class']}{$call['type']}{$call['function']}";
				} else {
					if ( in_array( $call['function'], array( 'do_action', 'apply_filters' ), true ) ) {
						$caller[] = "{$call['function']}('{$call['args'][0]}')";
					} elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ), true ) ) {
						$caller[] = $call['function'] . "('" . str_replace( array( WP_CONTENT_DIR, ABSPATH ), '', $call['args'][0] ) . "')";
					} else {
						$caller[] = $call['function'];
					}
				}
			}
			if ( $pretty ) {
				return join( ', ', array_reverse( $caller ) );
			} else {
				return $caller;
			}
		}

	}

	/**
	 * Initialize class object with 'get_instance()' method
	 */
	Astra_Portfolio_Templates::get_instance();

endif;

if ( ! function_exists( 'astra_portfolio_get_template_html' ) ) :

	/**
	 * Wrapper function of `get_template_html()` function
	 *
	 * @see get_template
	 * @since 1.0.6
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments. (default: array).
	 * @param string $template_path Template path. (default: '').
	 * @param string $default_path  Default path. (default: '').
	 *
	 * @return string
	 */
	function astra_portfolio_get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		return Astra_Portfolio_Templates::get_instance()->get_template_html( $template_name, $args, $template_path, $default_path );
	}

endif;

if ( ! function_exists( 'astra_portfolio_get_template_part' ) ) :

	/**
	 * Wrapper function of `get_template_part()` function
	 *
	 * Filter `self::$hook_prefix . '_template_debug_mode'` will prevent overrides in themes from taking priority.
	 *
	 * @since 1.0.6
	 *
	 * @access public
	 * @param mixed  $slug Template slug.
	 * @param string $name Template name (default: '').
	 */
	function astra_portfolio_get_template_part( $slug, $name = '' ) {
		Astra_Portfolio_Templates::get_instance()->get_template_part( $slug, $name );
	}

endif;

if ( ! function_exists( 'astra_portfolio_get_template' ) ) :

	/**
	 * Wrapper function of `get_template()` function
	 *
	 * @since 1.0.6
	 *
	 * @access public
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments. (default: array).
	 * @param string $template_path Template path. (default: '').
	 * @param string $default_path  Default path. (default: '').
	 */
	function astra_portfolio_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		Astra_Portfolio_Templates::get_instance()->get_template( $template_name, $args, $template_path, $default_path );
	}

endif;
