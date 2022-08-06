<?php
/*
Plugin Name:  HoneyPress
Plugin URI:   https://github.com/cmllr/honeypress.git
Description:  High interaction honeypot for WordPress
Version:      2
Author:       https://github.com/cmllr
Author URI:   https://github.com/cmllr/honeypress.git
License:      Apache 2.0
*/

/*
    Copyright 2021-2022 https://github.com/cmllr

    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at

        http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.
*/

require_once ABSPATH . "wp-content/plugins/honeypress/util.php";
require_once(ABSPATH . 'wp-admin/includes/user.php');

$watchFiles = getSetting("watchFiles");
if ($watchFiles) {
    $preState = ABSPATH."logs/pre.json";
    if (!is_file($preState)){
        $content = getDirContents(ABSPATH);
        file_put_contents($preState, json_encode($content));
    } 
    $preStateContent = json_decode(file_get_contents($preState));
    $nowStateContent = getDirContents(ABSPATH);
    $change = false;
    
    /* check if unknown hashs are now present */
    foreach($nowStateContent as $key => $file){
        $token = get_value("_ga", $_COOKIE);
        if (!$token){
            $token = generateRandomString(35);
            setcookie("_ga", $token);
        }
        if (!property_exists($preStateContent, $key)){
            log_action($token, $file, false, "New file $file", "filedropnew");
            $change = true;
        }
    }
    
    foreach($preStateContent as $key => $file){
        $token = get_value("_ga", $_COOKIE);
        if (!$token){
            $token = generateRandomString(35);
            setcookie("_ga", $token);
        }
        if (!array_key_exists($key, $nowStateContent)){
            log_action($token, $file, false, "Removed file $file", "filedropdelete");
            $change = true;
        }
    }
    
    
    
    if ($change){
        $preStateContent = $nowStateContent;
        file_put_contents($preState, json_encode($preStateContent));
    }
}





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
        $what["credentials"] = array(
            "user" => $username,
            "password" => $_POST["pwd"],
            "stayLoggedIn" => in_array("rememberme", array_keys($_POST))
        );
        create_log_folder($id, $what);
        log_action($id, array(), false, "Failed login $username:".$_POST["pwd"], "failedlogin");
        session_destroy();
        return;
    }
    // check for user existence
    $user = get_user_by("login", $username);
    // Create user only if needed
    // If existingUsersOnly is active, no new user will be created
    $token = get_value("_ga", $_COOKIE);
    if (!$token){
        $token = generateRandomString(35);
    }
    if (!$user && !$existingUsersOnly) {
        $newUser = wp_create_user($username, "fasffas-fasf");
        $role = getSetting("userRole");
        $newUserObj = new WP_User($newUser);
        $newUserObj->set_role($role);
        log_action($token, array(), false, "Created user(User=$username,Pass=".$_POST["pwd"].",Role=$role)","create_user");
        update_user_meta($newUser, "isHoneypot", true);
        update_user_meta($newUser, "createSession", $token);
    }
    wp_clear_auth_cookie();
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

function check_credentials($user){
    if (is_blocked_user()){
        return;
    }
    $existingUsersOnly = getSetting("existingUsersOnly");
    $credentials = getSetting("credentials");
    $target_login = $user->user_login;
    $pass = $_POST["pwd"];
    if ($existingUsersOnly){

        $passwords = property_exists($credentials, $target_login) ? $credentials->{$target_login} : [];
        if (!in_array($pass, $passwords)){
            return new WP_Error( 'check_credentials', 'Error: The password you entered for the username user is incorrect.' );
        } else {
            return $user;
        }
    }

    return $user; 
}

add_filter("wp_authenticate_user", "check_credentials");

function expireUser($id) {
    if (is_blocked_user()){
        return;
    }
    $token = get_user_meta($id, "createSession", true);
    $user = get_user_by("ID", $id);
    if ($user){
        $userName = $user->user_login;
        log_action($token, array(
            "removed" => true
        ), false, "Removed user $id ($userName)", "usercleanup");
        setcookie('_ga', null, -1, '/'); 
        wp_delete_user($id);
        unset($_COOKIE['_ga']); 
    }
}

