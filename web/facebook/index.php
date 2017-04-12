<?php
session_start();
require_once '../../vendor/autoload.php';

use Facebook\Facebook;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$configPath = '.config.json';
if(!file_exists($configPath))
	throw new \Exception('Not found .config.json');
$contents = file_get_contents($configPath);

$config = (array) json_decode($contents);

$fb = new Facebook($config);

function printJSON() {
	global $fb;
	
	$fb->setDefaultAccessToken($_SESSION['facebook.token']);

	try {
	  $response = $fb->get('/me?fields=first_name,last_name,work,education,email,location,picture,id');
	  $userinfo = $response->getDecodedBody();
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
	  // When Graph returns an error
	  echo 'Graph returned an error: ' . $e->getMessage();
	  exit;
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
	  // When validation fails or other local issues
	  echo 'Facebook SDK returned an error: ' . $e->getMessage();
	  exit;
	}
	$profile = [];
	foreach($userinfo as $key => $info) {
		switch($key) {
		case 'first_name':
		case 'last_name':
		case 'email':
		case 'id':
			$profile[$key] = $info;
			break;
		case 'work':
		case 'education':
			foreach($info as $f) {
				$subfield = [];
				foreach($f as $k => $l) {
					if($k == 'id') continue;
					if(is_array($l))
						$subfield[$k] = $l['name'];
					else
						$subfield[$k] = $l;
				}
				$profile[$key][] = $subfield;
			}
			break;
		case 'location':
			$profile[$key] = $info['name'];
			break;
		case 'picture':
			$profile[$key] = $info['data']['url'];
			break;
		}
	}
	$profile['source'] = 'Facebook';
	$profile['sourceContactId'] = $profile['id'];
	unset($profile['id']);
	
	if($profile)
		$printableJSON = json_encode($profile, JSON_PRETTY_PRINT);
	header("Content-disposition: attachment; filename=facebook.json");
	header("Content-type: application/json");
	echo $printableJSON;
	exit;
}
if(!isset($_SESSION['facebook.token'])) {
	$helper = $fb->getRedirectLoginHelper();
	try {
		$accessToken = $helper->getAccessToken();
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
		echo 'Graph returned an error: ' . $e->getMessage();
		exit;
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
		echo 'Facebook SDK returned an error: ' . $e->getMessage();
		exit;
	}
	if (isset($accessToken)) {
		// Logged in!
		$_SESSION['facebook.config'] = $config;
		$_SESSION['facebook.token'] = (string) $accessToken;
		
		printJSON();
	}
	else {
		$permissions = ['user_posts', 'publish_pages', 'publish_actions', 'manage_pages'];
		$isHttps = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] === '443')));
		
		$redirect = ($isHttps ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$loginURL = $helper->getLoginUrl($redirect, $permissions);
		header("Location: " . $loginURL);
	}
}
else {
	printJSON();
}
?>