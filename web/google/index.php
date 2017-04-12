<?php
session_start();
require_once '../../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$configPath = '.config.json';
if(!file_exists($configPath))
	throw new \Exception('Not found .config.json');
$contents = file_get_contents($configPath);

$config = json_decode($contents);

function getClient() {
	global $config;
	
	$client = new Google_Client();
	$client->setApplicationName('Jobs2Careers');
	// $client->addScope(Google_Service_Plus::PLUS_ME);
	$client->setScopes(array('https://www.googleapis.com/auth/plus.login','https://www.googleapis.com/auth/plus.me','https://www.googleapis.com/auth/userinfo.email','https://www.googleapis.com/auth/userinfo.profile'));
	$client->setClientId($config->clientID);
	$client->setClientSecret($config->clientSecret);
	$client->setRedirectUri($config->redirectUri);
	$client->setAccessType('offline');
	$client->setApprovalPrompt('force');
	$client->setDeveloperKey($config->developerKey);
	
	return $client;
}
function printJSON() {
	$client = getClient();
	$client->setAccessToken($_SESSION['google.token']);
	// $oauth2 = new Google_Service_Oauth2($client);
	// $userInfo = $oauth2->userinfo->get();
	// print_r($userInfo);
	$httpClient = $client->authorize();

	// make an HTTP request
	$response = $httpClient->get('https://www.googleapis.com/plus/v1/people/me?fields=aboutMe%2CcurrentLocation%2Cemails%2Cid%2Cimage%2Furl%2Cname%2CplacesLived%2Corganizations');
	$profile = (array)json_decode($response->getBody());

	$profile['firstName'] = $profile['name']->givenName;
	$profile['lastName'] = $profile['name']->familyName;
	unset($profile['name']);
	
	$profile['work'] = [];
	$profile['education'] = [];
	if(isset($profile['organizations']) && count($profile['organizations']) > 0) {
		foreach($profile['organizations'] as $org) {
			$org1 = (array)$org;
			unset($org1['type']);
			unset($org1['primary']);
			if($org->type == 'work')
				$profile['work'][] = $org1;
			elseif($org->type == 'school')
				$profile['education'][] = $org1;
		}
		unset($profile['organizations']);
	}
	
	foreach($profile['emails'] as $email)
		$profile['email'][] = $email->value;
	unset($profile['emails']);
	
	if(isset($profile['placesLived']) && count($profile['placesLived']) > 0) {
		$location = (array)$profile['placesLived'][0];
		unset($location['primary']);
		$profile['location'] = $location;
		unset($profile['placesLived']);
	}
	
	$profile['profilePhotoUrl'] = $profile['image']->url;
	unset($profile['image']);
	
	$profile['source'] = 'Google';
	$profile['sourceContactId'] = $profile['id'];
	unset($profile['id']);
	
	if($profile)
		$printableJSON = json_encode($profile, JSON_PRETTY_PRINT);
	header("Content-disposition: attachment; filename=google.json");
	header("Content-type: application/json");
	echo $printableJSON;
	exit;
}
if(!isset($_SESSION['google.token']) || !$_SESSION['google.token']) {
	if(isset($_GET['code'])) {
		$client = getClient();
		$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
		$client->setAccessToken($token);
		
		if (!isset($token['refresh_token'])) {
			var_dump($token);
			die('Invalid access token! Try again later.');
		}
		$_SESSION['google.token'] = $token;
		printJSON();
	}
	else {
		$client = getClient();
		$authURL = $client->createAuthUrl();

		header("Location: " . $authURL);
	}
}
else {
	printJSON();
}
?>