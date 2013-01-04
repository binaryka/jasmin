<?php
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


// Главная функция для sql запросов
// $sql - сам sql запрос
// $execute - массив для подстановки данных
// допустим имеем sql запрос
// 		select * from table where id=:myid
// то массив будет таким
// 		array(':myid' => 108)
// Результат 
//		select * from table where id=108
function db_query($sql, $execute = array(),$typequery = 'select'){ 
	global $db,$sqlerr,$allsql,$sqlcache;

	if(isset($sql))
	$sqlmd5 = md5($sql);


	$code = 'fail';
	$error = 'dberror';

	// Сделаем так чтобы одинаковые запросы не совершались 
	// в рамках одного запроса к серверу
	if($sqlcache[$sqlmd5] and $typequery == 'select'){
		$result = $sqlcache[$sqlmd5];
		$code = 'success';
	}else{

		// Проверяем входные значения
		if(!is_array($execute))
			$bad_param[] = 'execute not array';

		if(!is_string($sql))
			$bad_param[] = 'sql not string';

		if(!$bad_param){
			// Через этот метод не пройдет ни одна sql инъекция
			$sql = $db->prepare($sql);

			// Подстановка входных параметр в sql запрос, 
			// тут тоже не пройдет никакая sql инъекция
			$sql->execute($execute);

			// Так написал, потому что так $sql->errorInfo()[0][0] не дадут обратиться, 
			// как обратиться нормально?
			$errsql = $sql->errorInfo();

			// код 0000 значит что ошибки нет при запросе
			if($errsql[0][0] == '0000'){
				$result = $sql->fetchAll(PDO::FETCH_ASSOC);



				if($typequery == 'select'){

					if($result)
						$code = 'success';
					else
						$error = 'no_result';
				}else if(in_array($typequery, array('delete','insert','update'))){
					$code = 'success';

					if($typequery == 'insert')
						$result = $db->lastInsertId();
					

				}
				

			}else{
				$error = 'dberror';
				$outsql['error'] = $errsql;
			}

			$outsql['sql'] = $sql->queryString;

			// Собираем все sql запросы в хранилище для отладки
			$allsql[] = $outsql;
	
		}else{
			$error = 'badparam';
			$errors = $bad_param;
		}
		
	}

	$sqlcache[$sqlmd5] = $result;

	$out = array('code' => $code);

	if($code == 'fail'){
		$out['error'] = $error;

		if($errors)
			$out['errors'] = $errors;
	}elseif($result){
		$out['result'] = $result;
	}



	return $out;
}


function parse_format($key_parse,$source){
	foreach ($key_parse as $key){
		$el = $source[$key];

		if(is_array($el))
			$el = parse_format(array_keys($el),$el);
		else if(is_string($el))
			$el = array_map('trim', explode(',', $el));
		
		$source[$key] = $el;
	}

	return $source;
}


