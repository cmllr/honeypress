# Honeypress

Honeypress is a high interaction honeypot operating on top of a real WordPress instance.

🛑 **Don't use this on a production environment** 🛑 

## What is that?

The honeypot uses an actual WordPress instance and injects users on `wp_login_failed` into the instance.

The attacker can freely navigate in WordPress according to the default WP role.

After logout, the user will be deleted if not a prepared user was utilized.

Uploaded files will be collected.

## Features

- [x] Logging of failed login interactions
- [x] Injecting of users into the instance for action tracking
- [x] Tracking of directory traversal attacts (with .htaccess redirect towards index.php)
- [x] Interception of xmlrpc calls
- [x] Timeout for sessions
- [x] FileSystem based logging
- [X] Proper deletion of honeypot users
- [x] Catching of file uploads inside WordPress

## Setup

### .htaccess

Add following code to the .htaccess file:

```
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /wordpress/index.php [L]

<Files "honeypress.json">  
  Redirect 404
</Files>
```

### Setup plugin


Place following `honeypress.json` in your WordPress root folder.

```
{
  "mask": true,
  "existingUsersOnly": false,
  "blockedLogins": [
    "admin"
  ],
  "generatorTag": "WordPress 5.721",
  "allowUploads": true,
  "expireUser": 10
}
```
|Setting|Description|Default|
|---|---|--|
|mask|Hide the plugin behind "Hello Dolly"|true|
|existingUsersOnly|If only existing users should be allowed to be logged in|false|
|blockedLogins|(if existingUsersOnly = false) don't create/ use following users (e. g. admin)| array|
|generatorTag|The meta generator tag to be used|WordPress 5.7|
|allowUploads|if true, uploads will be allowed, if false not. In both cases uploads will be logged|true|
|expireUser|(if existingUsersOnly = false) delete the user `n` seconds after login|60|


Install the HoneyPress plugin into WordPress. Make sure the "Hello Dolly plugin is present". 

In case you give the default user role permission to access the plugin list, HoneyPress will try to mask itself behind Hello Dolly's description.


## License

GPLv3