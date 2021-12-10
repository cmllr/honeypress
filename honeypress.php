<?php
/*
Plugin Name:  HoneyPress
Plugin URI:   https://github.com/cmllr/honeypress.git
Description:  High interaction honeypot for WordPress
Version:      2
Author:       Christoph Müller
Author URI:   https://github.com/cmllr/honeypress.git
License:      Apache 2.0
*/

require_once ABSPATH . "/wp-content/plugins/honeypress/util.php";
require_once(ABSPATH . 'wp-admin/includes/user.php');

/**
 * Hook triggered by wrong logins
 * 
 */
function login_trigger($username)
{
    $data = get_request_env();
    $data["credentials"] = array(
        "user" => $username,
        "password" => $_POST["pwd"],
        "stayLoggedIn" => in_array("rememberme", array_keys($_POST))
    );
    $existingUsersOnly = getSetting("existingUsersOnly");
    $notAllowed = getSetting("blockedLogins");
    // Don't honeypot certain users
    if ($notAllowed && in_array($username, $notAllowed)) {
        $token = generateRandomString(35);
        setcookie("_ga", $token);
        session_start();
        session_regenerate_id();
        $id = session_id();
        $what = get_request_env();
        $what["isFailedLogin"] = true;
        create_log_folder($id, $what);
        session_destroy();
        return;
    }
    // check for user existence
    $user = get_user_by("login", $username);
    // Create user only if needed
    // If existingUsersOnly is active, no new user will be created
    $token = generateRandomString(35);
    if (!$user && !$existingUsersOnly) {
        $newUser = wp_create_user($username, "fasffas-fasf");
        update_user_meta($newUser, "isHoneypot", true);
        update_user_meta($newUser, "createSession", $token);
    }
    wp_clear_auth_cookie();
    $token = generateRandomString(35);
    setcookie("_ga", $token);
    $user = get_user_by("login", $username);
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    create_log_folder($token, $data);
    $redirectTarget = user_admin_url();
    wp_safe_redirect($redirectTarget);
    if (!$existingUsersOnly && getSetting(("expireUser"))) {
        wp_schedule_single_event(time() + getSetting("expireUser"), "honeypress_expire_user", array(
            $user->ID
        ), false);
    }
    exit();
}
add_action('wp_login_failed', 'login_trigger');

function expireUser($id) {
    $token = get_user_meta($id, "createSession", true);
    log_action($token, array(
        "removed" => true
    ), false, "usercleanup");
    setcookie('_ga', null, -1, '/'); 
    wp_delete_user($id);
    unset($_COOKIE['_ga']); 
}

add_action( 'honeypress_expire_user', 'expireUser' );
function remove_honeypot_user()
{
    $current_user   = wp_get_current_user();
    $users = get_users(array(
        'meta_key' => 'isHoneypot',
        'meta_value' => true
    ));
    foreach ($users as $user) {
        $id = $user->ID;
        if ($id === $current_user->ID) {
            $token = get_value("_ga", $_COOKIE);
            log_action($token, array(
                "removed" => true
            ), false, "usercleanup");
            wp_delete_user($id);
        }
    }
}
add_action('wp_logout', 'remove_honeypot_user');


function log_action($token, $what, $isXMLRPC = false, $logSuffix="request")
{

    $time =  microtime(true);
    if (!$token && $isXMLRPC) {
        $xml = simplexml_load_string($what["xmlPayload"]);
        $userName = (string)$xml->params->param[0]->value[0];
        $user = get_user_by("login", $userName);
        $sessions = get_user_meta($user->ID, 'session_tokens', true);
        if (count($sessions) > 0) {
            $token = array_keys($sessions)[0];
        }
    }

    $rootFolder = ABSPATH . "/logs/" . $token;
    if (!is_dir($rootFolder)) {
        mkdir($rootFolder);
    }
    $prefix = $isXMLRPC ? "xmlrpc_" : "";
    file_put_contents($rootFolder . "/$prefix$time$logSuffix.json", json_encode($what));
}

function activity_trigger()
{
    $token = get_value("_ga", $_COOKIE);
    if (!$token){
        $token = generateRandomString(35);
        setcookie("_ga", $token);
    }
    if ($token) {
        log_action($token, get_request_env(), defined("XMLRPC_REQUEST"), "request");
    }
}
add_action("init", "activity_trigger");

add_filter('all_plugins', "filter_plugins");

function filter_plugins($allPlugins)
{
    $allPlugins["honeypress/honeypress.php"] = $allPlugins["hello.php"];
    unset($allPlugins["hello.php"]);
    return $allPlugins;
}
if (getSetting("mask")) {
    remove_action('wp_head', 'wp_generator');
}

function set_generator_tag()
{
    echo '<meta name="generator" content="' . getSetting("generatorTag") . '" />' . "\n";
}

if (getSetting("generatorTag")) {
    add_action('wp_head', 'set_generator_tag');
}

add_filter('wp_handle_upload_prefilter', 'upload_filter' );

function upload_filter( $file ) {
    $token = get_value("_ga", $_COOKIE);
    $data = get_request_env();
    $hash = sha1_file($file["tmp_name"]);
    $data["file"] = $file;
    $data["file"]["hash"] = $hash;
    log_action($token, $data, false, "fileupload");
    // Move File to log destination

    $targetPath = ABSPATH."/logs/$token/uploads";
    if (!is_dir($targetPath)){
        mkdir($targetPath);
    }
    /** In case the uploads should not be actually added into WordPress, kill the process **/
    if (!getSetting("allowUploads")){
        die("Internal server error");
    }
    copy($file["tmp_name"], $targetPath."/$hash");
    return $file;
}

if  (getSetting("catchComments")){
    add_action( 'comment_post', 'catch_comment',10,3);
}
function catch_comment($id, $comment_approved, $commentdata) {
    $token = get_value("_ga", $_COOKIE);
    wp_delete_comment($id);
    $data = get_request_env();
    $data["comment"] = $commentdata;
    log_action($token, $data, false, "comment");
}
