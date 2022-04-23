<?php
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
  $userName = $credentials["credentials"]["user"];
  $password = $credentials["credentials"]["password"];
  log_action($id, $data, false, "Created session folder $id ($userName:$password)", "session_create");
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
    "hidePlugin" => true,
    "blockedLogins" =>  [
      "admin"
    ],
    "existingUsersOnly" => true,
    "deleteAfterLogin" =>  true,
    "generatorTag" =>  "WordPress 5.7",
    "allowUploads" => true,
    "expireUser" => 60,
    "catchComments" => true,
    "watchFiles" => true,
    "userRole" => "contributor",
    "logStyle" => "flat"
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

/**
 * Return a global identifier for a file, consisting out of the modify time, a path hash (sha1) and a CRC32 checksum of the file
 */
function get_file_id($filename){
  $mtime = filemtime($filename);
  $pathHash = sha1($filename);
  $hash = hash_file("CRC32", $filename, false);
  return $mtime.$pathHash.$hash;
}

/* https://stackoverflow.com/questions/24783862/list-all-the-files-and-folders-in-a-directory-with-php-recursive-function */
function getDirContents($dir, &$results = array()) {
  $files = scandir($dir);

  foreach ($files as $key => $value) {
      $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
      if (!is_dir($path)) {
        if (strpos($path, "honeypress") === false && strpos($path, "uploads") == false){
          $results[get_file_id($path)] = $path;
        }
      } else if ($value != "." && $value != "..") {
        if (strpos($path, "logs") === false){
          getDirContents($path, $results);
        }
      }
  }

  return $results;
}

/**
 *  Checks if the current user is a blocked user, e. g. preventing logs and deletion
 */
function is_blocked_user() {

  if (!function_exists("wp_get_current_user")) {
    return false;
  }
  $user = wp_get_current_user();
  if (!$user){
    return true;
  }

  $name = $user->user_login;
  $blockedLogins = getSetting("blockedLogins");
  return in_array($name, $blockedLogins);
}