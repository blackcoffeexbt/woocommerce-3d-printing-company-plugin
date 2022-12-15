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

//const NONCE_KEY = 'bc_admin';
include_once("inc/functions.php");

add_action('admin_menu', 'bc_admin_menu');

add_action( 'admin_enqueue_scripts', 'bc_load_css' );

add_action('admin_post_bc_print_status_update', 'bc_update_status');
