<?
/*
	Jasmin - multitasking system.
	Version - 1.1.0
	Copyright (C) 2012  binarykacom@gmail.com

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/* TODO
- Написать функцию init_check(), которая будет проверять весь форматс на правильность заполнения данных 
  и убивать процесс до того как че то начнется, так сказать вынесем все проверки за поле боя

- Написать функцию put_values(), которая будет подставлять данные в запросы

- Сделать обработку filtering в select
- Вынести extra блоки в анонимные функции

*/

error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );

// Подключаем функции
if(file_exists('functions.php'))
	include "functions.php";
else
	die('functions.php not found');


function Jasmin($arg){

	global $jas_actions, $jas_result_views, $storage, $db;

	$storage = Jas_storage::Instance();

	jas_set_mem('begin');

	$default_value = array(
		'select' => array(
			'where' => '1',
			'limit' => '10'
		),
		'update' 		=> array(),
		'multiupdate' 	=> array(),
		'multidelete' 	=> array(),
		'insert' 		=> array(),
		'delete' 		=> array()
	);

	$format_requare = array(
		'select' 		=> array('fields'),
		'update' 		=> array('fields','where'),
		'insert' 		=> array('fields'),
		'delete' 		=> array('where'),
		'multiupdate' 	=> array('maxrows'),
		'multidelete' 	=> array('maxrows')
	);

	$do_not_check_action = array(
		'filtering',
		'frame',
	);

	$r = $arg['request'];

	if( !$arg['format'] )
		$err[] = 'no_format';

	if( !$arg['settings'] ) 
		$err[] = 'no_format';

	if( !$r['request'] ) 
		$err[] = 'no request';

	if( !array_key_exists($r['request'], $arg['format']) ) 
		$err[] = 'module "'.$r['request'].'" not found';


	if( !$err ):

		try{
			$db = new PDO ( 'mysql:host=' . $arg['settings']['db']['host'] . ';dbname=' . $arg['settings']['db']['name'], $arg['settings']['db']['user'], $arg['settings']['db']['password'],
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
				)
			);
		}
		catch( PDOException $e ){
			exit('Failed connection to database');
		}

		array_walk_recursive($r, 'jas_addslashes');

		// Default values
		$r['action'] = ( isset($r['action']) ) ? $r['action'] : 'select';
		$r['view'] = ( isset($r['view']) ) ? $r['view'] : 'list';

		// Simple record
		$mod = $arg['format'][$r['request']];
								

		/* TODO - rewrite block, write check_format*/		
		if( !in_array($r['action'], $do_not_check_action) )
			if( $format_requare[$r['action']] ){
				
				foreach( $format_requare[$r['action']] as $key => $value ) 
					if( !$mod[$r['action']][$value] )
						$err[] = $value.' not found in module';

			}else{
				$err[] = '$mod['.$r['action'].'] not found'; 
			}


		if( !$mod['from'] )
			$err[] = 'from not found'; 

		// check require  fields
		if( $mod[$r['action']]['require'] )
			foreach ( $mod[$r['action']]['require'] as $key => $value )
				if( !$r[$value] )
					$err[] = 'require param '.$value;
		
		
		if( !$err ):

			if( !in_array($r['action'], $do_not_check_action) )
				foreach ( $default_value[$r['action']] as $key => $value )
					$mod[$r['action']][$key] = ( isset($mod[$r['action']][$key]) and $mod[$r['action']][$key] != '' ) ? $mod[$r['action']][$key] : $value ;


			$mod_access_in_param = jas_set_access_param( $mod['access_in_param'], $r, $prefix = 'r->' );





			$result_action = jas_action($r['action'],array(
				'mod' => $mod, 
				'r' => $r, 
				'limit' => $limit, 
				'extra' => $block_extra,
				'mod_access_in_param' => $mod_access_in_param,
				'page' => $r['page'],
				'perpage' => $perpage,
				'format' => $arg['format']
			));


			if( $result_action['code'] == 'success' and $r['action'] == 'select' )
				$result_action = jas_view($r['view'],array(
					'mod' => $mod, 
					'r' => $r, 
					'mod_access_in_param' => $mod_access_in_param,
					'result_action' => $result_action,
					'format' => $arg['format']
				));


		endif; 

	endif; 

	if( $err ){
		$result_action = array('code' => 'fail', 'errors' => $err);
	}


	jas_set_mem('end');


	if( $storage->get()['out'] )
		$result_action = $result_action+$storage->get()['out'];

	if( $arg['settings']['debug'] )
		$storage->push(array('debug' => array('mem_usage' => array('peack_usemem' => ((memory_get_peak_usage()/1024)/1024). " mb" ))));

	if( $storage->get()['debug'] and $arg['settings']['debug'])
		$result_action['debug'] = $storage->get()['debug'];



	// The end!
	return $result_action;
}