// Здесь все наши действия//
$jas_actions = array(
	'select' => function($arg){
		global $out;


		// Формируем поиск

		$fields = $arg['mod']['select']['filtering']['fields'];


		// Это если есть фильтрация
		if($arg['r']['filtering'] == 'filter' or $arg['r']['filtering'] == 'search'){

			if($arg['r']['filtering'] == 'filter')
				$to_fields = $arg['mod']['select']['filtering']['filter']['fields'];
			else
				$to_fields = $arg['mod']['select']['filtering']['search'];

			foreach ($to_fields as $field) {
				if(isset($fields[$field])){


					$field_to_key = $arg['mod']['fields'][$field];

					if($field_to_key['key'])
						$key = $field_to_key['key'];
					else
						$key = $field;


					if($field_to_key['table'])
						$key = $field_to_key['table'].".".$key;


					if($arg['r']['filtering'] == 'filter')
						$value = $arg['r'][$field];
					else
						$value = $arg['r']['search'];

					if(!isset($arg['r'][$field]) and $arg['r']['filtering'] == 'filter')
						unset($key);

					if($key)
						$filter_sql[] =  strtr($fields[$field]['sql'], array_merge($arg['mod_acces_in_param'],array(':this->key' => $key, ':this->value' => $value))) ;
				}
			}

			if($filter_sql){

				if($arg['r']['filtering'] == 'search')
					$compare = 'or';
				else
					$compare = 'and';

				$filter_sql = " and (".implode(' '.$compare.' ', $filter_sql).") ";

				$filter_sql = strtr($filter_sql,$arg['mod_acces_in_param']);
			}
		}


		// Подставим доступные переменные для подстановки в части sql запросов
		if($arg['mod']['select']['where'] != '1')
			$arg['mod']['select']['where'] = strtr($arg['mod']['select']['where'],$arg['mod_acces_in_param']);

		if(isset($arg['mod']['select']['join']))
			$arg['mod']['select']['join'] = strtr($arg['mod']['select']['join'],$arg['mod_acces_in_param']);

		// Формируем fields

		$modfields = $arg['mod']['fields'];

		foreach($arg['mod']['select']['fields'] as $field){
			if($modfields[$field]['table'])
				$table = $modfields[$field]['table'].'.';

			if($modfields[$field]['key']){
				$select_fields[] = $table.$modfields[$field]['key'].' as '.$field;
			}else{
				$select_fields[] = $table.$field;
			}
		}

		if(!$arg['mod']['select']['where'])
			$arg['mod']['select']['where'] = 1;


		if($arg['mod']['select']['orderby'])
			$orderby = "ORDER BY ".$arg['mod']['select']['orderby'];

		$sql = "select " . implode(',',$select_fields) . " from ". $arg['mod']['from'] . " " . $arg['mod']['select']['join']  . " where " . $arg['mod']['select']['where']. $filter_sql ." " . $orderby . " limit ".$arg['limit'];

		$sql_for_count = "select count(*) from ". $arg['mod']['from'] . " " . $arg['mod']['select']['join']  . " where " . $arg['mod']['select']['where']. $filter_sql ;		

		$out['records'] = db_query($sql_for_count);
		$out['records'] = $out['records']['result'][0]['count(*)'];

		$result = db_query($sql);


		if($result['code'] == 'success'){

			foreach ($result['result'] as $keyrow => $fieldrow)
					foreach ($fieldrow as $key => $value){
						/*Convert*/
						if($arg['mod']['select']['convert_fields'][$key])
							if(is_callable($arg['mod']['select']['convert_fields'][$key]))
								$convert_value = $arg['mod']['select']['convert_fields'][$key]($value,$arg['r'],$fieldrow);
						
						if($convert_value) $value = $convert_value;
						unset($convert_value);

						/*Convert*/
						$result['result'][$keyrow][$key] = array('field' => $key, 'val' => $value);
					}

			$result['page'] = $arg['page']+1;
			$result['perpage'] = $arg['perpage'];

			if($arg['extra'] and $arg['r']['extra'])
				$result['extra'] = $arg['extra'];

			if($out['records']) $result['records'] = $out['records'];
		}



		return $result;
	},
	'insert' => function($arg){

		global $actions;
		$fields = $arg['mod']['insert']['fields'];




		if(is_array($fields)){

			foreach ($fields as $field) {

				/* Default*/
				if($arg['mod']['insert']['default_fields']){

					if(is_callable($arg['mod']['insert']['default_fields'][$field]))
						$default_value = $arg['mod']['insert']['default_fields'][$field]($arg['r'][$field],$arg['r']);
					
					if($default_value) $arg['r'][$field] = $default_value;
				}
				/* Default */

				// Проверяем только если поле действительно хотят обновить
				$out_fields[$field]['errors'] = check_field(
					array(
						'field' => $field, 
						'value' => $arg['r'][$field],
						'checks' => array('simple_check' => $arg['mod']['insert']['checks']['simple_check'], 'sql_check' => $arg['mod']['insert']['checks']['sql_check'])
					)
				);
			//	print_r($out_fields[$field]['errors']);

				if(is_array($out_fields[$field]['errors']))
					$show_error = true;


			}
		}
		//print_r($show_error);

		if($show_error){
			foreach ($out_fields as $field => $v)
				if(!is_array($v['errors']))
					unset($out_fields[$field]);

			return array('code' => 'fail', 'rows' => $out_fields, 'error' => 'error_check');
		}elseif(is_array($out_fields)){
			foreach ($out_fields as $field => $v){

				if($arg['mod']['fields'][$field]['table'])
					$table = $arg['mod']['fields'][$field]['table'].".";

				$set[] = '`'.$table.$field.'`="'.$arg['r'][$field].'"';
			}

			if($arg['mod']['insert']['from'])
				$table = $arg['mod']['insert']['from'];
			else
				$table = $arg['mod']['from'];


			$sql = 'insert into '.$table.' set '.implode(',', $set);

			$sql = strtr($sql,$arg['mod_acces_in_param']);

			$result = db_query($sql,array(),'insert');

			return array('code' => 'success', 'result' => $result);

		}else{
			return array('code' => 'fail','error' => 'nothing_update');
		}

		return $out_fields;

	},
	'update' => function($arg){
		global $jas_actions;
		$fields = $arg['mod']['update']['fields'];


		if(is_array($fields)){


			// Вызываем экшн селект
			$old_values = $jas_actions['select'](array(
				'mod' => $arg['mod'], 
				'r' => $arg['r'], 
				'limit' => '0,1',
				'mod_acces_in_param' => $arg['mod_acces_in_param'],
				'page' => 1,
				'perpage' => 1 
			));

			$old_values = $old_values['result'][0];


			foreach ($fields as $field) {

				// Проверяем только если поле действительно хотят обновить
				if(array_key_exists($field, $arg['r'])){
					$out_fields[$field]['errors'] = check_field(
						array(
							'field' => $field, 
							'value' => $arg['r'][$field],
							'old_values' => $old_values,
							'old_value' => $old_values[$field],
							'checks' => array('simple_check' => $arg['mod']['update']['checks']['simple_check'], 'sql_check' => $arg['mod']['update']['checks']['sql_check'])
						)
					);
			//	print_r($out_fields[$field]['errors']);

					if(is_array($out_fields[$field]['errors']))
						$show_error = true;

				}


			}
		}
		//print_r($show_error);

		if($show_error){
			foreach ($out_fields as $field => $v)
				if(!is_array($v['errors']))
					unset($out_fields[$field]);

			return array('code' => 'fail', 'rows' => $out_fields, 'error' => 'error_check');
		}elseif(is_array($out_fields)){
			foreach ($out_fields as $field => $v){

				if($arg['mod']['fields'][$field]['table'])
					$table = $arg['mod']['fields'][$field]['table'].".";

				$set[] = '`'.$table.$field.'`="'.$arg['r'][$field].'"';
			}

			if($arg['mod']['update']['from'])
				$from = $arg['mod']['update']['from'];
			else
				$from = $arg['mod']['from'];

			$where = $arg['mod']['update']['where'];

			$sql = 'update '.$from.' set '.implode(',', $set).' where '.$where;

			$sql = strtr($sql,$arg['mod_acces_in_param']);

			db_query($sql,array(),'insert');

			return array('code' => 'success');

		}else{
			return array('code' => 'fail','error' => 'nothing_update');
		}

		return $out_fields;

	},
	'multiupdate' => function($arg){
		global $jas_actions;
		$max = ($arg['mod']['multiupdate']['maxrows'])? $arg['mod']['multiupdate']['maxrows'] :100;

		$inarg = $arg;

		unset($inarg['data']);

		$result = array('code' => 'success');

		if(count($arg['r']['data']) <= $max){
			foreach ($arg['r']['data'] as $value) {
				$inarg['r'] = $value;

				$mod_acces_in_param = array();
				if($arg['mod']['acces_in_param']){
					foreach ($arg['mod']['acces_in_param'] as $value)
						if($inarg['r'][$value])
							$mod_acces_in_param[':r_'.$value] = $inarg['r'][$value];
				}

				$inarg['mod_acces_in_param'] = $mod_acces_in_param;

				$itemresult = $jas_actions['update']($inarg);

				if($itemresult['code'] == 'fail'){
					$result['code'] = 'fail';
				}

				$rows[] = $itemresult;

			}

			$result['rows'] = $rows;
			
		}else{
			$result = array('code' => 'fail', 'error' => 'exceeded_number_records');
		}


		return $result;

	},
	'delete' => function($arg){

		$sql = 'DELETE FROM '.$arg['mod']['from'].' WHERE '.$arg['mod']['delete']['where'];

		$sql = strtr($sql,$arg['mod_acces_in_param']);

		$result = db_query($sql,array(),'delete');

		return $result;
	},
	'multidelete' => function($arg){
		global $jas_actions;
		$max = ($arg['mod']['multidelete']['maxrows'])? $arg['mod']['multidelete']['maxrows'] :100;

		$inarg = $arg;

		unset($inarg['data']);

		$result = array('code' => 'success');

		//print_r($arg['r']);

		if($arg['r']['data'] and count($arg['r']['data']) <= $max){
			foreach ($arg['r']['data'] as $value) {
				$inarg['r'] = $value;

				$mod_acces_in_param = array();
				if($arg['mod']['acces_in_param']){
					foreach ($arg['mod']['acces_in_param'] as $value)
						if($inarg['r'][$value])
							$mod_acces_in_param[':r_'.$value] = $inarg['r'][$value];
				}

				$inarg['mod_acces_in_param'] = $mod_acces_in_param;

				$itemresult = $jas_actions['delete']($inarg);

				if($itemresult['code'] == 'fail'){
					$result['code'] = 'fail';
				}

				$rows[] = $itemresult;

			}

			$result['rows'] = $rows;
			
		}else{
			$result = array('code' => 'fail', 'error' => 'exceeded_number_records');
		}


		return $result;
	},
	'filtering' => function($arg){
		return array('code' => 'success', 'extra' => $block_extra);
	}
);


