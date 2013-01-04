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
define( 'ABSPATH', dirname(__FILE__) . '/' );
//echo ABSPATH;
error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );

// Подключаем функции
if(file_exists(ABSPATH . 'functions.php'))
	include ABSPATH . "functions.php";
else
	die('functions.php not found');


function Jasmin($arg){

	global $jas_actions, $db, $jas_result_views,$allsql,$out,$mem_usage;

	$default_value = array(
		'select' => array(
			'where' => '1',
			'limit' => '10'
		),
		'update' => array(),
		'multiupdate' => array(),
		'multidelete' => array(),
		'insert' => array(),
		'delete' => array()
	);

	$format_requare = array(
		'select' => array('fields'),
		'update' => array('fields','where'),
		'insert' => array('fields'),
		'delete' => array('where'),
		'multiupdate' => array('maxrows'),
		'multidelete' => array('maxrows')
	);

	set_mem('after format_requare for parse_types');
	// Стартуем сессию
	session_start();

	$_format = $arg['format'];
	$r = $arg['request'];




	$settings = $arg['settings'];



	if(!$_format)
		$err[] = 'no_format';

	if(!$settings) $err[] = 'no_format';

	// Проверяем входящие переменные 

	if(!$r['request']) $err[] = 'no request';

	if(!array_key_exists($r['request'], $_format)) $err[] = 'module "'.$r['request'].'" not found';



	//  ну раз все хорошо, пошли отрабатывать запрос
	if(!$err):
			// от sql инъекций
/*			foreach ($r as $key => $value)
				if(is_string($value))
				$r[$key] = mysql_real_escape_string($value);*/
	
			//print_r($r);

			//Подключаемся к базе
			try {
				$db = new PDO ( 'mysql:host=' . $settings['db']['host'] . ';dbname=' . $settings['db']['name'], $settings['db']['user'], $settings['db']['password'],
					// опции
					array(
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
					)
				);
				//$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$sqlerr = array();
				$allsql = array();
			}
			catch(PDOException $e){
				//print_r($e);
				exit('Failed connection to database');
			}
			set_mem('after db connect');



		// Проверка переменных, установка дефолтных значений //

			// Действие по умолчанию
			$r['action'] = (isset($r['action'])) ? $r['action'] : 'select';
			$r['view'] = (isset($r['view'])) ? $r['view'] : 'list';

			// $mod - массив в котором содержатся настройки запроса или модуля
			$mod = $_format[$r['request']];

			//print_r($mod);

			// Распарсим конфиг для фильтров
			//if(is_array($mod[$r['action']]['filtering']['filter']))
			//		$mod[$r['action']]['filtering']['filter'] = parse_format(array('fields', 'fields_extra'),$mod[$r['action']]['filtering']['filter']);
			
			set_mem('after parse mod');


			// Разбивка на страницы

				// максимальное допустимое кол-во на странице
					$maxperpage = (isset($f['global']['maxperpage'])) ? $f['global']['maxperpage'] : 100 ; 
					if(isset($mod['select']['maxperpage'])) $maxperpage = $mod['select']['maxperpage'];

				// дефолтное кол-во на странице
					$perpage = (isset($f['global']['perpage'])) ? $f['global']['perpage'] : 10 ; 

					// дефолтное в модуле
					if(isset($mod['select']['perpage'])) $perpage = $mod['select']['perpage'];

					// подставляем если есть входящая переменная
					if(isset($r['perpage']) and $r['perpage'] <= $maxperpage) $perpage = $r['perpage'];

				// формируем limit

					// номер страницы, сразу делаем минус один, в таблице бд все с нуля
					$r['page'] = (isset($r['page'])) ? $r['page']-1 : 0 ; 
					$limit_from = ($r['page'] == 0) ? 0 : $r['page']*$perpage;
					$limit = $limit_from . "," . $perpage;

			// закончили разбивку на страницы
						

			// Проверяем обязательные поля в запросе
			// Это значит что допустим у запроса select должны быть обызательно поля и from
			// $format_requare - находится в load.php
			
			if($format_requare[$r['action']]){
				foreach ($format_requare[$r['action']] as $key => $value) 
					if(!$mod[$r['action']][$value])
						$err[] = $value.' not found in module';
			}else{
				$err[] = '$mod['.$r['action'].'] not found'; // на всякий случай
			}

			//print_r($mod);

			if(!$mod['from'])
				$err[] = 'from not found'; // на всякий случай

			// Проверяем обязательные входящие переменные
			if($mod[$r['action']]['require']){

				foreach ($mod[$r['action']]['require'] as $key => $value){
					
					if(!$r[$value])
						$err[] = 'require param '.$value;
				}
			}




		// закончили проверять переменные // 

		// Подготавливаем данные для создания запрососв // 
		if(!$err):

			// Теперь обработаем дефолтные значение, если вдруг ничего в настройках не будет, 
			// опять же, только если выше не найдено ошибок, $default_value в load.php
			foreach ($default_value[$r['action']] as $key => $value)
				$mod[$r['action']][$key] = (isset($mod[$r['action']][$key]) and $mod[$r['action']][$key] != '') ? $mod[$r['action']][$key] : $value ;


			// Значит нам надо подготовить массив значений, доступный 
			// для подстановки из входящих переменных через функцию strtr
			// ключ к подстановки - двоеточие, т.е. если через гет приходит переменная byid,
			// то в конфиге запроса пишем :byid
				$mod_acces_in_param = array();
				if($mod['acces_in_param']){
					foreach ($mod['acces_in_param'] as $value)
						if($r[$value])
							$mod_acces_in_param[':r_'.$value] = $r[$value];
				}

			// закончили с подстановкой входящих значений в части запроса



			set_mem('before filtering');
			//print_r($mod); die;


			// Блоки с фильтрами, их может быть сколько угодно
			if(is_array($mod[$r['action']]['filtering']['filter'])){
				$filtering = $mod[$r['action']]['filtering'];


				$value_filter = $mod[$r['action']]['filtering']['filter'];
					
					foreach ($value_filter['fields'] as $field) {

						// Устанавливаем стандартные значения
						$out_field['field'] = $field;

						$out_field['type'] = (isset($filtering['fields'][$field]['type'])) ? $filtering['fields'][$field]['type'] : 'text'; // дефолтный

						$out_field['name'] = (isset($_format['global']['names'][$field])) ? $_format['global']['names'][$field] : null;


						// отдельно обрабатываем селект
							if($out_field['type'] == 'select'){
								$filtering['fields'][$field]['options'];

								if(is_array($filtering['fields'][$field]['options']))
									$options = $filtering['fields'][$field]['options'];
								else if(is_sql($filtering['fields'][$field]['options'])){
									$options = db_query(strtr($filtering['fields'][$field]['options'],$mod_acces_in_param));
								}

								$out_field['options'] = $options;
							}

						// Устанавливаем дополнительные значения
							if(is_array($value_filter['fields_extra']))
								foreach ($value_filter['fields_extra'] as $key_extra => $value_extra)
									if(in_array($field, $value_extra) and isset($_format['global']['extra'][$key_extra]) )
										$extra = $_format['global']['extra'][$key_extra]+(array)$extra;
										
							if($extra)
								$out_field['extra'] = $extra;

							unset($extra);

						if($out_field)
							$fields[$field] = $out_field;

						unset($out_field);
					}

				if(isset($fields))
					$block_extra['filter'] = $fields;
			}
			set_mem('after filtering');

		endif;

		// Закончили подготовку //
		
		
		if(!$err):

			if(is_callable($jas_actions[$r['action']]))
				$result_action = $jas_actions[$r['action']](array(
					'mod' => $mod, 
					'r' => $r, 
					'limit' => $limit, 
					'extra' => $block_extra,
					'mod_acces_in_param' => $mod_acces_in_param,
					'page' => $r['page'],
					'perpage' => $perpage 
				));
			else
				$result_action = array('code' => 'fail', 'error' => 'this_action_notfound');

			// совершили запросы

			// если зпрос был совершен корректно и есть какие то результаты, то можно настроить ему нужный вид
			if($result_action['code'] == 'success' and $r['action'] == 'select'):

				if(is_callable($jas_result_views[$r['view']]))
					$result_action = $jas_result_views[$r['view']](array(
						'mod' => $mod, 
						'r' => $r, 
						'mod_acces_in_param' => $mod_acces_in_param,
						'result_action' => $result_action
					));
				else
					$result_action = array('code' => 'fail', 'error' => 'this_view_notfound');

			endif;

			//

		endif; // закончилась обработка запроса

	endif; // закончилась проверка обязательных входящих переменных

	if($err){
		$result_action = array('code' => 'fail', 'errors' => $err);
	}


	set_mem('after request');


	if($out)
		$result_action = $result_action+$out;

	// Активирем дебаг, вырубить на продакшене можно через константу DEBUG
		if($allsql[0] and $settings['debug'])
			$result_action['debug']['allsql'] = $allsql;

		if($r['usermem'] and $settings['debug']):
			$result_action['debug']['usemem'] = $mem_usage;
			$result_action['debug']['peack_usemem'] = ((memory_get_peak_usage()/1024)/1024). " mb";
		endif;

	// The end!
	return $result_action;
}