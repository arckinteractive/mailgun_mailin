<?php

namespace Mailgun;

use ArckInteractive\Mailgun\Message;
use ElggGroup;
use ElggObject;

class Mailin {

	/**
	 * Get type configuration
	 * @return array
	 */
	public static function getTypes() {

		$types = [];

		if (elgg_is_active_plugin('blog')) {
			$types['blog'] = [
				'handler' => [__CLASS__, 'createBlog'],
				'fields' => [
					'excerpt',
					'description',
					'tags',
				],
			];
		}

		if (elgg_is_active_plugin('bookmarks')) {
			$types['bookmark'] = [
				'handler' => [__CLASS__, 'createBookmark'],
				'fields' => [
					'description',
					'address',
					'tags',
				],
			];
		}

		if (elgg_is_active_plugin('file')) {
			$types['file'] = [
				'handler' => [__CLASS__, 'createFile'],
				'fields' => [
					'description',
					'tags',
				],
			];
		}

		if (elgg_is_active_plugin('discussions')) {
			$types['discussion'] = [
				'handler' => [__CLASS__, 'createDiscussion'],
				'fields' => [
					'description',
					'tags',
				],
			];
		}

		return elgg_trigger_plugin_hook('mailin:types', 'mailgun', null, $types);
	}

	/**
	 * Process incoming message
	 * 
	 * @param string  $event   "receive"
	 * @param string  $type    "mg_message"
	 * @param Message $message Incoming message
	 * @return bool
	 */
	public static function receiveMessage($event, $type, $message) {

		$entity = $message->getTargetEntity();
		$sender = $message->getSender();

		if (!$entity || !$sender) {
			return;
		}

		// Check if the recipient token belongs to the sender
		$handler = $entity instanceof ElggGroup ? "mailin:$sender->guid" : "mailin";
		$token = mailgun_get_entity_notification_token($entity, $handler);

		$recipient_token = $message->getRecipientToken();
		if (!self::areEqual($token, $recipient_token)) {
			return;
		}

		$subject = $message->getSubject();

		// Match subject again known types
		$types = self::getTypes();
		foreach ($types as $type => $options) {
			$type = strtoupper($type);
			$matches = [];

			// Case sensitive match for TITLE: title
			if (!preg_match("/($type)+:(.*)/", $subject, $matches)) {
				continue;
			}

			if (!is_callable($options['handler'])) {
				continue;
			}

			$fields = [
				'title' => trim($matches[2]),
			];

			$body = $message->getText();

			// Normalize line endings after FIELD:
			$body = preg_replace("/([A-Z]+):(\s+)/s", "$1:", $body);

			// Add two new lines before each FIELD: so we can parse paragraph
			$body = preg_replace("/([A-Z]+):/s", "\n\n$1: ", $body);

			// Break down into FIELD: field
			preg_match_all("/([A-Z]+):(.*?)(\n{2,}(?![A-Z])|$)/s", $body, $matches);

			$keys = array_keys($matches[1]);
			foreach ($keys as $key) {
				$label = strtolower($matches[1][$key]);
				$value = $matches[2][$key];
				if (in_array($label, $options['fields'])) {
					$fields[$label] = trim($value);
				}
			}

			if (call_user_func($options['handler'], $message, $fields)) {
				return false; // terminate event
			}
		}
	}

	/**
	 * Create a new blog
	 *
	 * @param Message $message Incoming message
	 * @param array   $fields  Parsed fields
	 * @return bool
	 */
	public static function createBlog(Message $message, array $fields = []) {

		if (!elgg_is_active_plugin('blog')) {
			return false;
		}

		$sender = $message->getSender();
		if (!$sender) {
			return false;
		}

		$entity = $message->getTargetEntity();
		if (!$entity) {
			return false;
		}

		if (!$entity->canWriteToContainer($sender->guid, 'object', 'blog')) {
			return false;
		}

		$class = get_subtype_class('object', 'blog');
		if (!$class || !class_exists($class)) {
			$class = ElggObject::class;
		}

		$access_id = ACCESS_LOGGED_IN;
		if ($entity instanceof ElggGroup) {
			$access_id = $entity->group_acl;
		} else if ($entity instanceof ElggObject) {
			$access_id = $entity->access_id;
		}

		$title = elgg_extract('title', $fields, '');
		$title = htmlentities($title, ENT_QUOTES, 'UTF-8');

		$ia = elgg_set_ignore_access(true);

		$description = elgg_autop(elgg_extract('description', $fields, ''));
		$excerpt = elgg_extract('excerpt', $fields);
		if (!$excerpt) {
			$excerpt = elgg_get_excerpt($description, 137);
		}
		$response = new $class();
		$response->subtype = 'blog';
		$response->owner_guid = $sender->guid;
		$response->container_guid = $entity->guid;
		$response->title = $title;
		$response->description = $description;
		$response->tags = string_to_tag_array(elgg_extract('tags', $fields, ''));
		$response->access_id = $access_id;
		$response->excerpt = $excerpt;
		$response->status = 'published';
		$response->origin = 'mailgun';

		$guid = $response->save();

		elgg_set_ignore_access($ia);

		if (!$guid) {
			return false;
		}

		if (elgg_is_active_plugin('hypeAttachments')) {
			$attributes = [
				'origin' => ['mailgun', 'attachments'],
				'subtype' => 'file',
				'access_id' => $response->access_id,
				'owner_guid' => $sender->guid,
				'container_guid' => $response->container_guid,
			];
			$attachments = $message->getAttachments($attributes);
			foreach ($attachments as $attachment) {
				hypeapps_attach($response, $attachment);
			}
		}

		elgg_create_river_item(array(
			'view' => 'river/object/blog/create',
			'action_type' => 'create',
			'subject_guid' => $response->owner_guid,
			'object_guid' => $response->guid,
		));

		elgg_log("Message {$message->getMessageId()} has been saved as a blog [guid: {$response->guid}]");
		return true;
	}

