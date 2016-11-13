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

require_once( 'class-mk-shipment-tracking.php' );

function mkst_cron_update() {
    $inst = new MKST();
    $inst->update_tracking_history();
}

add_action( 'init', array( 'MKST', 'init' ) );
add_action( 'mkst_cron', 'mkst_cron_update' );
register_activation_hook( __FILE__, array( 'MKST', 'install' ) );
if ( !wp_next_scheduled( 'mkst_cron' ) ) {
    wp_schedule_event( time(), 'hourly', 'mkst_cron' );
}

?>