<?php

/**
 * The dashboard-specific functionality for Critical CSS generation.
 *
 * The CSS generation is based on the library Penthouse.
 *
 * @link https://github.com/pocketjoso/penthouse
 *
 * @package    abovethefold
 * @subpackage abovethefold/admin
 * @author     Optimalisatie.nl <info@optimalisatie.nl>
 */

class Abovethefold_Critical_Admin {

	/**
	 * Page cache controller
	 *
	 * @since    0.1.0
	 * @access   protected
	 * @var      object    $CTRL
	 */
	public $CTRL;

    function __construct(&$CTRL) {

        $this->CTRL =& $CTRL;
		$this->options = get_site_option('salesdoll');
		if (!is_array($this->options)) {
		    $this->options = array();
		}

        if ( is_multisite() ) {
            $this->CTRL->GO->loader->add_action( 'network_admin_menu', $this, 'add_network_menu' );
            $this->CTRL->GO->loader->add_action( 'admin_menu', $this, 'add_menu' );
        } else {
            $this->CTRL->GO->loader->add_action( 'admin_menu', $this, 'add_menu' );
        }

        // Register settings (data storage)
        $this->CTRL->GO->loader->add_action('admin_init', $this, 'register_settings', 10);

		$this->CTRL->GO->loader->add_action( 'admin_notices', $this, 'show_notices' );

		$this->CTRL->GO->loader->add_action( 'admin_init', $this, 'generate_critical_css', 15);

		$this->CTRL->GO->loader->add_action('admin_post_update_abovethefold', $this,  'update_abovethefold');

    }

    /**
     * Add setting sub-menu for single site
     */
    public function add_menu() {
        global $submenu;
        $submenu['salesdoll-performance'][] = array(__('Above The Fold'), 'salesdoll', admin_url('network/admin.php?page=salesdoll-abovethefold'));

    }

    /**
     * Add setting sub-menu for multi site
     */
    public function add_network_menu() {
        add_submenu_page( 'salesdoll-performance', __( 'Above The Fold', 'salesdoll' ), __( 'Above The Fold', 'salesdoll' ), 'salesdoll', 'salesdoll-abovethefold', array( &$this, 'create_admin_page' ) );
    }

    /**
     * Generate Critical CSS
     */
    public function generate_critical_css() {

        if (isset($_GET['generate-css']) && $_GET['generate-css']) {

            /**
             * Generate Crtical CSS
             */
            $this->load_generator();

            $criticalCSS = $this->generator->generate();

            if ($criticalCSS) {

                $options = get_site_option('salesdoll');

                // Update inline critical CSS
                $options['inline-css'] = $criticalCSS;
                update_site_option('salesdoll',$options);

                $this->CTRL->GO->admin->set_notice('Critical CSS generated and stored in the inline CSS field.');
            }
        }
    }

	public function update_abovethefold() {

      check_admin_referer('salesdoll');
      if(!current_user_can('salesdoll')) wp_die('FU');

		$options = get_site_option('salesdoll');
		if (!is_array($options)) {
			$options = array();
		}

		$input = $_POST['salesdoll'];

		$options['abovethefold-urls'] = trim(sanitize_text_field($input['urls']));

        $urls = array();
        $_urls = explode("\n",$input['urls']);
        foreach ($_urls as $url) {
            if (trim($url) === '') { continue 1; }
            if (!preg_match('|^http(s)?://|Ui',$url)) {
                add_settings_error(
                    'salesdoll-abovethefold',                     // Setting title
                    'urls_texterror',            // Error ID
                    'Invalid URL',     // Error message
                    'error'                         // Type of message
                );
                $error = true;
            } else {
                $urls[] = $url;
            }
        }
        if (empty($urls)) {
            add_settings_error(
                'salesdoll-abovethefold',                     // Setting title
                'urls_texterror',            // Error ID
                'You did not enter URL\'s.',     // Error message
                'error'                         // Type of message
            );
            $error = true;
        } else {
            $options['abovethefold-urls'] = implode("\n",$urls);
        }

        $dims = explode(',',$input['dimensions']);
        if (empty($dims)) {
            add_settings_error(
                'salesdoll-abovethefold',                     // Setting title
                'dimensions_texterror',            // Error ID
                'You did not enter dimensions.',     // Error message
                'error'                         // Type of message
            );
            $error = true;
            $options['abovethefold-dimensions'] = $this->data['dimensions'];
        } else {
            $dimensions = array();
            foreach ($dims as $dim) {
                if (trim($dim) === '') { continue 1; }
                $dim = explode('x',$dim);
                if (
                    count($dim) !== 2
                    OR !is_numeric($dim[0])
                    OR intval($dim[0]) <= 0
                    OR !is_numeric($dim[1])
                    OR intval($dim[1]) <= 0
                ) {
                    add_settings_error(
                        'salesdoll-abovethefold',                     // Setting title
                        'dimensions_texterror',            // Error ID
                        'One of the dimensions is invalid.',     // Error message
                        'error'                         // Type of message
                    );
                    $error = true;
                } else {
                    $dimensions[] = intval($dim[0]).'x'.intval($dim[1]);
                }
            }
            if (empty($dimensions)) {
                add_settings_error(
                    'salesdoll-abovethefold',                     // Setting title
                    'dimensions_texterror',            // Error ID
                    'You did not enter dimensions.',     // Error message
                    'error'                         // Type of message
                );
                $error = true;
                $options['abovethefold-dimensions'] = $this->data['dimensions'];
            } else {
                $options['abovethefold-dimensions'] = implode(', ',$dimensions);
            }
        }

		update_site_option('salesdoll',$options);

		wp_redirect(admin_url('network/admin.php?page=salesdoll-abovethefold'));
		exit;
    }

