<?php

function create_log_folder($id, $credentials)
{
  if (!$id) {
    return; /* no empty id folders */
  }
  $rootFolder = ABSPATH . "/logs";

  if (!is_dir($rootFolder)) {
    mkdir($rootFolder);
  }
  if (!is_dir($rootFolder . "/" . $id)) {
    mkdir($rootFolder . "/" . $id);
  }
  file_put_contents($rootFolder . "/" . $id . "/credentials.json", json_encode($credentials));
  $data = get_request_env();
  $data["credentials"] = $credentials;
  log_action($id, $data, false, "useradd");
}

function generateRandomString($length = 10)
{
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $charactersLength = strlen($characters);
  $randomString = '';
  for ($i = 0; $i < $length; $i++) {
    $randomString .= $characters[rand(0, $charactersLength - 1)];
  }
  return $randomString;
}


/**
 * Get an generic info about the request
 * 
 * @returns get, post, target, userAgent, referrer, ip
 */
function get_request_env()
{
  $xmlContent = defined("XMLRPC_REQUEST") ? file_get_contents('php://input') : "";
  $result = array(
    "get" => $_GET,
    "post" => $_POST,
    "target" => get_value("REQUEST_URI", $_SERVER),
    "userAgent" => get_value("HTTP_USER_AGENT", $_SERVER),
    "referrer" => get_value("HTTP_REFERER", $_SERVER),
    "ip" => get_ip(),
    "notFound" => is_404(),
    "isAdmin" => is_admin(),
    "isFailedLogin" => false
  );
  if (defined("XMLRPC_REQUEST")) {
    $result["isXMLRPC"] = true;
    $result["xmlPayload"] = $xmlContent;
  }
  return $result;
}


function getSetting($key)
{
  $defaults = array(
    "mask" => true,
    "blockedLogins" =>  [
      "admin"
    ],
    "existingUsersOnly" => true,
    "deleteAfterLogin" =>  true,
    "generatorTag" =>  "WordPress 5.7",
    "allowUploads" => true,
    "expireUser" => 60,
    "catchComments" => true
  );

  $configPath = ABSPATH . "/honeypress.json";

  $config = null;
  if (is_file($configPath)) {
    $config = json_decode(file_get_contents($configPath));
  }
  if ($config) {
    $configKeys = get_object_vars($config);
    if (in_array($key, $configKeys)) {
      return $config->{$key};
    }
  }
  $keys = array_keys($defaults);
  if (in_array($key, $keys)) {
    return $defaults[$key];
  }
  return null;
}


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
