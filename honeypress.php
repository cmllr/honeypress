<?php
define("HONEYPOT_NAME","Honeypress");
define("HONEYPOT_VERSION", "0.1");



/*
Plugin Name:  Honeypress
Plugin URI:   https://github.com/cmllr/honeypress.git
Description:  Authorization honeypot
Version:      0.1
Author:       Roasting Malware
Author URI:   https://github.com/roastingmalware
License:      MIT
*/

function honeypot_install(){
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $tablename = $wpdb->prefix . "honeypress"; 
    $sql = "CREATE TABLE $tablename (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      name text NOT NULL,
      value text NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
function custom_login_failed( $username ) {
    $referrer = $_SERVER['HTTP_REFERER'];
    $password = $_POST["pwd"];
    # publish results to splunk
    global $wpdb;
    $tablename = $wpdb->prefix . "honeypress"; 
    $token = $wpdb->get_row("Select value from $tablename where name = 'splunk_token'")->value ;
    $url = $wpdb->get_row("Select value from $tablename where name = 'splunk_url'")->value ;

    $entry = new \stdClass();
    $entry->time = time();
    $entry->index = "main";
    $entry->source = "honeypress";
    $entry->sourcetype = "json";
    $entry->host = "ume";
    $entry->event = new \stdClass();
    $ip = "";
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $entry->event->source_ip = $ip;
    $entry->event->useragent = $_SERVER['HTTP_USER_AGENT'];
    $entry->event->username = $username;
    $entry->event->password = $password;
    $entry->event->referrer = $_SERVER['HTTP_REFERER'];
    
    $json = json_encode($entry);
    $ch = curl_init($url);
 
    
    //Tell cURL that we want to send a POST request.
    curl_setopt($ch, CURLOPT_POST, 1);
    
    //Attach our encoded JSON string to the POST fields.
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    //Set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Splunk $token"
    )); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //Execute the request
    $result = curl_exec($ch);
}
add_action( 'wp_login_failed', 'custom_login_failed' );

add_action( 'admin_menu', 'my_plugin_menu' );
register_activation_hook( __FILE__, 'honeypot_install' );

/** Step 1. */
function my_plugin_menu() {
	add_options_page( HONEYPOT_NAME, HONEYPOT_NAME, 'manage_options', HONEYPOT_NAME, 'my_plugin_options' );
}

/** Step 3. */
function my_plugin_options() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    global $wpdb;
    $tablename = $wpdb->prefix . "honeypress"; 
    if (isset($_POST["splunk_url"]) && isset($_POST["splunk_token"])){       
        $wpdb->delete( $tablename, array( 'name' => "splunk_url" ) );
        $wpdb->delete( $tablename, array( 'name' => "splunk_token" ) );
        $wpdb->insert($tablename, array(
            'name' => 'splunk_url',
            'value' => $_POST["splunk_url"],
        ));
        $wpdb->insert($tablename, array(
            'name' => 'splunk_token',
            'value' => $_POST["splunk_token"],
        ));
    }
    $token = $wpdb->get_row("Select value from $tablename where name = 'splunk_token'")->value ;
    $url = $wpdb->get_row("Select value from $tablename where name = 'splunk_url'")->value ;
    include plugin_dir_path( __FILE__ ). "options.php";
}