<?php if (!class_exists('CFRuntime')) die('No direct access allowed.');

CFCredentials::set(array(
	'development' => array(
		'key' => 'KEYHERE',
		'secret' => 'SECRETHERE',
		'default_cache_config' => '',
		'certificate_authority' => false
	),
	'@default' => 'development'
));
