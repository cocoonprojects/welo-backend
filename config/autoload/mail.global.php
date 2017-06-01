<?php
return [
    'mail_domain' => getenv('MAIL_DOMAIN'),
    'acmailer_options' => [
		'default' => [
			'mail_adapter' => '\Zend\Mail\Transport\Sendmail',
			'message_options' => [
				'from' => 'no-reply@getwelo.com',
				'from_name' => 'Welo',
				//'to' => [],
				//'cc' => [],
				//'bcc' => [],
				//'subject' => '',
				'body' => [
					//'content' => '',
					'charset' => 'utf-8',
					'use_template' => true,
					'template' => [
						//    'path'          => 'ac-mailer/mail-templates/layout',
						//    'params'        => [],
						//    'children'      => [
						//        'content'   => [
						//            'path'   => 'ac-mailer/mail-templates/mail',
						//            'params' => [],
						//        ]
						//    ],
						'default_layout' => [
							'path' => 'mail/layout.phtml',
							'params' => [],
							'template_capture_to' => 'content'
						]
					],
				],
			],
			'file_options' => [
			],
		]
	]
];