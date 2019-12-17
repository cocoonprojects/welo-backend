<?php

$invite = new \stdClass();
$invite->guestName = '';
$invite->guestEmail = '';
$invite->invitedBy = ''; //$user->getFirstname().' '.$user->getLastname();
$invite->orgName = '';
$invite->orgId = '';     //$organization->getId();
$invite->invitedOn = date('d/m/Y');

$hash = base64_encode(json_encode($invite));

$baseUrl = "http://app.getwelo.com/index.html#/organizations/acceptinvite?token=$hash";

echo $baseUrl, "\n";