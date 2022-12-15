<?php
/**
 * @package 3D Printing Business - Printing List
 * @version 0.0.1
 */
/*
Plugin Name: 3D Printing Business - Printing List
Description: A short desc.
Author: BC
Version: 0.0.1
*/

//const NONCE_KEY = 'threedp_admin';
include_once("inc/functions.php");
include_once("inc/acf.php");

add_action('admin_menu', 'threedp_admin_menu');

add_action( 'admin_enqueue_scripts', 'threedp_load_css' );

add_action('admin_post_threedp_print_status_update', 'threedp_update_status');
