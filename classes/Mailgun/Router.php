<?php

namespace Mailgun;

class Router {
	
	/**
	 * Routes pages
	 * 
	 * @param string $hook   "route"
	 * @param string $type   "mailgun"
	 * @param array  $return Route details
	 * @param array  $params Hook params
	 * @return array|false
	 */
	public static function route($hook, $type, $return, $params) {

		if (!is_array($return)) {
			return; // another hook kicked in
		}
		
		$identifier = elgg_extract('identifier', $return);
		$segments = (array) elgg_extract('segments', $return, []);

		if ($identifier !== 'mailgun') {
			return;
		}

		if ($segments[0] == 'mailin') {
			echo elgg_view_resource('mailgun/mailin', [
				'username' => $segments[1],
			]);
			return false; // stop further routing
		}
	}
}
