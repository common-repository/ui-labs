<?php
/*
Plugin Name: UI Labs
Plugin URI: http://halfelf.org/plugins/ui-labs/
Description: Experimental WordPress admin UI features, ooo shiny!
Author: John O'Nolan, Mika A Epstein
Version: 4.0.3
Author URI: http://halfelf.org
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
Network: True
*/

/**
 * Create a singleton class
 *
 * @since 2.0
 */

class UI_Labs {

	/*
	 * Starter defines and vars for use later
	 *
	 * @since 2.0
	 */


	// Plugin version
	public $plugin_version = '4.0.3';

	// Default environment.
	public $default_env_type;

	// Instance
	public static $instance;

	/**
	 * Construct
	 * Fires when class is constructed, adds init hook
	 *
	 * @since 2.0
	 */
	public function __construct() {

		//allow this instance to be called from outside the class
		self::$instance = $this;

		//add admin init hooks
		add_action( 'admin_init', array( &$this, 'admin_init' ) );

		//add admin panel
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( &$this, 'network_admin_menu' ) );
			add_action( 'current_screen', array( &$this, 'network_admin_screen' ) );
		} else {
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		}

		// If the new environment type is being used, we use it.
		// If the type isn't use, but development mode is, we're on dev.
		if ( function_exists( 'wp_get_environment_type' ) && ! empty( wp_get_environment_type() ) ) {
			$this->default_env_type = 'uilabs-' . wp_get_environment_type();
		} elseif ( function_exists( 'wp_get_development_mode' ) && ! empty( wp_get_development_mode() ) ) {
			$this->default_env_type = 'uilabs-development';
		}
	}

	public function get_options() {
		// Setting plugin defaults here:
		$option_defaults = array(
			'poststatuses' => 'yes',
			'pluginage'    => 'no',
			'toolbar'      => 'no',
			'dashboard'    => 'no',
			'identity'     => 'no',
			'footer'       => 'no',
		);

		// Fetch and set up options.
		$options = wp_parse_args( get_site_option( 'uilabs_options' ), $option_defaults );

		return $options;
	}

	/**
	 * Admin init Callback
	 *
	 * @since 2.0
	 */
	public function admin_init() {
		// Add link to settings from plugins listing page
		$plugin = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_$plugin", array( &$this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( &$this, 'donate_link' ), 10, 2 );

		// Register Settings
		$this->register_settings();

		// Get Options:
		$naked_options = $this->get_options();

		// Allows experiments to be turned on/off, written by Ollie Read

		// Post Statuses
		if ( 'yes' === $naked_options['poststatuses'] ) {
			add_filter( 'display_post_states', array( &$this, 'display_post_states' ) );
			wp_register_style( 'ui-labs-poststatuses', plugins_url( 'css/poststatuses.css', __FILE__ ), false, $this->plugin_version );
			wp_enqueue_style( 'ui-labs-poststatuses' );
		}

		// Show Plugin Age
		if ( ( 'yes' === $naked_options['pluginage'] || 'all' === $naked_options['pluginage'] ) || ( 'no' !== $naked_options['pluginage'] && is_network_admin() ) ) {
			wp_register_style( 'ui-labs-pluginage', plugins_url( 'css/pluginage.css', __FILE__ ), false, $this->plugin_version );
			wp_enqueue_style( 'ui-labs-pluginage' );
			add_action( 'after_plugin_row', array( &$this, 'pluginage_row' ), 10, 2 );
		}

		// Change toolbar padding
		if ( 'yes' === $naked_options['toolbar'] ) {
			wp_register_style( 'ui-labs-toolbar', plugins_url( 'css/toolbar.css', __FILE__ ), false, $this->plugin_version );
			wp_enqueue_style( 'ui-labs-toolbar' );
		}

		// Change footer
		if ( 'yes' === $naked_options['footer'] ) {
			wp_register_style( 'ui-labs-footer', plugins_url( 'css/footer.css', __FILE__ ), false, $this->plugin_version );
			wp_enqueue_style( 'ui-labs-footer' );
		}

		// Make dashboard bigger fonts
		if ( 'yes' === $naked_options['dashboard'] ) {
			wp_register_style( 'ui-labs-dashboard', plugins_url( 'css/dashboard.css', __FILE__ ), false, $this->plugin_version );
			wp_enqueue_style( 'ui-labs-dashboard' );
		}

		// Identify server
		if ( 'yes' === $naked_options['identity'] ) {
			wp_register_style( 'ui-labs-identity', plugins_url( 'css/identity.css', __FILE__ ), false, $this->plugin_version );
			wp_enqueue_style( 'ui-labs-identity' );
		}

		// Filter for the admin body class
		add_filter( 'admin_body_class', array( &$this, 'admin_body_class' ) );
	}

	/**
	 * Admin Menu Callback
	 *
	 * @since 2.0
	 */
	public function admin_menu() {
		// Add settings page on Tools
		add_management_page( __( 'UI Labs', 'ui-labs' ), __( 'UI Labs', 'ui-labs' ), 'manage_options', 'ui-labs-settings', array( &$this, 'uilabs_settings' ) );
	}

	/**
	 * Network Admin Menu Callback
	 *
	 * @since 3.0
	 */
	public function network_admin_menu() {
		add_submenu_page( 'settings.php', __( 'UI Labs', 'ui-labs' ), __( 'UI Labs', 'ui-labs' ), 'manage_options', 'ui-labs-network-settings', array( &$this, 'uilabs_settings' ) );
	}

	/**
	 * Network Admin Screen Callback
	 *
	 * @since 3.0
	 */
	public function network_admin_screen() {
		$current_screen = get_current_screen();

		if ( 'settings_page_ui-labs-network-settings-network' === $current_screen->id ) {

			if ( isset( $_POST['update'] ) && check_admin_referer( 'uilabs_networksave' ) ) {
				$options = $this->get_options();
				$input   = array_map( 'sanitize_text_field', $_POST['uilabs_options'] );

				foreach ( $options as $key => $value ) {
					if ( ! isset( $input[ $key ] ) || is_null( $input[ $key ] ) || '0' === $input[ $key ] ) {
						$output[ $key ] = 'no';
					} else {
						$output[ $key ] = sanitize_text_field( $input[ $key ] );
					}
				}


				// Update:
				update_site_option( 'uilabs_options', $output );

				?>
				<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e( 'Options Updated!', 'ui-labs' ); ?></strong></p></div>
				<?php
			}
		}
	}

	/**
	 * Register Admin Settings
	 *
	 * @since 2.0
	 */
	public function register_settings() {
		register_setting( 'ui-labs', 'uilabs_options', array( &$this, 'uilabs_sanitize' ) );

		// The main section
		add_settings_section( 'uilabs-experiments', __( 'Experiments to make your site cooler', 'ui-labs' ), array( &$this, 'uilabs_experiments_callback' ), 'ui-labs-settings' );

		// The Fields
		add_settings_field( 'poststatuses', __( 'Colour-Coded Post Statuses', 'ui-labs' ), array( &$this, 'poststatuses_callback' ), 'ui-labs-settings', 'uilabs-experiments' );
		add_settings_field( 'toolbar', __( 'More Toolbar Padding', 'ui-labs' ), array( &$this, 'toolbar_callback' ), 'ui-labs-settings', 'uilabs-experiments' );
		add_settings_field( 'footer', __( '3.2-esque Footer', 'ui-labs' ), array( &$this, 'footer_callback' ), 'ui-labs-settings', 'uilabs-experiments' );
		add_settings_field( 'dashboard', __( 'Bigger Dashboard Fonts', 'ui-labs' ), array( &$this, 'dashboard_callback' ), 'ui-labs-settings', 'uilabs-experiments' );
		add_settings_field( 'pluginage', __( 'Warn if Plugins Are Old', 'ui-labs' ), array( &$this, 'pluginage_callback' ), 'ui-labs-settings', 'uilabs-experiments' );
		add_settings_field( 'identity', __( 'Identify This Server', 'ui-labs' ), array( &$this, 'identity_callback' ), 'ui-labs-settings', 'uilabs-experiments' );
	}

	/**
	 * UI Labs Experiments Callback
	 *
	 * @since 2.0
	 */
	public function uilabs_experiments_callback() {
		?>
		<p><?php esc_html_e( 'To activate an experiment, click the checkbox and save your settings.', 'ui-labs' ); ?></p>
		<?php
	}

	/**
	 * Colour-Coded Post Statuses Callback
	 *
	 * @since 2.0
	 */
	public function poststatuses_callback() {
		$naked_options = $this->get_options();
		?>
			<input type="checkbox" id="uilabs_options[poststatuses]" name="uilabs_options[poststatuses]" value="yes" <?php echo checked( $naked_options['poststatuses'], 'yes', true ); ?> >
			<label for="uilabs_options[poststatuses]"><?php esc_html_e( 'Add colour coded labels to posts to easily identity their status (draft, scheduled, etc.).', 'ui-labs' ); ?></label>
		<?php
	}

	/**
	 * Plugin Age Callback
	 *
	 * @since 2.2
	 */
	public function pluginage_callback() {
		$naked_options = $this->get_options();
		if ( is_multisite() ) {
			?>
			<select id="uilabs_options[pluginage]" name="uilabs_options[pluginage]">
				<option value="no" <?php echo ( 'no' === $naked_options['pluginage'] ) ? ' selected' : ''; ?> >  <?php esc_html_e( 'Never', 'ui-labs' ); ?></option>
				<option value="network" <?php echo ( 'network' === $naked_options['pluginage'] ) ? ' selected' : ''; ?> ><?php esc_html_e( 'Network Admin Only', 'ui-labs' ); ?></option>
				<option value="all" <?php echo ( 'all' === $naked_options['pluginage'] ) ? ' selected' : ''; ?> ><?php esc_html_e( 'Network and Per Site', 'ui-labs' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Flag WordPress.org hosted plugins if they have not been updated for over two years. Warning: This may slow your plugin list dashboard.', 'ui-labs' ); ?></p>
			<?php
		} else {
			?>
			<input type="checkbox" id="uilabs_options[pluginage]" name="uilabs_options[pluginage]" value="yes" <?php echo checked( $naked_options['pluginage'], 'yes', true ); ?> >
			<label for="uilabs_options[pluginage]"><?php esc_html_e( 'Flag WordPress.org hosted plugins if they have not been updated for over two years. Warning: This may slow your plugin list dashboard.', 'ui-labs' ); ?></label>
			<?php
		}
	}

	/**
	 * Clean up the Toolbar
	 *
	 * @since 2.0
	 */
	public function toolbar_callback() {
		$naked_options = $this->get_options();
		?>
		<input type="checkbox" id="uilabs_options[toolbar]" name="uilabs_options[toolbar]" value="yes" <?php echo checked( $naked_options['toolbar'], 'yes', true ); ?> >
		<label for="uilabs_options[toolbar]"><?php esc_html_e( 'Add spacing and padding to the toolbar.', 'ui-labs' ); ?></label>
		<?php
	}

	/**
	 * 3.2 esque Footer
	 *
	 * @since 3.0
	 */
	public function footer_callback() {
		$naked_options = $this->get_options();
		?>
		<input type="checkbox" id="uilabs_options[footer]" name="uilabs_options[footer]" value="yes" <?php echo checked( $naked_options['footer'], 'yes', true ); ?> >
		<label for="uilabs_options[footer]"><?php esc_html_e( 'Restore a 3.2-esque admin footer.', 'ui-labs' ); ?></label>
		<?php
	}

	/**
	 * Make the fonts in the dashboard bigger
	 *
	 * @since 2.0
	 */
	public function dashboard_callback() {
		$naked_options = $this->get_options();
		?>
		<input type="checkbox" id="uilabs_options[dashboard]" name="uilabs_options[dashboard]" value="yes" <?php checked( $naked_options['dashboard'], 'yes', true ); ?> >
		<label for="uilabs_options[dashboard]"><?php esc_html_e( 'Increase the font size in the admin dashboard.', 'ui-labs' ); ?></label>
		<?php
	}

	/**
	 * Identify This Server
	 *
	 * @since 2.0
	 */
	public function identity_callback() {
		// default environments
		$wp_environments = array(
			'local'       => 'purple',
			'development' => 'green',
			'staging'     => 'yellow',
			'production'  => 'red',
		);
		$naked_options   = $this->get_options();
		?>
		<input type="checkbox" id="uilabs_options[identity]" name="uilabs_options[identity]" value="yes" <?php checked( $naked_options['identity'], 'yes', true ); ?> >
		<label for="uilabs_options[identity]"><?php esc_html_e( 'Enable colour coding for your different servers for quick identification.', 'ui-labs' ); ?></label>

		<p class="description">
			<?php echo wp_kses_post( __( 'The server type is configured by the WordPress environment settings. You can change this by defining <code>WP_ENVIRONMENT_TYPE</code> in your <code>wp-config.php</code> file.', 'ui-labs' ) ); ?>
			<br /><?php echo wp_kses_post( __( 'If <code>WP_DEVELOPMENT_MODE</code> is defined, and <code>WP_ENVIRONMENT_TYPE</code> is not, your site will be flagged as "Development."', 'ui-labs' ) ); ?>
		</p>
		<p class="description">
			<?php echo wp_kses_post( __( 'Supported environments and their colours are as follows:', 'ui-labs' ) ); ?>
		</p>

		<ul>
			<?php
			foreach ( $wp_environments as $environment => $colour ) {
				$content = ucfirst( $environment ) . ' - ' . $colour;
				echo '<li>';
				if ( 'uilabs-' . $environment === $this->default_env_type ) {
					echo '<strong>' . esc_html( $content . __( ' (current setting)', 'ui-labs' ) ) . '</strong>';
				} else {
					echo esc_html( $content );
				}
				echo '</li>';
			}
			?>
		</ul>
		<?php
	}

	/**
	 * Call settings page
	 *
	 * @since 1.0
	 */
	public function uilabs_settings() {
		?>

		<div class="wrap">

		<h1><?php esc_html_e( 'UI Labs', 'ui-labs' ); ?></h1>

		<?php
		settings_errors();

		if ( is_network_admin() ) {
			?>
			<form method="post" width='1'>
				<?php
				wp_nonce_field( 'uilabs_networksave' );
		} else {
			?>
			<form action="options.php" method="POST" >
				<?php
				settings_fields( 'ui-labs' );
		}

				do_settings_sections( 'ui-labs-settings' );
				submit_button( '', 'primary', 'update' );
		?>
			</form>
		</div>
		<?php
	}

	/**
	 * Options sanitization and validation
	 *
	 * @param $input the input to be sanitized
	 * @since 2.0
	 */
	public function uilabs_sanitize( $input ) {

		$options             = $this->get_options();

		foreach ( $options as $key => $value ) {
			if ( ! isset( $input[ $key ] ) || is_null( $input[ $key ] ) || '0' === $input[ $key ] ) {
				$output[ $key ] = 'no';
			} else {
				$output[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		return $output;
	}

	/**
	 * Modify the output of post statuses
	 *
	 * Allows for fine-grained control of styles and targeting protected posts, written by Pete Mall
	 *
	 * Modified to allow multiple languages: https://wordpress.org/support/topic/plugin-ui-labs-classes-translated
	 *
	 * @since 1.0.1
	 */
	public function display_post_states( $post_states ) {
		$post_state_array = array();
		foreach ( $post_states as $post_state => $post_state_title ) {
			$post_state_array[] = '<span class="' . strtolower( str_replace( ' ', '-', $post_state ) ) . '">' . $post_state_title . '</span>';
		}
		return $post_state_array;
	}

	/**
	 * Show the age of a plugin underneath if it's over 2 years old
	 *
	 * @since 2.2.0
	 * @param string $file
	 * @param array  $plugin_data
	 * @return false|void
	 */
	public function pluginage_row( $file, $plugin_data ) {

		// Forcing a hard set - If there's no slug, then this plugin is screwy and should be skipped.
		if ( ! isset( $plugin_data['slug'] ) ) {
			return false;
		}

		$lastupdated = strtotime( $this->pluginage_get_last_updated( $plugin_data['slug'] ) );
		$twoyears    = strtotime( '-2 years' );

		if ( $lastupdated >= $twoyears ) {
			return false;
		}

		$plugins_allowedtags = array(
			'a'       => array(
				'href' => array(),
				'title' => array(),
			),
			'abbr'    => array( 'title' => array() ),
			'acronym' => array( 'title' => array() ),
			'code'    => array(),
			'em'      => array(),
			'strong'  => array(),
		);
		$plugin_name = wp_kses( $plugin_data['Name'], $plugins_allowedtags );

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

		if ( is_network_admin() || ! is_multisite() ) {
			if ( is_network_admin() ) {
				$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
			} else {
				$active_class = is_plugin_active( $file ) ? ' active' : '';
			}
			echo '<tr class="plugin-age-tr' . esc_attr( $active_class ) . '" id="' . esc_attr( $plugin_data['slug'] . '-age' ) . '" data-slug="' . esc_attr( $plugin_data['slug'] ) . '" data-plugin="' . esc_attr( $file ) . '"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-age colspanchange"><div class="age-message">';
			// translators: %1 - Plugin Name ; %2 - time since last update
			$results = sprintf( __( '%1$s was last updated %2$s ago and may no longer be supported.', 'ui-labs' ), $plugin_name, human_time_diff( $lastupdated, current_time( 'timestamp' ) ) );
			echo esc_html( $results );
			echo '</div></td></tr>';
		}
	}

	/**
	 * Detect the age of the plugin
	 * From https://wordpress.org/plugins/plugin-last-updated/
	 *
	 * @since 2.2.0
	 * @param string $slug
	 * @return date|void
	 */
	public function pluginage_get_last_updated( $slug ) {

		// Bail early - If there's no slug, then this plugin is screwy and should be skipped.
		// Return an empty but CACHABLE response.
		if ( ! isset( $slug ) ) {
			return '';
		}

		$request = wp_remote_post(
			'http://api.wordpress.org/plugins/info/1.0/',
			array(
				'body' => array(
					'action' => 'plugin_information',
					'request' => serialize(
						(object) array(
							'slug' => $slug,
							'fields' => array( 'last_updated' => true ),
						)
					),
				),
			)
		);

		if ( 200 != wp_remote_retrieve_response_code( $request ) || empty( $request ) ) {
			// If there's no response, return with a cacheable response
			return '';
		} else {
			$response = unserialize( wp_remote_retrieve_body( $request ) );
		}

		if ( isset( $response->last_updated ) ) {
			return sanitize_text_field( $response->last_updated );
		} else {
			return false;
		}
	}

	/**
	 * Identify Server UI Experiment
	 *
	 * Allows users to easily spot whether they are logged in to their development, staging, or live server.
	 *
	 * @since 1.2
	 */
	public function admin_body_class( $classes ) {
		if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
			$classes .= ' ' . $this->default_env_type . ' ';
		}
		return $classes;
	}

	/**
	 * Add settings link on plugin
	 *
	 * @since 2.0
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'tools.php?page=ui-labs-settings' ) . '">' . __( 'Settings', 'ui-labs' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add Donate link on plugin
	 *
	 * @since 3.0.2
	 */

	// donate link on manage plugin page
	public function donate_link( $links, $file ) {
		if ( plugin_basename( __FILE__ ) == $file ) {
			$donate_link = '<a href="https://ko-fi.com/A236CEN/">' . __( 'Donate', 'wp-grins-ssl' ) . '</a>';
			$links[] = $donate_link;
		}
		return $links;
	}
}

new UI_Labs();