    /**
     * Options page callback
     */
    function create_admin_page() {
        global $pagenow;

        if ( !current_user_can( 'salesdoll' ) ) {
            wp_die(__('You do not have sufficient permissions to access this configuration.'));
        }

        $options = get_site_option('salesdoll');
        if (!is_array($options)) {
            $options = array();
        }


?>
<form method="post" action="<?php echo admin_url('admin-post.php?action=update_abovethefold'); ?>" class="clearfix">
    <?php wp_nonce_field('salesdoll'); ?>
        <div class="wrap nginx-wrapper">
            <h2 class="option_title"><?php _e( 'Above The Fold Optimization', 'salesdoll' ); ?></h2>
            <p class="description">Critical CSS generation for above the fold optimization.</p>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder">
                    <div id="post-body-content">

        <div class="postbox">
            <h3 class="hndle">
                <span><?php _e( 'Critical CSS generation', 'salesdoll' ); ?></span>
            </h3>
            <div class="inside">
                <?php
                    $purge_url = add_query_arg( array( 'page' => 'salesdoll-abovethefold', 'generate-css' => '1' ) );
                    $nonced_url = wp_nonce_url( $purge_url, 'salesdoll-abovethefold' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th>&nbsp;</th>
                        <td>
                            <a href="<?php echo $nonced_url; ?>" class="button-primary"><?php _e( 'Create Critical CSS', 'salesdoll' ); ?></a>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Dimensions</th>
                        <td>
                            <input type="text" name="salesdoll[dimensions]" value="<?php echo esc_attr( ((isset($options['abovethefold-dimensions'])) ? $options['abovethefold-dimensions'] : '') ); ?>" style="width:100%;" />
                            <p class="description"><?php _e('Enter the (responsive) dimensions to generate Critical CSS for, e.g. <code>1600x1200, 720x1280, 320x480</code>', 'salesdoll'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">URL's</th>
                        <td>
                            <textarea name="salesdoll[urls]" style="width:100%;height:100px;" /><?php echo esc_attr( ((isset($options['abovethefold-urls'])) ? $options['abovethefold-urls'] : '') ); ?></textarea>
                            <p class="description"><?php _e('Enter the URL\'s to generate Critical CSS for. The resulting CSS for each URL is merged and compressed te create critical CSS that is compatible for each page.<br />
                            The URL\'s should be located on the live installed WordPress installation and will be appended with a special query string to return extracted CSS code for use in critical CSS generation.', 'salesdoll'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php

        submit_button( __( 'Save All Changes', 'salesdoll' ), 'primary large', 'is_submit', true );
        ?>
        <!-- End of #post_form -->

                    </div>
                </div> <!-- End of #post-body -->
            </div> <!-- End of #poststuff -->
        </div> <!-- End of .wrap .nginx-wrapper -->
        </form>
        <?php
    }

	/**
	 * Show admin notices
	 *
	 * @since     1.0
	 * @return    string    The version number of the plugin.
	 */
	public function show_notices() {

		settings_errors( 'salesdoll-abovethefold' );

	}

	/**
	 * Load critical CSS generator
	 */
	public function load_generator() {

	    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/abovethefold/criticalcss.class.php';
	    $this->generator = new Salesdoll_CriticalCSS($this);
	}
}