$jas_result_views = array(
	'list' => function($arg){
		global $subquery_val;

		// проходим по всему результату
		// По умолчанию не выводятся всяие дополнительные данные,
		// не всегда надо, пожтому показываем только по ключу
		if($arg['r']['extra']):

			foreach ($arg['result_action']['result'] as $keyrow => $fieldrow):

				// Нам в подзапросе понадобится подставлять данные из общего запроса
				// для этого подготовим массив
				foreach ($fieldrow as $key => $value)
					$subquery_val[':q_'.$value['field']] = $value['val'];

				// Устанавливаем параметры для каждого отдельного поля
				foreach ($fieldrow as $key => $value){
						$extra = get_more_info($arg+$value+array('subquery_val' => $subquery_val));

						$rows[$keyrow][$value['field']]= $extra+$value;

				}

			endforeach;	

			$arg['result_action']['result'] = $rows;

		endif;

					

		if(isset($row_options))
			$arg['result_action']['row_options'] = $row_options;

		return $arg['result_action'];
	}
);

/*==== Checks ====*/



function is_domain($email){
         $p = '/^([-a-z0-9]+\.)+([a-z]{2,3}';
         $p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
         return preg_match($p, $email);         
}


function is_email($email){
         $p = '/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
         $p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
         $p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
         return preg_match($p, $email);         
}


