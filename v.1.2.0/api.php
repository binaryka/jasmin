<?php

	include('jasmin.php');
	include('format.php');
	include('settings.php');

	$result = Jasmin(array(
		'format' => $_format,
		'settings' => $settings,
		'request' => array_merge($_GET,$_POST),
	));


	if( $_GET['r'] )
		print_r($result);
	else
		die( json_encode($result) );