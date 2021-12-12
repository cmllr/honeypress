# HoneyPress

```
  /_)
(8_))}-  High interaction Honeypot for WordPress.
  \_)   
```

The goal is to monitor activities on the instance. These activities can be attempted logins, comment spam or in general requests towards the instance to drop scripts.

The Honeypot can utilize defined users and/ or create users on the fly (with a limited lifespan) and monitors the acitvity as JSON file. Activities will be logged on the logs/ directory inside the WordPress directory.

🛑 **This project is a playground. Use with caution :)** 🛑 

## Features

- [x] Logging of failed login interactions
- [x] Injecting of users into the instance for action tracking
- [x] Tracking of directory traversal attacts (with .htaccess redirect towards index.php)
- [x] Interception of xmlrpc calls
- [x] Timeout for sessions
- [x] FileSystem based logging
- [X] Proper deletion of honeypot users
- [x] Catching of file uploads inside WordPress
- [x] Monitoring of given comments (spam)
- [x] Log activity inside the WP dashboard

## Activities

HoneyPress can monitor following actions

- `request` - A request was completed
- `dashboard` - A user navigated in the admin dashboad
- `usercleanup` - A user was removed (e. g. due session expire)
- `useradd` - A user was created 
- `usercleanup_logout`- A user was removed due to logout
- `comment` - A comment was done
- `fileupload` - A file was uploaded out of the WordPress backend
- `filedropnew` - A file was dropped somewhere in the WordPress installation 1) 2)
- `filedropdelete` - A file was removed somewhere in the WordPress installation 1) 2)


### Remarks 
1) if a file was changed, a `filedropdelete` and `filedropnew` action will be caused.
2) not for wp-content/uploads

## Setup

Make sure not found files are being redirected to the index.php of the WordPress instance (allowing the plugin to catch these requests).

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
  "expireUser": 10,
  "catchComments": true,
  "watchFiles": true
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
|catchComments|Should comments be monitored|true|
|watchFiles|Check the files for changes (slow operation)|true|

Install the HoneyPress plugin into WordPress. Make sure the "Hello Dolly plugin is present". 

In case you give the default user role permission to access the plugin list, HoneyPress will try to mask itself behind Hello Dolly's description.

# Logging

The honeypot logs as following:

```
logs/global.log # all activity

logs/<token>/credentials.log (if the user logged in)
logs/<token>/<timestamp><request|dashboard|usercleanup|useradd|usercleanup_logout|fileupload|comment|filedropnew|filedropdelete>.log (Activity)
```

`global.log` uses following structure:

```
[IP] [token or "No token"] [request|dashboard|usercleanup|useradd|usercleanup_logout|fileupload|comment|filedropnew|filedropdelete] logmessage
```

## Recommendations

- Use an proxy for outgoing connections (so you can monitor installed droppers)
- Use containers and/ or virtual machines
- Apply a regular reset of HoneyPress instances
- 🛑 **Don't use this on a production environment** 🛑 
- Make the `wp-contents/` directory readonly
- Prevent access throught the webserver towards `logs/` and `honeypress.json` (redirect it to 404)

## License

Apache 2.0