function is_date($date){
         $p = '/^([0-9]{2})[\.-]([0-9]{2})[\.-]([0-9]{2,4}';
         $p.= '$/ix';
         return preg_match($p, $date);  
}


// Допускается использование * в адресе
function is_email_mask($email){
	 if( $email == '*' ) return 1;
         $p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
         $p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
         $p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
         return preg_match($p, $email);         
}

// Допускается использование формата @domain.ru 
function is_address($email){
         $p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]*(\.[a-z0-9!#$%&*+-=?^_`{|}~]*)*';
         $p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
         $p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
         return preg_match($p, $email);         
}

function is_phone($phone){
         $p= '/^\+{0,1}\d{1,2}\(\d{2,6}\)[\d-]{3,8}$/ix';
         return preg_match($p, $phone);
}


function is_number($value){
         $p = '/^[0-9]+$/ix';
         return preg_match($p, $value);
}


function is_ip($ip){
        $p = '/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/ix';
        return preg_match($p, $ip);
}

function is_host($host){
        return is_domain($host) or  is_ip($host);
}

// Определяет, является ли строка sql запросом
function is_sql($str){
	$str = mb_strtolower($str);

	$finder = array('select','insert','update','delete');
	$ret = false;

	foreach ($finder as $value) {
		if(preg_match('#^'.$value.' .*$#',$str)){

			// если это select или delete, то полюбому должен быть from
			if($value == 'select' or $value == 'delete'){
				if(preg_match('#^.* from .*$#',$str))
					$ret = true;
			// У insert'a обязателен into
			}elseif ($value == 'insert') {
				if(preg_match('#^.* into .*$#',$str))
					$ret = true;
			// У updat'a обязателен set
			}elseif ($value == 'update') {
				if(preg_match('#^.* set .*$#',$str))
					$ret = true;
			}
		}
	}

	return $ret;
}




//  функция проверки значения по sql запросу
function check_val_in_table($arg){
	global $mod_acces_in_param;

	if( isset($arg['sql']) )
		return db_query(strtr($arg['sql'],$mod_acces_in_param));
	else
		return false;		    
}


/*
	Массив анонимных функций проверки значений полей

	Как использовать

	В файле ../application/functions.php


	$_format['check_function_simple']['ключ_новой_проверки'] = function($arg){

		if(Ваша проверка)
			// error - обязателен, это ключ ошибки!
			return array('error'=>'already_exist' ,'val'=> $arg['value']);
		else
			// если все хорошо
			return true;
			
	};
	
*/

$jas_checks['simple'] = array(

	'required' => function($arg){
		

		if(!$arg['value'])
			return array('error' => 'require');
		else
			return true;
	},
	'date' => function($arg){
		if($arg['value']!=''  && is_date($arg['value'])==0 )
		   	return array('error'=>'baddatel','val'=>$arg['value']);
		else
			return true;

	},
	'host' => function($arg){
		if( $arg['value']!='' && !is_host($arg['value']) )
			return array('error' => 'badhost','val' => "bad value '".$arg['value']."'");
		else
			return true;
	},
	'email' => function($arg){
		if($arg['value']!=''  && is_email($arg['value'])==0 )
		   	return array('error'=>'bademail','val'=>$arg['value']);
		else
			return true;

	},
	'email_mask' => function($arg){
		if($arg['value']!=''  && is_email_mask($arg['value'])==0 )
		   	return array('error'=>'bademail','val'=>$arg['value']);
		else
			return true;

	},
	'phone' => function($arg){
		if($arg['value']!=''  && !is_phone($arg['value']) )
		   	return array('error'=>'badphone','val'=>$arg['value']);
		else
			return true;

	},
	'domain' => function($arg){
		if($arg['value']!=''  && is_domain($arg['value'])==0 ) 
			return array('error'=>'baddomain','val'=>"bad value '".$arg['value']."'");
		else
			return true;
	},
	'address' => function($arg){
		if($arg['value']!=''  && is_address($arg['value'])==0 ) 
			return array('error'=>'badadres','val'=> "bad value '".$arg['value']."'");
		else
			return true;
	},
	'port' => function($arg){
		if($arg['value']!='' && (!is_digits($arg['value']) || $arg['value']<=0 || $arg['value']>=65535) )
			return array('error'=>'badport','val'=>"bad value '".$arg['value']."'");
		else
			return true;
	}
);


/*
	Массив анонимных функций, которые проверяют значения полей на основе sql запроса


	$_format['check_function_sql']['ключ_новой_проверки'] = function($arg){
		$arg['sql'] = содержит sql запрос

		можно воспользоваться функцией
		$lines = check_val_in_table($arg); 
		она выполнит sql запрос, подставит все данные из $arg['values'] в запрос

		if(Ваша проверка)
			// error - обязателен, это ключ ошибки!
			return array('error'=>'already_exist' ,'val'=> $arg['value']);
		else
			// если все хорошо
			return $lines;
	};
	
*/

$jas_checks['sql'] = array(
	'exist' => function($arg){

		$result = check_val_in_table($arg);

		if($result['code'] == 'fail')
			return array('error' => 'notexist','val' => $arg['value']);
		else 
			return true;
		
	},
	'unique' => function($arg){

		$result = check_val_in_table($arg);

		if($result['code']  != 'fail')
			return array('error' => 'notunique', 'val' => $arg['value']);
		else
			return true;
	}
);


// Проверка на значений полей

/*
	Как использовать

	В функцию нужно передать обязательные поля
	$arg['checks'] - содержит в себе списки проверок, 

	$arg['field'] - содержит в себе ключ поля, он нужен потом в функциях проверки
	$arg['value'] - значение, которое будет проверяться
*/
function check_field($arg){
	global $jas_checks;

	//print_r($arg);

	// Делаем простые проверки
	if($arg['checks']['simple_check'])
		foreach ($arg['checks']['simple_check'] as $check_name => $fields) {


			// Если объявлено поле для этой проверки, то выполняем проверку
			if(in_array($arg['field'], $fields) and is_callable($jas_checks['simple'][$check_name])){
				$check = $jas_checks['simple'][$check_name]($arg);

				if($check['error'])
					$errors[] = $check_name;

				//if($errors[$check_name] == 1) unset($errors[$check_name]);
			}
	}
	// Делаем sql проверки
	if($arg['checks']['sql_check'])
		foreach ($arg['checks']['sql_check'] as $check_name => $fields){
			if(array_key_exists($arg['field'], $fields) and is_callable($jas_checks['sql'][$check_name])){
				$arg['sql'] = $fields[$arg['field']];

				$check = $jas_checks['sql'][$check_name]($arg);

				if($check['error'])
					$errors[] = $check_name;

				//if($errors[$check_name] == 1) unset($errors[$check_name]);
			}
		}

	if($errors)
		return $errors;
	else
		return true;
}



/*==== Checks ====*/








// Основная функция, которая задает каждому полю из результата - нужную информацию
// $field - имя поля из результата
// $value - значение поля из результата
function get_more_info($arg){

	//global $mod,$_format,$mod_acces_in_param,$sql_ret,$subquery_val,$row_options,$out;
	global $out,$_format;

	//print_r($_format);
	// Устанавливаем типы
		$ret['type'] = set_value_by_key($arg['mod']['type_fields'],$arg['field'],'text');
	
	// Устанавливаем информацию о возможности редактирования
		$ret['edit'] = (in_array($arg['field'],(array)$arg['mod']['update']['fields'])) ? 'yes' : 'no';

	// Устанавливаем параметр name. Это читаемая часть поля 
		$ret['name'] = (isset($arg['mod']['fields'][$arg['field']]['name'])) ? $arg['mod']['fields'][$arg['field']]['name'] : $_format['global']['names'][$arg['field']];

	// Устанавливаем стили
		// В начале надо получить какие дополнительные данные используются для этого поля
		// Все работает как в простом css
		if(is_array($arg['mod'][$arg['r']['action']]['fields_extra']))
			foreach ($arg['mod'][$arg['r']['action']]['fields_extra'] as $field_extra => $value_extra){

				if(in_array($arg['field'], $value_extra) and isset($_format['global']['extra'][$field_extra]) )
					$extra = $_format['global']['extra'][$field_extra]+(array)$extra;
			}

		// Теперь применим все стили
		if($extra)
			$ret['extra'] = $extra;
	// закончили со стилями

	// Обрабатываем типы select
		if(array_key_exists($arg['field'],(array)$arg['mod']['select']['select_fields'])){

			if(!in_array($ret['type'],array('select','multiselect','radio')))
				$ret['type'] = 'select';

			// TODO сделать функцию которая будет проверять sql ли запрос
			// А пока просто на строку проверяем
			if(is_string($arg['mod']['select']['select_fields'][$arg['field']]))
				$options = db_query(strtr($arg['mod']['select']['select_fields'][$arg['field']],$arg['subquery_val']));

			// Не будем ограничиваться только sql запросом, можно подставлять простые массивы 
			if(is_array($arg['mod']['select']['select_fields'][$arg['field']]))
				$options = $arg['mod']['select']['select_fields'][$arg['field']];

			// Получим md5 результата options 
			// нужно чтобы при 20 записях и при разных результатах options не было дублей, 
			// и было все на месте, так же это экономия трафика 
			$md5_options = md5(json_encode($options));

			// Не ставим в $row_options если такой результат уже получали
			if(!isset($row_options[$arg['field']."-".$md5_options])){
				$out['options'][$arg['field']."-".$md5_options] = $options;
			}

			// Оставляем адрес для получения нужных options
			$ret['options'] = $arg['field']."-".$md5_options;
		}
	// Закончили с типом select
		
	return $ret;
}





// Полезная функция, она нужна только для функции get_more_info
// Устанавливает значение из вот такого формата
/*
*	'type_fields' => array(
*    	'hidden' => 'name',
*    	'password' => 'MobilePass,WebPass',
*    	'textarea' => 'MobileInfo',
*	),
*/
// Где 
// $set_param - массив выше;
// $key - ключ который может являться значением любого элемента массива
// $defaultval - значение по умолчанию, если ничего не найдет
//
// P.S. Может показаться что тут будет ошибка, т.к. значения в массиве строки, а не массивы.
// Но на момент применения они становятся массивами, это делается еще при загрузке
function set_value_by_key($set_param,$key,$defaultval){
	$set = $defaultval;

	if(isset($set_param))
		foreach ($set_param as $keyset => $value)
			if(in_array($key, $value))
				$set = $keyset;

	return $set;
}

// Функция упрощает вывод json
function exit_json($array){
	exit(json_encode((object)$array));
}

// Фиксирует кол-во используемой памяти в том месте, где оставил функцию
// Результат оставленных вызовов функций можно посмотреть при запросе к апи с ключом usemem=1
// $place - это строка, которая поясняет где вызывается функция
// $line - не обязательно, но желательно передавать номер строки где это все вызывается, будет потом проще
function set_mem($place, $line = false){
	global $mem_usage;

	if($line)
		$line = '- строка '.$line;

	$current_mem = memory_get_usage();
	$mem_usage[] = $place. ' - ' .(($current_mem/1024)/1024)." mb ".$line;
}