	/**
	 * Create a new bookmark
	 *
	 * @param Message $message Incoming message
	 * @param array   $fields  Parsed fields
	 * @return bool
	 */
	public static function createBookmark(Message $message, array $fields = []) {

		if (!elgg_is_active_plugin('bookmarks')) {
			return false;
		}

		$sender = $message->getSender();
		if (!$sender) {
			return false;
		}

		$entity = $message->getTargetEntity();
		if (!$entity) {
			return false;
		}

		if (!$entity->canWriteToContainer($sender->guid, 'object', 'bookmarks')) {
			return false;
		}

		$title = elgg_extract('title', $fields, '');
		$title = htmlentities($title, ENT_QUOTES, 'UTF-8');

		$address = elgg_extract('address', $fields, '');
		$address = str_replace([PHP_EOL, ' '], '', $address);

		if ($address && !preg_match("#^((ht|f)tps?:)?//#i", $address)) {
			$address = "http://$address";
		}
		if (!$title || !$address || !filter_var($address, FILTER_VALIDATE_URL)) {
			return false;
		}

		$class = get_subtype_class('object', 'bookmarks');
		if (!$class || !class_exists($class)) {
			$class = ElggObject::class;
		}

		$access_id = ACCESS_LOGGED_IN;
		if ($entity instanceof ElggGroup) {
			$access_id = $entity->group_acl;
		} else if ($entity instanceof ElggObject) {
			$access_id = $entity->access_id;
		}

		$ia = elgg_set_ignore_access(true);

		$response = new $class();
		$response->subtype = 'bookmarks';
		$response->owner_guid = $sender->guid;
		$response->container_guid = $entity->guid;
		$response->title = $title;
		$response->description = elgg_autop(elgg_extract('description', $fields, ''));
		$response->tags = string_to_tag_array(elgg_extract('tags', $fields, ''));
		$response->access_id = $access_id;
		$response->address = $address;
		$response->origin = 'mailgun';

		$guid = $response->save();

		elgg_set_ignore_access($ia);

		if (!$guid) {
			return false;
		}

		if (elgg_is_active_plugin('hypeAttachments')) {
			$attributes = [
				'origin' => ['mailgun', 'attachments'],
				'subtype' => 'file',
				'access_id' => $response->access_id,
				'owner_guid' => $sender->guid,
				'container_guid' => $response->container_guid,
			];
			$attachments = $message->getAttachments($attributes);
			foreach ($attachments as $attachment) {
				hypeapps_attach($response, $attachment);
			}
		}

		elgg_create_river_item(array(
			'view' => 'river/object/bookmarks/create',
			'action_type' => 'create',
			'subject_guid' => $sender->guid,
			'object_guid' => $response->guid,
		));

		elgg_log("Message {$message->getMessageId()} has been saved as a bookmark [guid: {$response->guid}]");
		return true;
	}

	/**
	 * Create a new file
	 *
	 * @param Message $message Incoming message
	 * @param array   $fields  Parsed fields
	 * @return bool
	 */
	public static function createFile(Message $message, array $fields = []) {

		$sender = $message->getSender();
		if (!$sender) {
			return false;
		}

		$entity = $message->getTargetEntity();
		if (!$entity) {
			return false;
		}

		if (!$entity->canWriteToContainer($sender->guid, 'object', 'file')) {
			return false;
		}

		$access_id = ACCESS_LOGGED_IN;
		if ($entity instanceof ElggGroup) {
			$access_id = $entity->group_acl;
		} else if ($entity instanceof ElggObject) {
			$access_id = $entity->access_id;
		}

		$ia = elgg_set_ignore_access(true);

		$attributes = [
			'origin' => ['mailgun', 'attachments'],
			'subtype' => 'file',
			'access_id' => $access_id,
			'owner_guid' => $sender->guid,
			'container_guid' => $entity->guid,
		];

		$attributes = array_merge($fields, $attributes);

		$attachments = $message->getAttachments($attributes);

		foreach ($attachments as $attachment) {
			elgg_create_river_item(array(
				'view' => 'river/object/file/create',
				'action_type' => 'create',
				'subject_guid' => $sender->guid,
				'object_guid' => $attachment->guid,
			));
		}

		elgg_log("Message {$message->getMessageId()} has been saved as file");
		return true;
	}

