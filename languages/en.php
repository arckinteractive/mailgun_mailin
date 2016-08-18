<?php

return [
	'mailgun:mailin' => 'Content Mail-In',
	'mailgun:mailin:instructions' => '
		<p>
		Below you will find email addresses to which you can email new content.
		Each email corresponds with the target location: your personal account or one of the groups you are a member of.
		</p>

		<p>
		The subject of your email should contain the type of post you want to make and its title:<br />
		ARTICLE: Tech Talk
		</p>

		<p>
		The body of the your email should include fields supported for the specific content type:<br />
		DATE: August 18, 2016 10:50am<br />
		LINK: http://example.com/path/to/article <br />
		DESCRIPTION:<br />
		This article is very interesting. You should check it out <br />
		</p>
		',
];