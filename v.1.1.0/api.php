<?php

	include('jasmin.php');
	include('format.php');
	include('settings.php');

	/*$request = array(
			'request' => 'list',
			'action' => 'select',
			'view' => 'list',
			'perpage' => 40,
			'extra' => 1,
	); 
	*/
	/*$request = array(
			'request' => 'item_list',
			'action' => 'update',
			'extra' => 1,
			'company' => 'viplab',
			'lang' => 'ru',
			'name' => 'Иванцов Михаил',
			'id' => 39
	); 

	$request = array(
			'request' => 'item_list',
			'action' => 'insert',
			'extra' => 1,
			'company' => 'asdasdad',
			'lang' => 'ru',
			'name' => 'mazafaka',
			'email' => 's@s.ru',
			'sex' => 'mazafaka',
			'id' => 39
	); 

	$request = array(
			'request' => 'item_list',
			'action' => 'select',
			'extra' => 1,
			'id' => 39
	); 


	$request = array(
			'request' => 'item_list',
			'action' => 'delete',
			'extra' => 1,
			'id' => 390
	); 
	*/

$result = Jasmin(array(
	'format' => $_format,
	'settings' => $settings,
	'request' => array_merge($_GET,$_POST),
));

if($_GET['r'])
	print_r($result);
else
	die(json_encode($result));




/*
$login = 'root';
$password = '3ec2ca7d7f0e28d130e8dbe0677881e5';*/