	/**
	 * Create a new discussion
	 *
	 * @param Message $message Incoming message
	 * @param array   $fields  Parsed fields
	 * @return bool
	 */
	public static function createDiscussion(Message $message, array $fields = []) {

		if (!elgg_is_active_plugin('discussions')) {
			return false;
		}

		$sender = $message->getSender();
		if (!$sender) {
			return false;
		}

		$entity = $message->getTargetEntity();
		if (!$entity instanceof ElggGroup) {
			return false;
		}

		if (!$entity->canWriteToContainer($sender->guid, 'object', 'discussion')) {
			// The sender is not allowed to create discussions
			return false;
		}

		$class = get_subtype_class('object', 'discussion');
		if (!$class) {
			$class = ElggObject::class;
		}

		$title = elgg_extract('title', $fields, '');
		$title = htmlentities($title, ENT_QUOTES, 'UTF-8');
	
		$description = elgg_autop(elgg_extract('description', $fields, ''));
		$tags = string_to_tag_array(elgg_extract('tags', $fields, ''));

		$ia = elgg_set_ignore_access(true);
		
		$response = new $class();
		$response->subtype = 'discussion';
		$response->owner_guid = $sender->guid;
		$response->container_guid = $entity->guid;
		$response->title = $title;
		$response->description = $description;
		$response->tags = $tags;
		$response->access_id = $entity->group_acl;
		$response->status = 'open';
		$response->origin = 'mailgun';

		$guid = $response->save();

		elgg_set_ignore_access($ia);

		if (!$guid) {
			return false;
		}

		if (elgg_is_active_plugin('hypeAttachments')) {
			$attributes = [
				'origin' => ['mailgun', 'attachments'],
				'subtype' => 'file',
				'access_id' => $response->access_id,
				'owner_guid' => $sender->guid,
				'container_guid' => $response->container_guid,
			];
			$attachments = $message->getAttachments($attributes);
			foreach ($attachments as $attachment) {
				hypeapps_attach($response, $attachment);
			}
		}

		elgg_create_river_item(array(
			'view' => 'river/object/discussion/create',
			'action_type' => 'create',
			'subject_guid' => $sender->guid,
			'object_guid' => $response->guid,
			'target_guid' => $response->container_guid,
		));

		elgg_log("Message {$message->getMessageId()} has been saved as a discussion [guid: {$response->guid}] "
		. "on {$entity->getDisplayName()} [guid: {$entity->guid}]");

		return true;
	}

	/**
	 * Are two strings equal (compared in constant time)?
	 *
	 * @param string $str1 First string to compare
	 * @param string $str2 Second string to compare
	 *
	 * @return bool
	 *
	 * Based on password_verify in PasswordCompat
	 * @author Anthony Ferrara <ircmaxell@php.net>
	 * @license http://www.opensource.org/licenses/mit-license.html MIT License
	 * @copyright 2012 The Authors
	 */
	public static function areEqual($str1, $str2) {
		$len1 = self::strlen($str1);
		$len2 = self::strlen($str2);
		if ($len1 !== $len2) {
			return false;
		}

		$status = 0;
		for ($i = 0; $i < $len1; $i++) {
			$status |= (ord($str1[$i]) ^ ord($str2[$i]));
		}

		return $status === 0;
	}

	/**
	 * Count the number of bytes in a string
	 *
	 * We cannot simply use strlen() for this, because it might be overwritten by the mbstring extension.
	 * In this case, strlen() will count the number of *characters* based on the internal encoding. A
	 * sequence of bytes might be regarded as a single multibyte character.
	 *
	 * Use elgg_strlen() to count UTF-characters instead of bytes.
	 *
	 * @param string $binary_string The input string
	 *
	 * @return int The number of bytes
	 *
	 * From PasswordCompat\binary\_strlen
	 * @author Anthony Ferrara <ircmaxell@php.net>
	 * @license http://www.opensource.org/licenses/mit-license.html MIT License
	 * @copyright 2012 The Authors
	 */
	public static function strlen($binary_string) {
		if (function_exists('mb_strlen')) {
			return mb_strlen($binary_string, '8bit');
		}
		return strlen($binary_string);
	}

}
