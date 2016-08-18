<?php

/**
 * mailgun_mailin
 * Converts inbound email into new site content
 *
 * @author      Ismayil Khayredinov <ismayil@arckinteractive.com>
 * @copyright   Copyright (c) 2016 ArckInteractive LLC
 */
require_once __DIR__ . '/autoloader.php';

elgg_register_event_handler('init', 'system', function() {

	$user = elgg_get_logged_in_user_entity();
	if ($user) {
		elgg_register_menu_item('page', [
			'name' => 'mailgun:mailin',
			'text' => elgg_echo('mailgun:mailin'),
			'href' => "mailgun/mailin/$user->username",
			'context' => ['settings', 'notifications'],
		]);
	}

	elgg_register_plugin_hook_handler('route', 'mailgun', [\Mailgun\Router::class, 'route']);
	
	elgg_register_event_handler('receive', 'mg_message', [\Mailgun\Mailin::class, 'receiveMessage']);
});