add_action( 'honeypress_expire_user', 'expireUser' );
function remove_honeypot_user()
{
    if (is_blocked_user()){
        return;
    }
    $current_user   = wp_get_current_user();
    $users = get_users(array(
        'meta_key' => 'isHoneypot',
        'meta_value' => true
    ));
    foreach ($users as $user) {
        $id = $user->ID;
        if ($id === $current_user->ID) {
            $token = get_user_meta($id, "createSession", true);
            $userName = $current_user->user_login;
            log_action($token, array(
                "removed" => true
            ), false, "Removed user $id ($userName)", "usercleanup_timeout");
            wp_delete_user($id);
        }
    }
}
add_action('wp_logout', 'remove_honeypot_user');


function log_action($token, $what, $isXMLRPC, $shortAction, $logSuffix="request")
{
    if (is_blocked_user()){
        return;
    }
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

    $rootFolder = ABSPATH . "logs/" . $token;
    if (!is_dir($rootFolder)) {
        mkdir($rootFolder);
    }
    $prefix = $isXMLRPC ? "xmlrpc_" : "";
    file_put_contents($rootFolder . "/$prefix$time$logSuffix.json", json_encode($what));

    // log into global logfile
    if ($shortAction){
        $style = getSetting("logStyle");
        if ($style !== "json") {
            $logString = sprintf("[%s] [%s] [%s] %s\n",get_ip(), ($token ? $token : "No token"), $logSuffix, $shortAction);
        } else {
            $logString = json_encode([
                "ip" => get_ip(),
                "token" => ($token ? $token : "No token"),
                "suffix" => $logSuffix,
                "action" => $shortAction
            ]);
        }
        error_log($logString,3, ABSPATH."logs/global.log");
    }
}

function activity_trigger()
{
    if (is_blocked_user()){
        return;
    }
    $token = get_value("_ga", $_COOKIE);
    if (!$token){
        $token = generateRandomString(35);
        setcookie("_ga", $token);
    }
    if ($token) {
        log_action($token, get_request_env(), defined("XMLRPC_REQUEST"), $_SERVER['QUERY_STRING'], "request");
    }
}
add_action("init", "activity_trigger");


function log_404(){
    if (is_blocked_user()){
        return;
    }
    if( is_404() ){ 
        $token = get_value("_ga", $_COOKIE);
        if (!$token){
            $token = generateRandomString(35);
            setcookie("_ga", $token);
        }
        log_action($token, get_request_env(), false, $_SERVER['REQUEST_URI'], "404");
    }
}
add_action( 'template_redirect', 'log_404',10,0 );

if (getSetting("hidePlugin")){
    add_filter('all_plugins', "filter_plugins");
}
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
    if (is_blocked_user()){
        return;
    }
    $token = get_value("_ga", $_COOKIE);
    $data = get_request_env();
    $hash = sha1_file($file["tmp_name"]);
    $data["file"] = $file;
    $data["file"]["hash"] = $hash;
    $fileName = $file['name'];
    log_action($token, $data, false, "Upload file $fileName", "fileupload");
    // Move File to log destination

    $targetPath = ABSPATH."logs/$token/uploads";
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
    if (is_blocked_user()){
        return;
    }
    $token = get_value("_ga", $_COOKIE);
    wp_delete_comment($id);
    $data = get_request_env();
    $data["comment"] = $commentdata;
    log_action($token, $data, false,"New comment","comment");
}


function log_admin_navigation(){
    if (is_blocked_user()){
        return;
    }
    $token = get_value("_ga", $_COOKIE);
    $id = $_SERVER['REQUEST_URI'];
    log_action($token, $id, false, "Navigated $id","dashboard");
}
add_action("admin_init", "log_admin_navigation",10,0);

function log_logout($id){
    if (is_blocked_user()){
        return;
    }
    $token = get_value("_ga", $_COOKIE);
    log_action($token, $id, false, "User $id demanded logout","logout");
    $user = get_user_by("ID", $id);
    if ($user){
        $userName = $user->user_login;
        
        $notAllowed = getSetting("blockedLogins");
        if (!in_array($userName, $notAllowed)){
            wp_delete_user($id);
            log_action($token, $id, false, "Removed user $id ($userName)","usercleanup_logout");
        } else {
            log_action($token, $id, false, "Skipping user remove $id ($userName)","skippedusercleanup_logout");

        }
    } else {
        log_action($token, $id, false, "Attempted remove for $id, but was already gone","usercleanup_logout");
    }
}
add_action("wp_logout", "log_logout",10,1);