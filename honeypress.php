<?php
/*
Plugin Name:  Honeypress
Plugin URI:   https://github.com/cmllr/honeypress.git
Description:  Low interaction honeypot for WordPress
Version:      2
Author:       Christoph Müller
Author URI:   https://github.com/cmllr/honeypress.git
License:      Apache 2.0
*/

/**
 * Pulls a needle out of a haystack
 * 
 * @returns the value or null
 */
function get_value($needle, $haystack)
{
    $keys = array_keys($haystack);
    if (in_array($needle, $keys)) {
        return $haystack[$needle];
    }
    return null;
}

/**
 * Return the client IP
 * 
 * @return the client ip
 */
function get_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * Get an generic info about the request
 * 
 * @returns get, post, target, userAgent, referrer, ip
 */
function get_request_env()
{
    $result = array(
        "get" => $_GET,
        "post" => $_POST,
        "target" => get_value("REQUEST_URI", $_SERVER),
        "userAgent" => get_value("HTTP_USER_AGENT", $_SERVER),
        "referrer" => get_value("HTTP_REFERER", $_SERVER),
        "ip" => get_ip()
    );
    return $result;
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
    var_dump($data);
}
add_action('wp_login_failed', 'login_trigger');

/**
 * Triggers if the page shows an 404 error
 */
function notfound_trigger()
{
    if (is_404()) {
        var_dump(get_request_env());
    }
}
add_action('template_redirect', 'notfound_trigger');
