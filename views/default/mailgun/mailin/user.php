<?php
$entity = elgg_extract('entity', $vars);
if (!$entity instanceof ElggUser || !$entity->canEdit()) {
	return;
}

$recipient = elgg_get_plugin_setting('recipient', 'mailgun') ? : 'mailin';
$domain = elgg_get_plugin_setting('domain', 'mailgun');

$token = mailgun_get_entity_notification_token($entity, 'mailin');
$email = "$recipient+$token@$domain";

$addresses = [
	$entity->getDisplayName() => $email,
];

$groups = new ElggBatch('elgg_get_entities_from_relationship', [
	'relationship' => 'member',
	'relationship_guid' => $entity->guid,
	'limit' => 0,
]);

foreach ($groups as $group) {
	$token = mailgun_get_entity_notification_token($group, "mailin:$entity->guid");
	$addresses[$group->getDisplayName()] = "$recipient+$token@$domain";
}
?>
<p class="mailgun-mailin-instructions">
	<?= elgg_echo('mailgun:mailin:instructions') ?>
</p>
<ul class="mailgun-mailing-supported-types elgg-list">
	<?php
	$types = Mailgun\Mailin::getTypes();
	foreach ($types as $type => $options) {
		$fields = array_map('strtoupper', (array) $options['fields']);
		?>
		<li class="elgg-item">
			<strong><?= strtoupper($type) ?></strong>: <br />
			<i><?= implode(', ', $fields) ?></i></li>
		<?php
	}
	?>
</ul>
<ul class="mailgun-mailin-addresses elgg-list">
	<?php
	foreach ($addresses as $name => $email) {
		?>
		<li class="elgg-item">
			<strong><?= $name ?></strong>:
			<?=
			elgg_view('output/url', [
				'text' => $email,
				'href' => "mailto:$email",
				'is_trusted' => true,
			]);
			?>
		</li>
		<?php
	}
	?>
</ul>