<?php
/*
Plugin Name: Orbis Neighborhood
Plugin URI: https://www.pronamic.eu/plugins/orbis-neighborhood/
Description: The Orbis Neighborhood plugin extends your Orbis environment with the option to manage a neighborhood.

Version: 1.0.0
Requires at least: 3.5

Author: Pronamic
Author URI: https://www.pronamic.eu/

Text Domain: orbis-neighborhood
Domain Path: /languages/

License: Copyright (c) Pronamic

GitHub URI: https://github.com/wp-orbis/wp-orbis-neighborhood
*/

/**
 * Autoload
 */
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

/**
 * Bootstrap
 */
function orbis_neighborhood_bootstrap() {
	global $orbis_neighborhood_plugin;

	$orbis_neighborhood_plugin = new Orbis_Neighborhood_Plugin( __FILE__ );
}

add_action( 'plugins_loaded', 'orbis_neighborhood_bootstrap' );
