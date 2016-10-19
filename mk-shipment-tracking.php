<?php
/*
Plugin Name: MK Shipment Tracking
Plugin URI: http://mkst.projects.mkbox.org
Text Domain: mkst
Description: Get shipment tracking information from many shipping companies
Domain aPth: /lang
Version: 0.1.0
Authoe: Madelle Kamois
Author URI: http://mkbox.org
*/
?>

<?php
/*  Copyright 2016  Madelle Kamois  (email: mk@mkbox.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>

<?php

error_reporting(E_ALL);

if (!defined( "WPINC" )) {
	die;
}

$mkst_domain = "mkst";
$mkst_provider_directory = "includes/providers";
$mkst_providers = array();

function mkst_load_providers() {
    global $mkst_domain;
    global $mkst_providers;
    global $mkst_provider_directory;
    $i = 0;
    foreach ( glob( dirname( __FILE__ ) . '/' . $mkst_provider_directory . '/class-*.php' ) as $file ) {
        include( $mkst_provider_directory . '/' . basename( $file ) );
        $arr_name = explode( '-', basename($file,'.php') );
        array_shift( $arr_name );
        foreach ($arr_name as $index => $word) {
            $arr_name[$index] = ucfirst($word);
        }
        $class_name = implode( '_', $arr_name );
        $provider = new $class_name();
        $mkst_providers[] = array ( 'name' => $provider->get_display_name(), 'provider' => $provider );
        $i++;
    }
    if ( $i == 0 ) {
        $mkst_providers[] = array( 'name' => __( 'No providers found', $mkst_domain ), 'provider' => null );
    }
}

function mkst_load_textdomain() {
    load_plugin_textdomain( $mkst_domain, FALSE, basename( dirname( __FILE__ ) ) . '/lang/' );
}

function mkst_show_settings() {
    global $mkst_providers;
    
    ?>

    <div class="wrap">
        <h2><?php _e( "Shipment Tracking Settings", $mkst_domain ); ?></h2>
        <form method="post" action="<?php echo __FILE__; ?>">
        <?php wp_nonce_field( "update-options" ); ?>
        </form>
    
    <?php

    var_dump( $mkst_providers );
    foreach ( $mkst_providers as $provider ) {
        echo "<h3>";
        echo $provider['name'];
        echo "</h3>";
    }
    echo '</div>';
}

mkst_load_providers();

function mkst_add_menu() {
    add_options_page( __( 'Shipment Tracking Settings', $mkst_domain ), __( 'Shipment Tracking', $mkst_domain ), 8, __FILE__, 'mkst_show_settings' );
}

add_action( 'plugins_loaded', 'mkst_load_textdomain' );
add_action( 'admin_menu', 'mkst_add_menu' );

?>