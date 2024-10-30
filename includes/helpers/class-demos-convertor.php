<?php

/**
 * Styler Elementor Convertor Class.
 *
 * @author  ClimaxThemes
 * @package Styler
 * @since   1.3.0
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ob_start();
use Styler\StyleSheet as StyleSheetManager;

/**
 * Class DemoConvertorWebService
 *
 * This class is responsible for converting data into a specific format for the Styler application.
 * It uses a singleton pattern to ensure only one instance of the class exists.
 */
class DemoConvertorWebService {


	private static $instance = null;
	private static $data     = array();
	private static $counter  = 0;

	private function __construct() {
		ini_set( 'xdebug.var_display_max_depth', '-1' );
		ini_set( 'xdebug.var_display_max_children', '-1' );
		ini_set( 'xdebug.var_display_max_data', '-1' );
		ini_set( 'display_errors', '0' );
		ini_set( 'display_startup_errors', '1' );
		error_reporting( E_ALL );

		if ( isset( $_REQUEST['update-demo'] ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'convertAndSaveData' ), 99999999999999999999 );
		}

		if ( isset( $_REQUEST['update-template'] ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'convertTemplateAndSaveData' ), 99999999999999999999 );
		}

		if ( isset( $_REQUEST['update-presets'] ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'presetsConvertAndSaveData' ), 99999999999999999999 );
		}

		if ( isset( $_REQUEST['update-customizer'] ) ) {

			$data = file_get_contents( 'php://input' );

			$customizer_data = json_decode( $data, true );

			$customizer_data = maybe_unserialize( $customizer_data['data'] );

			foreach ( $customizer_data as $key => $value ) {

				if ( strpos( $key, 'styler_' ) === false ) {
					continue;
				}

				foreach ( $value as $id => $styler ) {
					if ( is_array( $styler ) || is_object( $styler ) ) {
						$styler = str_replace(
							array( 'climaxthemes.com\/kata\/', 'climaxthemes.com/kata/', 'climaxthemes.com/kata-blog/', 'climaxthemes.com\/kata-blog\/', 'climaxthemes.com\\\/kata-blog', 'climaxthemes.com\\\/kata' ),
							array( 'katademos.com\/', 'katademos.com/', 'katademos.com/kata-blog/', 'katademos.com\/kata-blog\/', 'katademos.com\/kata-blog\/', 'katademos.com' ),
							json_encode( $styler )
						);
						$styler = json_decode( $styler, true );
					} else {
						$styler = str_replace(
							array( 'climaxthemes.com\/kata\/', 'climaxthemes.com/kata/', 'climaxthemes.com/kata-blog/', 'climaxthemes.com\/kata-blog\/', 'climaxthemes.com\\\/kata-blog', 'climaxthemes.com\\\/kata' ),
							array( 'katademos.com\/', 'katademos.com/', 'katademos.com/kata-blog/', 'katademos.com\/kata-blog\/', 'katademos.com\/kata-blog\/', 'katademos.com' ),
							$styler
						);
					}

					$pattern = '/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([01]?(\.\d+)?))?\s*\)|#([0-9a-fA-F]{3,6})|rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/';

					if( ! class_exists( '\SavvyWombat\Color\Color' ) ) {
						require Kata_Plus::$dir . '/vendor/autoload.php';
					}

					$convertedText = preg_replace_callback($pattern, function( $matches ) {
						$matches[0] = preg_replace( '/\.\d+,/', ',', $matches[0]);
						$matches[0] = str_replace(" ", "", $matches[0]);
						$matches[0] = str_replace(",.", ",0.", $matches[0]);


						return (string) \SavvyWombat\Color\Color::fromString($matches[0])->toHex();
					}, is_array( $styler ) || is_object( $styler ) ? json_encode( $styler ) : $styler );

					$updatedStructure = $this->UpdateStructure( $id, json_decode( $convertedText, true ) );

					foreach ( $updatedStructure as $d => $dv ) {
						foreach ( $dv as $p => $pv ) {

							if ( ! $pv['data'] ) {
								unset( $updatedStructure[ $d ][ $p ] );
							}
						}
					}

					foreach ( $updatedStructure as $d => $dv ) {
						if ( ! $dv ) {
							unset( $updatedStructure[ $d ] );
						}
					}

					$cid = preg_replace( '/([styler_]){7}/s', '', $id, 1 );
					if ( $updatedStructure ) {
						$customizer_data[ $key ][ $id ] = array(
							'cid'    => $cid,
							'stdata' => $updatedStructure,
						);
					} else {
						unset( $customizer_data[ $key ][ $id ] );
					}
				}
			}

			if ( isset( $customizer_data['wp_css'] ) ) {
				$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $customizer_data['wp_css'] );
				// Remove spaces before and after selectors, braces, and colons
				$css = preg_replace( '/\s*([{}|:;,])\s+/', '$1', $css );
				// Remove remaining spaces and line breaks
				$css = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ), '', $css );

				$customizer_data['wp_css'] = $css;
			}

			wp_send_json(
				array(
					'data' => serialize( $customizer_data ),
				)
			);
		}
		// Private to prevent instantiation
	}

	/**
	 * Update the structure of the data based on the given option name and data.
	 *
	 * @param string $optionName
	 * @param array  $data
	 * @return array
	 */
	private function UpdateStructure( $optionName, $data ) {
		$breakpoints = array(
			'desktop',
			'laptop',
			'tablet',
			'tabletlandscape',
			'mobile',
			'smallmobile',
		);

		$actions = array(
			'hover',
			'phover',
			'before',
			'after',
			'placeholder',
		);

		$selectors = array(
			'kata_gdpr_agree_style'          => 'body .kata-gdpr-box button',
			'kata_gdpr_pp_style'             => 'body .kata-gdpr-box .gdpr-button-privacy a',
			'kata_gdpr_content_style'        => 'body .kata-gdpr-box .gdpr-content-wrap p',
			'kata_gdpr_box_style'            => 'body .kata-gdpr-box',
			'styler_kata_preloader'          => 'body .kata-preloader-screen, body .kata-arc-scale .kata-loader .kata-arc, body .kata-arc-scale .kata-loader .kata-arc',
			'kata_back_to_top_wrap'          => 'body #scroll-top .scrollup',
			'kata_back_to_top_wrap_icon'     => 'body #scroll-top .scrollup i',
			'kata_scrollbar_cursor_color'    => 'body .nicescroll-rails .nicescroll-cursors',
			'kata_scrollbar_rail_background' => 'body .nicescroll-rails',
			'kata_container'                 => 'body .container, body .elementor-section.elementor-section-boxed>.elementor-container',
			'kata_container'                 => 'body',
			'kata_page_title_wrapper'        => 'body.page #kata-page-title',
			'kata_page_title'                => 'body.page  #kata-page-title h1',
			'kata_blog_title_wrapper'        => 'body.blog  #kata-page-title',
			'kata_blog_title'                => 'body.blog #kata-page-title h1.kata-archive-page-title',
			'kata_archive_title_wrapper'     => 'body.archive #kata-page-title',
			'kata_archive_title'             => 'body.archive #kata-page-title h1.kata-archive-page-title',
			'kata_archive_title_part1'       => 'body.archive #kata-page-title h1.kata-archive-page-title .kt-tax-name',
			'kata_archive_title_part2'       => 'body.archive #kata-page-title h1.kata-archive-page-title .kt-tax-title',
			'kata_author_title_wrapper'      => '.author #kata-page-title',
			'kata_author_title'              => '.author #kata-page-title h1.kata-archive-page-title',
			'kata_author_title_part1'        => '.author #kata-page-title h1.kata-archive-page-title .kt-tax-name',
			'kata_author_title_part2'        => '.author #kata-page-title h1.kata-archive-page-title .vcard',
			'kata_search_title_wrapper'      => '.search #kata-page-title',
			'kata_search_title'              => '.search #kata-page-title h1.kata-archive-page-title',
			'kata_search_title_part1'        => '.search #kata-page-title h1.kata-archive-page-title .kt-tax-name',
			'kata_search_title_part2'        => '.search #kata-page-title h1.kata-archive-page-title .kt-search-title',
			'body_tag'                       => 'body',
			'all_heading'                    => 'h1, h2, h3, h4, h5, h6',
			'h1_tag'                         => 'body h1',
			'h2_tag'                         => 'body h2',
			'h3_tag'                         => 'body h3',
			'h4_tag'                         => 'body h4',
			'h5_tag'                         => 'body h5',
			'h6_tag'                         => 'body h6',
			'p_tag'                          => 'body p',
			'blockquote_tag'                 => 'body blockquote',
			'a_tag'                          => 'body a',
			'img_tag'                        => 'body .elementor img, img',
			'button_element'                 => 'body a.kata-button',
		);

		$result = array();
		foreach ( $breakpoints as $key ) {

			$selectorKey = preg_replace( '/([styler_]){7}/s', '', $optionName, 1 );

			if ( isset( $data[ $key ] ) && ! empty( $data[ $key ] ) ) {

				if( $key === 'mobile' ) {
					$key = 'mobileLandscape';
				} else if ( $key === 'smallmobile' ) {
					$key = 'mobile';
				}

				$result[ $key ] = array(

					'normal' => array(
						'selector' => $selectors[ $selectorKey ],
						'data'     => $this->setupData( $data[ $key ] ),
					),
				);
			}

			foreach ( $actions as $action ) {
				$newAction = $action === 'phover' ? 'parent-hover' : $action;

				$explodedSelectors = explode( ',', @$selectors[ $selectorKey ] );
				$fixedSelector     = '';
				if ( $action !== 'phover' ) {
					foreach ( $explodedSelectors as $index => $explodedSelector ) {
						$explodedSelectors[ $index ] = trim( $explodedSelector ) . ':' . $action;
					}
					$fixedSelector = implode( ', ', $explodedSelectors );
				}

				if ( isset( $data[ $key . $action ] ) && $data[ $key . $action ] ) {
					if ( ! isset( $result[ $key ] ) ) {
						$result[ $key ] = array();
					}
					$result[ $key ] [ $newAction ] = array(
						'data'     => $this->setupData( $data[ $key . $action ] ),
						'selector' => $fixedSelector,
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Get the instance of the DemoConvertorWebService class.
	 *
	 * @return DemoConvertorWebService
	 */
	public static function getInstance() {
		if ( self::$instance == null ) {
			self::$instance = new DemoConvertorWebService();
		}

		return self::$instance;
	}

	/**
	 * Convert and save Elementor data for all posts.
	 *
	 * This function retrieves all post types and performs a query to get all posts with the '_elementor_data' meta key.
	 * It then loops through each post and retrieves the old data from the meta. If the old data is not empty, it checks if it contains the string 'styler'.
	 * If it does, it checks if the post has already been updated by checking the '_elementor_data_updated' meta key.
	 * If the post has not been updated, it calls the 'convertData' method to convert the old data, saves the converted data using the 'saveData' method,
	 * updates the '_elementor_data_updated' meta key, and outputs a
	 */
	public function presetsConvertAndSaveData() {

		ob_clean();
		$json_data = file_get_contents( 'php://input' );

		// Decode the JSON data into a PHP associative array
		$data = json_decode( $json_data, true );

		// Extract the data from the named field "data"
		foreach ( $data['data'] as $key => $templateData ) {
			$convertedData          = $this->convertData( $templateData, 5986 );
			$convertedData          = $this->saveData( $convertedData );
			$updatedTemplateContent = $this->doUpdateInnerElements( $templateData, $convertedData );
			$data['data'][ $key ]   = $updatedTemplateContent;
		}

		wp_send_json( $data['data'] );
		die();
	}

	public function convertAndSaveData() {

		ob_clean();
		$json_data = file_get_contents( 'php://input' );

		// $json_data = file_get_contents( __DIR__ . '/1.json' );

		$json_data = str_replace(
			array( 'climaxthemes.com\/kata\/', 'climaxthemes.com/kata/', 'climaxthemes.com/kata-blog/', 'climaxthemes.com\/kata-blog\/', 'climaxthemes.com\\\/kata-blog', 'climaxthemes.com\\\/kata' ),
			array( 'katademos.com\/', 'katademos.com/', 'katademos.com/kata-blog/', 'katademos.com\/kata-blog\/', 'katademos.com\/kata-blog\/', 'katademos.com' ),
			$json_data
		);

		// Decode the JSON data into a PHP associative array
		$data = json_decode( $json_data, true );

		// Extract the data from the named field "data"
		$templateData = $data['data'];
		// $templateData = $data;

		if ( is_serialized( $templateData ) ) {
			$templateData = maybe_unserialize( $templateData );
		} else {
			$templateData = json_decode( $templateData, true );
		}

		$convertedData = $this->convertData( $templateData, 5986 );

		$convertedData = $this->saveData( $convertedData );

		if ( ! is_object( $templateData ) && ! is_array( $templateData ) ) {
			$templateData = json_decode( $templateData, true );
		}

		$updatedTemplateContent = $this->doUpdateInnerElements( $templateData, $convertedData );

		wp_send_json( $updatedTemplateContent );
		die();
	}

	public function convertTemplateAndSaveData() {

		ob_clean();
		$json_data = file_get_contents( 'php://input' );

		$json_data = str_replace(
			array( 'climaxthemes.com\/kata\/', 'climaxthemes.com/kata/', 'climaxthemes.com/kata-blog/', 'climaxthemes.com\/kata-blog\/' ),
			array( 'katademos.com\/', 'katademos.com/', 'katademos.com/kata-blog/', 'katademos.com\/kata-blog\/' ),
			$json_data
		);

		// Decode the JSON data into a PHP associative array
		$data = json_decode( $json_data, true );

		// Extract the data from the named field "data"
		$templateData = $data['data'];

		if( ! is_array( $templateData ) ) {
			$templateData = json_decode( $templateData, true );
		}

		if( isset( $templateData['page_settings']['kata_styler_page_title'] ) ) {
			unset( $templateData['page_settings']['kata_styler_page_title'] );
		}

		if( isset( $templateData['page_settings']['styler_body'] ) ) {
			unset( $templateData['page_settings']['styler_body'] );
		}

		$convertedData = $this->convertData( $templateData['content'], 5986 );
		$convertedData = $this->saveData( $convertedData );

		$updatedTemplateContent  = $this->doUpdateInnerElements( $templateData['content'], $convertedData );

		$templateData['content'] = $updatedTemplateContent;

		wp_send_json( $templateData );
		die();
	}

	/**
	 * String is Contain
	 *
	 * @param $string string to search
	 * @param $search search for charachter
	 * @since   1.3.0
	 */
	public function string_is_contain( $string, $search ) {

		if ( strpos( $string, $search ) !== false ) {
			return true;
		} else {
			return false;
		}
	}

	// Regular expression to match consecutive slashes that are not part of a URL
	private function removeUnwantedSlashes( $input ) {
		// Split the input string by URLs using a regex pattern
		$pattern = '/(https?:\/\/[^\s\/]+)/';
		$parts   = preg_split( $pattern, $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		// Remove consecutive slashes from non-URL parts
		foreach ( $parts as &$part ) {
			if ( ! preg_match( $pattern, $part ) ) {
				$part = preg_replace( '/\/+/', '/', $part );
			}
		}

		// Reconstruct the string
		$result = implode( '', $parts );

		// Remove leading and trailing slashes
		$result = trim( $result, '/' );

		return $result;
	}

	/**
	 * Setup the data by replacing semicolons with a delimiter and then splitting the properties into an valid new data array.
	 *
	 * @param string $properties
	 * @return array
	 */
	private function setupData( $properties ) {

		$properties = str_replace( array( ';' ), array( '^^^^' ), $properties );
		$properties = explode( '^^^^', $properties );

		$data = array();

		foreach ( $properties as $key => $property ) {

			$property = $this->removeUnwantedSlashes( $property );
			// resole http: and https: in background property
			$property = str_replace( array( 'http:', 'https:' ), '', $property );
			$property = explode( ':', $property, 2 );

			if ( ! isset( $property[1] ) ) {
				continue;
			}

			$property[1] = trim( $property[1] );
			if ( is_array( $property ) && ! empty( $property[0] ) ) {
				if ( $property[0] === 'outline' && trim( $property[1] ) === 'notset' ) {
					continue;
				}
				// fix background image index
				if ( $property[0] == 'background-image' ) {
					if ( $this->string_is_contain( $property[1], 'url(' ) ) {
						$index       = 'background-image';
						$value       = $property[1];
						$replace_val = $this->get_string_between( $value, '//', 'wp-content' );
						// $value       = str_replace( $replace_val, get_home_url() . '/', $value );
						$value = str_replace( '//http', 'http', $value );

						$image_url = ltrim( trim( $value ), 'url(' );
						$image_url = rtrim( trim( $image_url ), ')' );

						if ( attachment_url_to_postid( $image_url ) ) {
							$data['background-id'] = array(
								'value'     => attachment_url_to_postid( $image_url ),
								'important' => false,
							);
						}

						$data['background'] = array(
							'value'     => 'url(' . $image_url . ')',
							'important' => false,
						);

						$data['background-type'] = array(
							'value'     => 'classic',
							'important' => false,
						);
					}
					// fix background gradient
					if ( $this->string_is_contain( $property[1], 'linear-gradient' ) ) {
						$index = $property[0];
						$value = $property[1];

						$gradient = ltrim( $value, 'linear-gradient(' );
						$gradient = rtrim( $gradient, ')' );
						$gradient = explode( ',', $gradient );

						$data['background-image'] = array(
							'value'     => $value,
							'important' => false,
						);

						$data['background-type'] = array(
							'value'     => 'gradient',
							'important' => false,
						);

						$data['gradient-first-color']  = array(
							'value'     => $gradient[1],
							'important' => false,
						);
						$data['gradient-second-color'] = array(
							'value'     => $gradient[2],
							'important' => false,
						);
						$data['gradient-angle']        = array(
							'value'     => $gradient[0],
							'important' => false,
						);
					}
				} elseif ( $property[0] == 'background-position' ) {
					$index                       = $property[0];
					$value                       = $property[1];
					$data['background-position'] = array(
						'value'     => str_replace( array( ' !important', '!important' ), '', $value ),
						'important' => false,
					);

					$data['background-position-type'] = array(
						'value'     => str_replace( array( ' !important', '!important' ), '', $value ),
						'important' => false,
					);
				} elseif ( $property[0] == 'background-size' ) {
					$index                        = $property[0];
					$value                        = $property[1];
					$data['background-size-type'] = array(
						'value'     => str_replace( array( ' !important', '!important' ), '', $value ),
						'important' => false,
					);
					$data['background-size']      = array(
						'value'     => str_replace( array( ' !important', '!important' ), '', $value ),
						'important' => false,
					);
				} elseif ( $property[0] == '-webkit-background-clip' ) {
					$data['use-as-color']            = array(
						'value'     => 'yes',
						'important' => false,
					);
					$data['-webkit-background-clip'] = array(
						'value'     => 'text',
						'important' => false,
					);
				} elseif ( $property[0] == 'font-family' ) {
					$important = $this->string_is_contain( $property[1], '!important' ) ? true : false;
					$value     = str_replace( array( ' !important', '!important' ), '', $property[1] );
					$value     = str_replace( array( '\\' ,'/', '"' ), array( '', '', "'" ), $value );
					if( $value ) {
						$data[ 'font-family' ] = array(
							'value'     => $value,
							'important' => $important,
						);
					}
				} elseif ( $property[0] == 'transform' ) {
					$index  = $property[0];
					$value  = str_replace( ', ', ',', $property[1] );
					$value  = explode( ' ', $value );
					$newVal = array();
					foreach ( $value as $transform_key => $transform_item ) {
						if ( $this->string_is_contain( $transform_item, 'skew' ) ) {
							$transform_item    = ltrim( $transform_item, 'skew(' );
							$transform_item    = rtrim( $transform_item, ',' );
							$transform_item    = rtrim( $transform_item, ')' );
							$transform_item    = explode( ',', $transform_item );
							$transform_item[0] = 'skewX(' . $transform_item[0] . ')';
							if ( $transform_item[1] ) {
								$transform_item[1] = 'skewY(' . $transform_item[1] . ')';
							}
							$transform_item          = implode( ' ', $transform_item );
							$value[ $transform_key ] = $transform_item;
						}
						if ( $this->string_is_contain( $transform_item, 'translate(' ) ) {
							$transform_item    = ltrim( $transform_item, 'translate(' );
							$transform_item    = rtrim( $transform_item, ',' );
							$transform_item    = rtrim( $transform_item, ')' );
							$transform_item    = explode( ',', $transform_item );
							$transform_item[0] = 'translateX(' . $transform_item[0] . ')';
							if ( $transform_item[1] ) {
								$transform_item[1] = 'translateY(' . $transform_item[1] . ')';
							}
							$transform_item          = implode( ' ', $transform_item );
							$value[ $transform_key ] = $transform_item;
						}
					}
					$value             = implode( ' ', $value );
					$data['transform'] = array(
						'value'     => $value,
						'important' => strpos( $property[1], '!important' ) ? true : false,
					);
				} elseif ( $property[0] == 'box-shadow' ) {
					// Box Shadow object_value
					$obj_val     = explode( ',', $property[1] . ',' );
					$obj_val_out = array();

					$i = 0;
					foreach ( $obj_val as $j => $vals ) {
						$obj_vals = explode( ' ', trim($vals) );

						// $i = $j == 0 ? 1 : rand( 1, 999 );

						if ( sizeof( $obj_vals ) == 6 ) {
							$obj_val_out[ $i ] = array(
								'x'      => $obj_vals[0],
								'y'      => $obj_vals[1],
								'blur'   => $obj_vals[2],
								'spread' => $obj_vals[3],
								'color'  => $obj_vals[4],
								'inset'  => $obj_vals[5] === 'inset' ? true : false,
							);
						} elseif ( sizeof( $obj_vals ) == 5 ) {
							$obj_val_out[ $i ] = array(
								'x'      => $obj_vals[0],
								'y'      => $obj_vals[1],
								'blur'   => $obj_vals[2],
								'spread' => $obj_vals[3],
								'color'  => $obj_vals[4],
								'inset'  => false,
							);
						} elseif ( sizeof( $obj_vals ) == 4 ) {
							$obj_val_out[ $i ] = array(
								'x'      => $obj_vals[0],
								'y'      => $obj_vals[1],
								'blur'   => $obj_vals[2],
								'spread' => '',
								'color'  => $obj_vals[3],
								'inset'  => false,
							);
						} elseif ( sizeof( $obj_vals ) == 3 ) {
							$obj_val_out[ $i ] = array(
								'x'      => $obj_vals[0],
								'y'      => $obj_vals[1],
								'blur'   => $obj_vals[2],
								'spread' => '',
								'color'  => '',
								'inset'  => false,
							);
						} elseif ( sizeof( $obj_vals ) == 3 ) {
							$obj_val_out[ $i ] = array(
								'x'      => $obj_vals[0],
								'y'      => $obj_vals[1],
								'blur'   => '',
								'spread' => '',
								'color'  => '',
								'inset'  => false,
							);
						}
						$i++;
					}

					$data['box-shadow']['value']        = $property[1];
					$data['box-shadow']['object_value'] = $obj_val_out;

				} elseif ( $property[0] == 'content' ) {

					$value = str_replace( '"', "'", $property[1] );
					$important = $this->string_is_contain( $value, '!important' ) ? true : false;
					$value     = str_replace( array( ' !important', '!important' ), '', $value );

					$data['content'] = array(
						'value'     => $value,
						'important' => $important,
					);

				} elseif ( $property[0] == 'text-shadow' ) {

					// Box Shadow object_value
					$obj_val     = explode( ',', $property[1] . ',' );
					$obj_val_out = array();

					$i = 0;
					foreach ( $obj_val as $j => $vals ) {
						$obj_vals = explode( ' ', trim($vals) );

						if ( sizeof( $obj_vals ) == 4 ) {
							$obj_val_out[ $i ] = array(
								'x'     => $obj_vals[0],
								'y'     => $obj_vals[1],
								'blur'  => $obj_vals[2],
								'color' => $obj_vals[3],
							);
						} elseif ( sizeof( $obj_vals ) == 3 ) {
							$obj_val_out[ $i ] = array(
								'x'     => $obj_vals[0],
								'y'     => $obj_vals[1],
								'blur'  => $obj_vals[2],
								'color' => '#000000',
							);
						} elseif ( sizeof( $obj_vals ) == 2 ) {
							$obj_val_out[ $i ] = array(
								'x'     => $obj_vals[0],
								'y'     => $obj_vals[1],
								'blur'  => '0',
								'color' => '#000000',
							);
						}
						$i++;
					}

					$data['text-shadow']['value']        = $property[1];
					$data['text-shadow']['object_value'] = $obj_val_out;
				} else {
					$index = $property[0];
					$value = $property[1];

					// Fix before & after
					if ( $index === 'content' ) {
						$value = str_replace( '"', "'", $value );
					}

					if ( $index == 'background-image' && $this->string_is_contain( $value, 'linear-gradient' ) ) {
						$data['background-type'] = array(
							'value'     => 'gradient',
							'important' => false,
						);
					} elseif ( ( $index == 'background' && $this->string_is_contain( $value, 'url(' ) ) || ( $index == 'background-color' ) ) {
						$data['background-type'] = array(
							'value'     => 'classic',
							'important' => false,
						);
					}

					$important = $this->string_is_contain( $value, '!important' ) ? true : false;
					$value     = str_replace( array( ' !important', '!important' ), '', $value );

					$data[ $index ] = array(
						'value'     => $value,
						'important' => $important,
					);

					// Box Shadow object_value
					// if ( $index == 'box-shadow' ) {
					// $data[ $index ]['object_value'] = explode( ',', $value );
					// }

					// filter object_value
					if ( $index == 'filter' ) {

						$filter_obj_val      = explode( ' ', $value );
						$filter_obj_val_data = array();

						foreach ( $filter_obj_val as $j => $val ) {
							$val                            = str_replace( ')', '', $val );
							$val                            = explode( '(', $val );
							$filter_obj_val_data[ $val[0] ] = $val[1];
						}
						$data[ $index ]['object_value'] = $filter_obj_val_data;
					}

					// backdrop-filter object_value
					elseif ( $index == 'backdrop-filter' ) {

						$filter_obj_val      = explode( ' ', $value );
						$filter_obj_val_data = array();

						foreach ( $filter_obj_val as $j => $val ) {
							$val                            = str_replace( ')', '', $val );
							$val                            = explode( '(', $val );
							$filter_obj_val_data[ $val[0] ] = $val[1];
						}
						$data[ $index ]['object_value'] = $filter_obj_val_data;
					}

					// transform object_value
					elseif ( $index == 'font-family' ) {
						$important = $this->string_is_contain( $value, '!important' ) ? true : false;
						$value     = str_replace( array( ' !important', '!important' ), '', $value );
						$value     = str_replace( array( '/', '"' ), array( '', "'" ), $value );

						$data[ $index ] = array(
							'value'     => $value,
							'important' => $important,
						);
					}
					elseif ( $index == 'transform' ) {

						$filter_obj_val      = explode( ' ', $value );
						$filter_obj_val_data = array();

						foreach ( $filter_obj_val as $j => $val ) {
							$val = str_replace( ')', '', $val );
							$val = explode( '(', $val );

							$filter_obj_val_data[ $val[0] ] = $val[1];

							// setup translate obj
							if ( $val[0] == 'translate' ) {
								$translate                         = explode( ',', $filter_obj_val_data['translate'] );
								$filter_obj_val_data['translateX'] = $translate[0];
								$filter_obj_val_data['translateY'] = $translate[1];
								unset( $filter_obj_val_data['translate'] );
							}

							// setup skew obj
							if ( $val[0] == 'skew' ) {
								$skew                         = explode( ',', $filter_obj_val_data['skew'] );
								$filter_obj_val_data['skewX'] = $skew[0];
								$filter_obj_val_data['skewY'] = $skew[1];
								unset( $filter_obj_val_data['skew'] );
							}
						}

						$data[ $index ]['object_value'] = $filter_obj_val_data;
					} else {
						$data[ $property[0] ]['value'] = $value;
					}

					$do_not_add_unit = $index !== 'box-shadow' && $index !== 'transform' && $index !== 'filter' && $index !== 'backdrop-filter' && ! $this->string_is_contain( $value, 'linear-gradient' );

					if ( $do_not_add_unit && $this->string_is_contain( $value, 'px' ) ) {
						$data[ $property[0] ]['unit'] = 'px';
					} elseif ( $do_not_add_unit && $this->string_is_contain( $value, 'em' ) ) {
						$data[ $property[0] ]['unit'] = 'em';
					} elseif ( $do_not_add_unit && $this->string_is_contain( $value, '%' ) ) {
						$data[ $property[0] ]['unit'] = '%';
					} elseif ( $do_not_add_unit && $this->string_is_contain( $value, 'deg' ) ) {
						$data[ $property[0] ]['unit'] = 'deg';
					} elseif ( $do_not_add_unit && $this->string_is_contain( $value, 'vw' ) ) {
						$data[ $property[0] ]['unit'] = 'vw';
					} elseif ( $do_not_add_unit && $this->string_is_contain( $value, 'vh' ) ) {
						$data[ $property[0] ]['unit'] = 'vh';
					} elseif ( $do_not_add_unit && $this->string_is_contain( $value, 'ms' ) ) {
						$data[ $property[0] ]['unit'] = 'ms';
					}
				}

				if ( strpos( $property[1], '!important' ) !== false ) {
					$data[ $property[0] ]['important'] = true;
				}
			}
		}

		return $data;
	}

	/**
	 * Saves the data for a given post ID.
	 *
	 * @param int   $post_id The ID of the post.
	 * @param array $data The data to be saved.
	 * @return void
	 */
	private function saveData( $data ) {
		$styles_data      = array();
		$styles_temp_data = array();
		// Define the mapping between old and new states
		$stateMapping = array(
			'normal'       => '',
			'hover'        => ':hover',
			'active'       => ':active',
			'parent-hover' => 'parent-hover',
			'before'       => ':before',
			'after'        => ':after',
			'placeholder'  => '::placeholder',
		);

		foreach ( $data as $controlID => $controlData ) {

			unset( $controlData['settings']['el'] );
			unset( $controlData['settings']['cid'] );

			foreach ( $controlData['settings'] as $widget_name => $eachStyler ) {
				$eachStyler = $eachStyler['data'];
				foreach ( $eachStyler as $device => $deviceData ) {
					if ( ! is_array( $deviceData ) ) {
						continue;
					}

					foreach ( $deviceData as $action => $actionData ) {
						foreach ( $stateMapping as $state => $suffix ) {

							if ( ! isset( $eachStyler[ $device ][ $action ][ $state ] ) ) {
								continue;
							}

							$e = $eachStyler[ $device ][ $action ][ $state ]['selector'];

							switch ( $action ) {
								case 'parent-hover':
									$eachStyler[ $device ][ $action ][ $state ]['selector'] = str_replace( $controlID, $controlID . ':hover', $eachStyler[ $device ][ $action ][ $state ]['selector'] );
									break;

								default:
									if ( array_filter(
										$stateMapping,
										function ( $state ) use ( $e ) {
											if ( ! $state ) {
												return false;
											}

											if ( strpos( $e, $state ) !== false ) {
												return true;
											}
										}
									) ) {
										$eachStyler[ $device ][ $action ][ $state ]['selector'] = trim( $eachStyler[ $device ][ $action ][ $state ]['selector'] );
									} else {
										$eachStyler[ $device ][ $action ][ $state ]['selector'] = trim( $eachStyler[ $device ][ $action ][ $state ]['selector'] ) . $stateMapping[ $state ];
									}
									break;
							}
						}
					}
				}

				$eachStyler = wp_json_encode( $eachStyler );
				$cid        = str_shuffle( md5( microtime( true ) ) );

				$styles_data[ $controlID ][ $cid ]                                  = $eachStyler;
				$styles_temp_data[ $controlID ][ $widget_name ]['settings'][ $cid ] = $eachStyler;
			}

			if ( ! isset( $controlData['inner_items'] ) ) {
				continue;
			}

			foreach ( $controlData['inner_items'] as $item_index => $item ) {
				foreach ( $item as $widget_name => $eachStyler ) {
					if( isset( $eachStyler['data'] ) ) {
						$id         = $eachStyler['id'];
						$el         = $eachStyler['el'];
						$eachStyler = $eachStyler['data'];
						foreach ( $eachStyler as $device => $deviceData ) {
							if ( ! is_array( $deviceData ) ) {
								continue;
							}

							foreach ( $deviceData as $action => $actionData ) {

								if ( ! isset( $eachStyler[ $device ][ $action ] ) ) {
									continue;
								}

								$e = $eachStyler[ $device ][ $action ]['selector'];
								// $eachStyler[ $device ][ $action ]['selector'] = $eachStyler[ $device ][ $action ]['selector'] . " .elementor-repeater-item-{$id}";

								switch ( $action ) {
									case 'parent-hover':
										$eachStyler[ $device ][ $action ]['selector'] = str_replace( $controlID, $controlID . ':hover', $eachStyler[ $device ][ $action ]['selector'] );
										break;

									default:
										if ( array_filter(
											$stateMapping,
											function ( $state ) use ( $e ) {
												if ( ! $state ) {
													return false;
												}

												if ( strpos( $e, $state ) !== false ) {
													return true;
												}
											}
										) ) {
											$eachStyler[ $device ][ $action ]['selector'] = trim( $eachStyler[ $device ][ $action ]['selector'] );
										} else {
											$eachStyler[ $device ][ $action ]['selector'] = trim( $eachStyler[ $device ][ $action ]['selector'] ) . $stateMapping[ $action ];
										}
										break;
								}
							}
						}

						$eachStyler = wp_json_encode( $eachStyler );
						$cid        = str_shuffle( md5( microtime( true ) ) );

						$styles_data[ $controlID ][ $cid ]                     = $eachStyler;
						$styles_temp_data[ $controlID ][ $id ][ $widget_name ] = array(
							$cid => $eachStyler,
						);
					} else {
						foreach ( $eachStyler as $es_key => $es_value ) {
							$id         = $es_value['id'];
							$el         = $es_value['el'];
							$es_value = $es_value['data'];
							foreach ( $es_value as $device => $deviceData ) {
								if ( ! is_array( $deviceData ) ) {
									continue;
								}

								foreach ( $deviceData as $action => $actionData ) {

									if ( ! isset( $es_value[ $device ][ $action ] ) ) {
										continue;
									}

									$e = $es_value[ $device ][ $action ]['selector'];
									switch ( $action ) {
										case 'parent-hover':
											$es_value[ $device ][ $action ]['selector'] = str_replace( $controlID, $controlID . ':hover', $es_value[ $device ][ $action ]['selector'] );
											break;

										default:
											if ( array_filter(
												$stateMapping,
												function ( $state ) use ( $e ) {
													if ( ! $state ) {
														return false;
													}

													if ( strpos( $e, $state ) !== false ) {
														return true;
													}
												}
											) ) {
												$es_value[ $device ][ $action ]['selector'] = trim( $es_value[ $device ][ $action ]['selector'] );
											} else {
												$es_value[ $device ][ $action ]['selector'] = trim( $es_value[ $device ][ $action ]['selector'] ) . $stateMapping[ $action ];
											}
											break;
									}
								}
							}

							$es_value = wp_json_encode( $es_value );
							$cid = str_shuffle( md5( microtime( true ) ) );
							$styles_data[ $controlID ][ $cid ]                     = $es_value;
							$styles_temp_data[ $controlID ][ $id ][ $widget_name ] = array(
								$cid => $es_value,
							);
						}
					}
				}
			}
		}

		return $styles_temp_data;
	}

	/**
	 * Recursively updates the inner elements of an array based on new settings.
	 *
	 * @param array $elements The array of elements to update.
	 * @param array $newSettings The new settings to apply.
	 * @return array The updated array of elements.
	 */
	private function doUpdateInnerElements( $elements, $newSettings ) {
		if( !is_array( $elements ) ) {
			$elements = json_decode( $elements, true );
		}

		if( !is_array( $elements ) ) {
			return $elements;
		}

		foreach ( $elements as $ss => $sub_element ) {
			if ( isset( $sub_element['settings'] ) && is_array( $sub_element['settings'] ) ) {
				foreach ( $sub_element['settings'] as $settingName => $setting ) {
					if ( is_array( $setting ) ) {
						if ( strpos( json_encode( $setting ), 'desktophover' ) !== false ) {
							$id = $sub_element['id'];
							if ( isset( $newSettings[ $id ][ $settingName ] ) ) {
								$newSetting = $newSettings[ $id ][ $settingName ]['settings'];
								if ( is_array( $newSetting[ array_key_first( $newSetting ) ] ) || is_object( $newSetting[ array_key_first( $newSetting ) ] ) ) {
									$newSetting[ array_key_first( $newSetting ) ] = json_encode( $newSetting[ array_key_first( $newSetting ) ] );
								}

								$elements[ $ss ]['settings'][ $settingName ] = array(
									'cid'    => array_key_first( $newSetting ),
									'stdata' => $newSetting[ array_key_first( $newSetting ) ],
								);
							}
						}

						foreach ( $setting as $i_key => $i_value ) {
							if ( ! is_array( $i_value ) ) {
								continue;
							}

							foreach ( $i_value as $sub_key => $sub_value ) {


								if ( strpos( json_encode( $sub_value ), 'desktophover' ) !== false && isset( $i_value['_id'] ) ) {
									if ( isset( $newSettings[ $sub_element['id'] ][ $i_value['_id'] ] ) ) {
										if( isset(  $newSettings[ $sub_element['id'] ][ $i_value['_id'] ][ $sub_key ] ) ) {
											$newSetting = $newSettings[ $sub_element['id'] ][ $i_value['_id'] ][ $sub_key ];
											if ( is_array( $newSetting[ array_key_first( $newSetting ) ] ) || is_object( $newSetting[ array_key_first( $newSetting ) ] ) ) {
												$newSetting[ array_key_first( $newSetting ) ] = json_encode( $newSetting[ array_key_first( $newSetting ) ] );
											}
											$elements[ $ss ]['settings'][ $settingName ][ $i_key ][ $sub_key ] = array(
												'cid'    => array_key_first( $newSetting ),
												'stdata' => $newSetting[ array_key_first( $newSetting ) ],
											);
										}
									}
								}
							}
						}
					}
				}
			}

			if ( isset( $sub_element['elements'] ) ) {
				$elements[ $ss ]['elements'] = $this->doUpdateInnerElements( $sub_element['elements'], $newSettings );
			}
		}

		return $elements;
	}

	/**
	 * Update the Elementor data for a given post ID.
	 *
	 * @param int $post_id The ID of the post to update the Elementor data for.
	 * @return void
	 */
	private function updateElementorData( $post_id ) {

		if ( get_post_meta( $post_id, '_elementor_data_updated', true ) ) {
			return;
		}

		$elementorData = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_array( $elementorData ) ) {
			$elementorData = json_decode( $elementorData, true );
		}

		if ( ! $stylerData = get_post_meta( $post_id, 'styles_temp_data', true ) ) {
			return false;
		}

		$newElementorData = $this->doUpdateInnerElements( $elementorData, $stylerData );

		// return;
		// $json_value = wp_slash( wp_json_encode( $newElementorData ) );

		update_post_meta( $post_id, '_elementor_data', $newElementorData );
		update_post_meta( $post_id, '_elementor_data_updated', true );
	}

	/**
	 * Parse the given element data and extract specific settings information.
	 *
	 * @param array $elementData
	 * @return array
	 */
	private function parseData( $elementData ) {
		if( ! is_array( $elementData ) ) {
			$elementData = json_decode( $elementData, true );
		}
		// Traverse through each element in the old data
		foreach ( $elementData as $ee => $element ) {
			// Check if the element has any elements
			if ( isset( $element['elements'] ) && ! empty( $element['elements'] ) ) {
				// Traverse through each sub-element
				foreach ( $element['elements'] as $sub_element ) {
					if ( isset( $sub_element['settings'] ) && is_array( $sub_element['settings'] ) ) {
						foreach ( $sub_element['settings'] as $settingName => $setting ) {
							if ( is_array( $setting ) && isset( $setting['desktophover'] ) ) {
								if ( strpos( json_encode( $setting ), 'desktophover' ) !== false ) {
									// static::$data[$sub_element['id']]['widget'] = @$sub_element['widget'];
									static::$data[ $sub_element['id'] ]['widget']                   = @$sub_element['widgetType'] ? @$sub_element['widgetType'] : $sub_element['elType'];
									static::$data[ $sub_element['id'] ]['id']                       = @$sub_element['id'];
									static::$data[ $sub_element['id'] ]['settings'][ $settingName ] = array(
										'el'     => @$settingName,
										'cid'    => @$sub_element['cid'],
										'data'   => @$setting,
										'elType' => @$sub_element['elType'],
									);
								}
							} elseif ( is_array( $setting ) ) {
								foreach ( $setting as $index => $item ) {
									if ( $item && is_array( $item ) && isset( $item['_id'] ) ) {
										if ( strpos( json_encode( $item ), 'desktophover' ) !== false ) {
											foreach ( $item as $item_setting_name => $item_setting_value ) {
												if ( is_array( $item_setting_value ) && isset( $item_setting_value['desktophover'] ) ) {
													if ( strpos( json_encode( $item_setting_value ), 'desktophover' ) !== false ) {
														// static::$data[$sub_element['id']]['widget'] = @$sub_element['widget'];
														static::$data[ $sub_element['id'] ]['widget']                              = @$sub_element['widgetType'] ? @$sub_element['widgetType'] : $sub_element['elType'];
														static::$data[ $sub_element['id'] ]['elType']                              = @$sub_element['elType'];
														static::$data[ $sub_element['id'] ]['is_repeater']                         = true;
														static::$data[ $sub_element['id'] ]['inner_items'][$settingName][ $item_setting_name ][] = array(
															'index'  => @$index,
															'id'     => @$item['_id'],
															'el'     => @$item_setting_name,
															'control_parent' => @$settingName,
															'cid'    => @$sub_element['cid'],
															'data'   => @$item_setting_value,
														);
													}
												}
											}
										}
									}
								}
							}
						}
					}

					if ( isset( $sub_element['elements'] ) && $sub_element['elements'] ) {
						$this->parseData( array( '' => $sub_element ) );
					}
				}
			}

			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				foreach ( $element['settings'] as $settingName => $setting ) {
					if ( is_array( $setting ) ) {
						if ( strpos( json_encode( $setting ), 'desktophover' ) !== false ) {
							static::$data[ $element['id'] ]['widget']                   = @$element['widgetType'] ? @$element['widgetType'] : $element['elType'];
							static::$data[ $element['id'] ]['id']                       = @$element['id'];
							static::$data[ $element['id'] ]['settings'][ $settingName ] = array(
								'el'     => @$settingName,
								'cid'    => @$element['cid'],
								'data'   => @$setting,
								'elType' => @$element['elType'],
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Convert the old data to a new format based on a key mapping.
	 *
	 * @param mixed $oldData
	 * @param int   $post_id
	 * @return array
	 */
	private function convertData( $oldData, $post_id ) {

		if ( ! is_array( $oldData ) ) {
			$decoded_data = json_decode( $oldData, true );
		} else {
			$decoded_data = $oldData;
		}

		static::$data = array();
		$this->parseData( $decoded_data );

		// Array to store the new data structure
		$new_data = array();

		foreach ( static::$data as $key => $element ) {
			foreach ( $element['settings'] as $index => $setting ) {
				$data                                   = $setting['data'];
				$result                                 = $this->handle_data( $data, $setting, $element );
				$new_data[ $key ]['id']                 = $key;
				$new_data[ $key ]['cid']                = $setting['cid'];
				$new_data[ $key ]['el']                 = $setting['el'];
				$new_data[ $key ]['elType']             = $setting['elType'];
				$new_data[ $key ]['widget']             = $element['widget'];
				$new_data[ $key ]['settings'][ $index ] = $result;
			}

			if ( isset( $element['is_repeater'] ) && $element['is_repeater'] === true ) {
				foreach ( $element['inner_items'] as $item_index => $item ) {
					foreach ( $item as $item_control_id => $item_control_data ) {
						if( isset( $item_control_data['data'] ) ) {
							$data   = $item_control_data['data'];
							$result = $this->handle_data( $data, $item_control_data, $element, true );
							$new_data[ $key ]['is_repeater'] = true;
							$new_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ] = array(
								'settings' => $result,
								'id'       => $item_control_data['id'],
								'el'       => $item_control_data['el'],
								'control_parent' => $item_control_data['control_parent'],
								'cid'      => $item_control_data['cid'],
							);
						} elseif( is_array( $item_control_data ) ) {
							foreach ($item_control_data as $icd_key => $icd_value) {
								$data   = $icd_value['data'];
								$result = $this->handle_data( $data, $icd_value, $element, true );
								$new_data[ $key ]['is_repeater'] = true;
								$new_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ][$icd_key] = array(
									'settings' => $result,
									'id'       => $icd_value['id'],
									'el'       => $icd_value['el'],
									'control_parent' => $icd_value['control_parent'],
									'cid'      => $icd_value['cid'],
								);
							}
						}
					}
				}
			}
		}


		return $this->finalize( $new_data, $post_id );
	}

	/**
	 * Description
	 *
	 * @since     1.0.0
	 */
	private function handle_data( $data, $setting, $element ) {
		// Define the mapping between old and new keys
		$keyMapping = array(
			'tabletlandscape' => 'tabletlandscape',
			'smallmobile'     => 'mobile',
			'desktop'         => 'desktop',
			'laptop'          => 'laptop',
			'tablet'          => 'tablet',
			'mobile'          => 'mobileLandscape',
		);

		// Define the mapping between old and new states
		$stateMapping = array(
			''            => 'normal',
			'hover'       => 'hover',
			'active'      => 'normal',
			'phover'      => 'parent-hover',
			'before'      => 'before',
			'after'       => 'after',
			'placeholder' => 'placeholder',
		);

		$newData = array();

		foreach ( $data as $sk => $skData ) {
			// Split the key into device and state
			foreach ( $keyMapping as $km => $kv ) {
				$sk = str_replace( $km, $km . '_', $sk );
				$sk = str_replace(
					array(
						'tablet_landscape_',
					),
					array(
						'tabletlandscape_',
					),
					$sk
				);
			}

			$parts  = explode( '_', $sk );
			$device = $parts[0];
			$state  = isset( $parts[1] ) ? $parts[1] : '';

			// Map the device and state to the new keys
			if ( isset( $keyMapping[ $device ] ) && isset( $stateMapping[ $state ] ) ) {
				$newDevice = $keyMapping[ $device ];
				$newState  = $stateMapping[ $state ];
				// Parse the value into an array of styles

				$pattern = '/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([01]?(\.\d+)?))?\s*\)|#([0-9a-fA-F]{3,6})|rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/';

				if( ! class_exists( '\SavvyWombat\Color\Color' ) ) {
					require Kata_Plus::$dir . '/vendor/autoload.php';
				}

				$skData = preg_replace_callback($pattern, function( $matches ) {
					$matches[0] = preg_replace( '/\.\d+,/', ',', $matches[0]);
					$matches[0] = str_replace(" ", "", $matches[0]);
					$matches[0] = str_replace(",.", ",0.", $matches[0]);

					return (string) \SavvyWombat\Color\Color::fromString($matches[0])->toHex();
				}, json_encode( $skData ) );

				$skData = json_decode( $skData, true );

				$styles = $this->setupData( $skData );

				if ( $styles ) {
					$newData[ $newDevice ][ $newState ] = array(
						'data' => $styles,
						'id'   => $element['id'],
					);
				}
			}
		}

		return $newData;
	}

	/**
	 * Get the substring between two given strings in a larger string.
	 *
	 * @param string $string
	 * @param string $start
	 * @param string $end
	 * @return string
	 */
	public function get_string_between( $string, $start, $end ) {
		$string = ' ' . $string;
		$ini    = strpos( $string, $start );
		if ( $ini == 0 ) {
			return '';
		}
		$ini += strlen( $start );
		$len  = strpos( $string, $end, $ini ) - $ini;
		return substr( $string, $ini, $len );
	}

	/**
	 * Parse the styles from a given value and return an array of style properties.
	 *
	 * @param mixed $value
	 * @return array
	 */
	private function parseStyles( $value ) {
		$styles = array();
		$parts  = explode( ';', $value );

		foreach ( $parts as $part ) {
			$styleParts = explode( ':', $part, 2 );
			if ( count( $styleParts ) == 2 ) {
				if ( trim( $styleParts[1] ) === 'notset' ) {
					continue;
				}
				$styles[ $styleParts[0] ] = array(
					'unit'      => strpos( $styleParts[1], 'px' ) !== false ? 'px' : '',
					'important' => strpos( $styleParts[1], '!important' ) !== false ? true : false,
					'value'     => trim( str_replace( '!important', '', $styleParts[1] ) ),
				);
			}
		}

		return $styles;
	}

	/**
	 * Finalizes the data by processing and formatting it before returning.
	 *
	 * @param array $pre_data The Pre data to be finalized.
	 * @param int   $post_id The ID of the post.
	 * @return array The finalized data.
	 */
	private function finalize( $pre_data, $post_id ) {
		$final_data = array();

		foreach ( $pre_data as $key => $element ) {

			$widget          = $element['widget'];
			$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
			$e_widget        = $widgets_manager->get_widget_types( $widget );

			if( ! $e_widget ) {
				$widgets_manager = \Elementor\Plugin::$instance->elements_manager;
				$e_widget        = $widgets_manager->get_element_types( $widget );
			}

			foreach ( $element['settings'] as $index => $setting ) {
				$result                    = $this->handle_final_data( $post_id, $e_widget, $setting, $element, $index );
				$cid                       = str_shuffle( md5( microtime( true ) ) );
				$el                        = $element['el'];
				$final_data[ $key ]['el']  = $el;
				$final_data[ $key ]['cid'] = $cid;
				$final_data[ $key ]['settings'][ $index ]['data'] = $result;
			}

			if ( isset( $element['is_repeater'] ) && $element['is_repeater'] === true ) {
				foreach ( $element['inner_items'] as $item_index => $item ) {
					foreach ( $item as $item_control_id => $item_control_data ) {
						if( isset( $item_control_data['settings'] ) ) {
							$result                                = $this->handle_final_data( $post_id, $e_widget, $item_control_data['settings'], $element, $item_control_data['control_parent'], $item_control_data['el'] );
							$cid                                   = str_shuffle( md5( microtime( true ) ) );
							$el                                    = $element['el'];
							$final_data[ $key ]['settings']['el']  = $el;
							$final_data[ $key ]['settings']['cid'] = $cid;
							$final_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ]['data'] = $result;
							$final_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ]['id']   = $item_control_data['id'];
							$final_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ]['el']   = $widget;
						} elseif ( is_array( $item_control_data ) ) {
							foreach ( $item_control_data as $icd_key => $icd_value ) {
								$result                                = $this->handle_final_data( $post_id, $e_widget, $icd_value['settings'], $element, $icd_value['control_parent'], $icd_value['el'], $icd_key );
								$cid                                   = str_shuffle( md5( microtime( true ) ) );
								$el                                    = $element['el'];
								$final_data[ $key ]['settings']['el']  = $el;
								$final_data[ $key ]['settings']['cid'] = $cid;
								$final_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ][$icd_key]['data'] = $result;
								$final_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ][$icd_key]['id']   = $icd_value['id'];
								$final_data[ $key ]['inner_items'][ $item_index ][ $item_control_id ][$icd_key]['el']   = $widget;
							}
						}
					}
				}
			}
		}

		return $final_data;
	}

	/**
	 * Description
	 *
	 * @since     1.0.0
	 */
	private function handle_final_data( $post_id, $e_widget, $setting, $element, $el = false, $subEl = false, $index = false ) {
		$data = array();
		foreach ( $setting as $device => $styleObject ) {
			foreach ( $styleObject as $state => $stateObject ) {
				$selector = '';

				if ( $e_widget ) {
					$widgetControls = $e_widget->get_controls();

					if ( isset( $widgetControls[ $el ] ) ) {

						if( $subEl ) {
							$control = $widgetControls[ $el ]['fields'][$subEl];
						} else {
							$control = $widgetControls[ $el ];
						}

						if ( isset( $control['selectors'] ) ) {
							$elData          = $setting[ $device ][ $state ];
							$selectorControl = '';
							$selectorControl = is_array($control['selectors']) ? array_key_first( $control['selectors'] ) : $control['selectors'];
						} else {
							$selectorControl = $control['selector'];
						}

						$elData = $setting[ $device ][ $state ];

						if( $subEl ) {
							$repeaterElementID = $index !== false ? $element['inner_items'][$el][$subEl][$index]['id'] : $element['inner_items'][$el][$subEl]['id'];
							$selectorControl = str_replace('{{CURRENT_ITEM}}', '.elementor-repeater-item-' . $repeaterElementID, $selectorControl );
						}

						if ( isset( $control['wrapper'] ) ) {
							$wrapper = str_replace( '{{WRAPPER}}', '.elementor-element.elementor-element-' . $elData['id'], $control['wrapper'] );
						} else {
							$wrapper = '.elementor-element.elementor-element-' . $elData['id'];
						}

						if ( $state === 'parent-hover' ) {
							$wrapper = $wrapper . ':hover';
						}

						if ( strpos( $selectorControl, '{{WRAPPER}}' ) === false ) {
							$selectorControl = $wrapper . ' ' . $selectorControl;
						}

						$selector = str_replace( '{{WRAPPER}}', $wrapper, $selectorControl );
					}
				} else {

					$selector = '.elementor-element.elementor-element-' . $stateObject['id'];

					if ( $state === 'parent-hover' ) {
						$selector = $selector . ':hover';
					}
				}

				$stateMapping = array(
					'normal'       => '',
					'parent-hover' => '',
					'hover'        => ':hover',
					'active'       => ':active',
					'before'       => ':before',
					'after'        => ':after',
					'placeholder'  => '::placeholder',
				);

				$selector = trim( $selector ) . $stateMapping[ $state ];

				$selector = str_replace( '::placeholder:hover', ':hover::placeholder', $selector );

				$selector = str_replace( '"', "'", $selector );

				if( trim( $selector ) ) {
					$data[ $device ][ $state ] = array(
						'data'     => $stateObject['data'],
						'selector' => $selector,
					);
				}
			}
		}

		return $data;
	}
}

DemoConvertorWebService::getInstance();