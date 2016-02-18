<?php

/**
 * Google Analytics Ga JS
 *
 * @since      2.3
 * @package    abovethefold
 * @subpackage abovethefold/admin
 * @author     Optimalisatie.nl <info@optimalisatie.nl>
 */


class Abovethefold_LocalizeJSModule_GoogleAnalyticsGaJs extends Abovethefold_LocalizeJSModule {


	// The name of the module
	public $name = 'Google Analytics (ga.js)';
	public $link = 'https://developers.google.com/analytics/devguides/collection/gajs/';

	public $update_interval = 86400; // once per day
	public $script_source = 'http://www.google-analytics.com/ga.js';

	public $snippets = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0
	 * @var      object    $CTRL       The above the fold admin controller..
	 */
	public function __construct( &$CTRL ) {

		parent::__construct( $CTRL );

		if ($this->CTRL->options['localizejs'][$this->classname]['enabled']) {
			switch ($this->CTRL->options['localizejs'][$this->classname]['incmethod']) {
				default:
					$this->CTRL->loader->add_action('wp_head', $this, 'setup_analytics_script', -1);
					$this->CTRL->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_script', -1);
				break;
			}
		}

	}

	/**
	 * Include script
	 */
	public function enqueue_script( ) {

		list($script_url, $script_time) = $this->get_script( true );

		wp_enqueue_script( 'google-analytics-ga-js', $script_url , array(), date('Ymd', $script_time) );

	}


	/**
	 * Parse Google Analytics javascript and return original code (to replace) and file-URL.
	 *
	 * @since 2.3
	 */
	public function parse_analytics_js( $code ) {

		/**
		 * ga.js
		 *
		 * @link https://developers.google.com/analytics/devguides/collection/gajs/
		 * @since 2.3
		 */
		if (strpos($code,'google-analytics.com/ga.js') !== false) {

			$regex = array();

			$replace = '';
			if ($this->CTRL->options['localizejs'][$this->classname]['incmethod'] === 'replace') {

				$regex[] = '#([\'|"])((http(s)?)?(:)?//[^/]+google-analytics\.com/ga\.js)[\'|"]#Ui';

				list($script_url,$script_time) = $this->get_script( true );
				$script_url = preg_replace('|^http(s)?:|Ui','',$script_url);
				$replace = '$1$3$4$5' . $script_url . '$1';

			}

			foreach ($regex as $str) {
				$code = preg_replace($str,$replace,$code);
			}

		}

		return $code;

	}

	/**
	 * Admin configuration options
	 */
	public function admin_config( $extra_config = '' ) {

		$config = '<div class="inside">';

		$config .= 'Include method: <select name="'.$this->option_name('incmethod').'">
			'.$this->select_options(array(
				'replace' => 'Replace URL in original code'
			),$this->options['incmethod']).'
		</select>';

		$config .= '</div>';

		parent::admin_config( $config );

	}

	/**
	 * Parse HTML
	 */
	public function parse_html( $html ) {

		$html = $this->parse_analytics_js( $html );

		return parent::parse_html( $html );

	}

	/**
	 * Parse Javascript
	 */
	public function parse_js( $js ) {

		$js = $this->parse_analytics_js( $js );

		return parent::parse_js( $js );

	}

}