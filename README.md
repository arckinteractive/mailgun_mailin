Mailgun - Content Mail-In
-------------------------
![Elgg 2.0](https://img.shields.io/badge/Elgg-2.0.x-orange.svg?style=flat-square)

Allows users to post new content by emailing it

Supports new:

* Blogs
* Bookmarks
* Files
* Discussions

## Customizations

### Adding a custom parser

To add a new content type to supported items, register a handler for `'mailin:types','mailgun'`
and add your type definition to the list. Type definition contains a callable handler and a list of
allowed fields:

```php

elgg_register_plugin_hook_handler('mailin:types', 'mailgun', function($hook, $type, $return) {

	$return['event'] = [
		'handler' => 'my_mailin_event_parser',
		'fields' => [
			'fields' => [
				'date',
				'location',
				'description',
			],
		],
	];
});

function my_mailin_event_parser(\ArckInteractive\Mailgun\Message $message, $fields) {

	$date = strtotime($fields['date']);
	$location = $fields['location'];

	if (!$date || !$location) {
		return false;
	}

	// save the event
	// note that this will be performed via cron, so there is no logged in user
	// you will need to set ignored access after checking container/edit permissions

	return true;
}
```

Once this is set, the user will see a EVENT type with a list of fields in their settings:
Settings > Content Mail-In.

Users can then send an email in the following format:

```
**Subject**
EVENT: Birthday party

**Message**
DATE: August 10, 2017 10:00
LOCATION: Some venue
DESCRIPTION:
I am hosting a birthday party, so come help me celebrate
```




