# HoneyPress

```
  /_)
(8_))}-  High interaction Honeypot for WordPress.
  \_)   
```

The HoneyPress projects adds high interaction honeypot features to an existing WordPress instance. The project can be installed as a regular plugin and wiill monitor given actions on the WordPress instance. HoneyPress can allow users to be created or being used by a defined username and password list. Activities will be logged on the logs/ directory inside the WordPress directory.

The project heavily depends on WordPress hook and action, but can also monitor file modifications inside the Wordpress files itself.

## Obvious Disclaimer

- 🛑 **This project is a playground. Use with caution :)** 
- 🛑 This honeypot _de facto_ introduces a Security issue on purpose to the WordPress. Use in isolated environments only
- 🛑 The honeypot itself might contain security issues

## Features

- [x] Logging of failed login interactions
- [x] Injecting of users into the instance for action tracking
- [x] Tracking of directory traversal attacts (with .htaccess redirect towards index.php)
- [x] Interception of xmlrpc calls
- [x] Timeout for sessions
- [x] FileSystem based logging
- [X] Proper deletion of honeypot users
- [x] Catching of file uploads inside WordPress
- [x] Monitoring of comments (spam)
- [x] Log activity inside the WP dashboard

## Activities

HoneyPress can monitor following actions

- `request` - A request was completed
- `dashboard` - A user navigated in the admin dashboad
- `usercleanup_timeout` - A user was removed (e. g. due session expire) by HoneyPress
- `useradd`/ `user_create` - A user was created by HoneyPress
- `usercleanup_logout`- A user was removed due to logout by HoneyPress
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
  "hidePlugin": true,
  "existingUsersOnly": false,
  "blockedLogins": [
    "admin"
  ],
  "generatorTag": "WordPress 5.721",
  "allowUploads": true,
  "expireUser": 10,
  "catchComments": true,
  "watchFiles": true,
  "userRole": "contributor",
  "logStyle": "json"
}
```
|Setting|Description|Default|
|---|---|--|
|mask|Modify the generator tag with the value of the `generatorTag` value|true|
|hidePlugin|Hide the plugin behind "Hello Dolly" (requires Hello Dolly to be present)|true|
|existingUsersOnly|If only existing users should be allowed to be logged in|false|
|blockedLogins|(if existingUsersOnly = false) don't create/ use following users (e. g. admin)| array|
|generatorTag|The meta generator tag to be used|WordPress 5.7|
|allowUploads|if true, uploads will be allowed, if false not. In both cases uploads will be logged|true|
|expireUser|(if existingUsersOnly = false) delete the user `n` seconds after login|60|
|catchComments|Should comments be monitored|true|
|watchFiles|Check the files for changes (slow operation)|true|
|userRole|The default role to assign to new users. Must be existing.|contributor|
|logStyle|The log style. Can be either a flat log or JSON (`flat`, `json`)|json|

# Logging

The honeypot logs as following:

```
logs/global.log # all activity

logs/<token>/credentials.log (if the user logged in)
logs/<token>/<timestamp><request|dashboard|usercleanup|useradd|usercleanup_logout|fileupload|comment|filedropnew|filedropdelete>.log (Activity)
```

## Log format

`global.log` uses following structure:

### `flat` style
```
[IP] [token or "No token"] ["See activities section of this readme"] logmessage
```

### `json` style

```
{
  "ip": "ip",
  "token": "token",
  "suffix": "See activities section of this readme",
  "action":  logmessage
}
```

**No** trailing , will be added to new entries. The log entries are not member of an array.

## Docker deployment

> To use this, make sure you have docker, docker-compose ready.

To create an instance, you can use the `deploy.sh` script. The script will create an MySQL and WordPress container with three volumes (DB, WP, Logs). Credentials will be created on the fly, the WP admin user will be whitelisted and printed in stdout. The deployment is done via docker compose, in case you need to adapt some settings. The WP instances will be configured to use localhost:<randomport> as the URL unless you provide the option `-u https://yourpageurl.tld`. For other options, see `deploy.sh -h`

To persist the results, you can use `takeout.sh`. The results will be stored in takeouts/. The takeout consists out of <id>.log and a folder <id>, containing uploads done by attackers.

To remove all containers and volumes, you can use `cleanup.sh`. The logs will persist.

## Content sources


- names.txt: Blog owner names to use (https://www.usna.edu/Users/cs/roche/courses/s15si335/proj1/files.php%3Ff=names.txt.html)
- themes.txt: Blog themes to use
- taglines: Blog descriptions to use.
- On deployment, there will be some posts created using (corporatelorem.sh and devlorem.sh, but you can add others, of course)

## Limitations

- This honeypot utilizes hooks and filters offered by WordPress. It is clear that only a subset ov available events can be monitored.

- It is not the goal to monitor everything possible, it is more an base monitoring what is going on with some crawlers or botnets.

- A modified file will trigger an `filedropdelete` event, followed by a `filedropnew` event.

## Updating instances

- When having `watchFiles` enabled, remove the `pre.json` file after the wordpress update. The initial state will be recreated (otherwise all files might be marked as modified)

## Mac remarks (for development)

- Install `gnu-sed` and `coreutils` using `brew`

## Examples

### Run an instance with a given port

```
deploy.sh -p 8181
```

### Run an instance with a given port (8555), an public URL (thisismypage.tld) and Traefik/ LetsEncrypt:

```
deploy.sh -p 8555 \
          -u https://thisismypage.tld \
          -l "traefik.enable=true,traefik.http.routers.thisismypage.rule=Host(\`thisismypage.tld\`),traefik.http.routers.thisismypage.entrypoints=websecure,traefik.http.routers.thisismypage.tls=true,traefik.http.routers.thisismypage.tls.certresolver=letsencrypt,traefik.docker.network=traefiknet,traefik.http.services.thisismypage.loadBalancer.server.port=8555"
```

## Recommendations

- Use an proxy for outgoing connections (so you can monitor installed droppers)
- Use containers and/ or virtual machines
- Apply a regular reset of the WordPress instances
- Take caution when using the administrator role for new users
- 🛑 **Don't use this on a production environment** 🛑 
- Prevent access throught the webserver towards `logs/` and `honeypress.json` (redirect it to 404)
- Implement a log rotation on `global.log`

## License

Apache 2.0