<?php
return [
	'mail_domain' => 'http://welo.dev/',
	'acmailer_options' => [
		'default' => [
			'mail_adapter' => '\Zend\Mail\Transport\Smtp',
			'smtp_options' => [
				'host' => 'localhost',
				'port' => 1025,
				'connection_class' => 'smtp',
			],
			'file_options' => [
			],
		]
	]
];