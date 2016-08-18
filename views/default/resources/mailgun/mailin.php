<?php

$username = elgg_extract('username', $vars);
$user = get_user_by_username($username);

if (!$user || !$user->canEdit()) {
	forward('', '403');
}

elgg_set_context('settings');

elgg_set_page_owner_guid($user->guid);

elgg_push_breadcrumb(elgg_echo('settings'), "settings/user/$username");

$title = elgg_echo('mailgun:mailin');
$content = elgg_view('mailgun/mailin/user', [
	'entity' => $user,
]);

$layout = elgg_view_layout('content', [
	'content' => $content,
	'title' => $title,
	'filter' => false,
]);

echo elgg_view_page($title, $layout);
