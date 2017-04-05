<?php
return array(
	'zendoauth2' => array(
		'google' => array(
			'client_id'     => getenv('GOOGLE_CLIENT_ID'),
			'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
			'redirect_uri'  => getenv('GOOGLE_REDIRECT_URI'),
		),
	)
);