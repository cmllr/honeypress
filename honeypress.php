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
    $token = $wpdb->get_row("Select value from $tablename where name = 'splunk_token'")->value ;
    $url = $wpdb->get_row("Select value from $tablename where name = 'splunk_url'")->value ;

    $entry = new \stdClass();
    $entry["index"] = "";
    $entry["source"] = "";
    $entry["sourcetype"] = "";
    $entry["host"] = "";
    $entry["event"] = "foo";

    $json = json_encode($entry);
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\nUser-Agent: Honeypress\r\nAuthorization: foo",
            'method'  => 'POST',
            'content' => http_build_query($json)
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) { /* Handle error */ }

    var_dump($result);
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