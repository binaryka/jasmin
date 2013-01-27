<?php
/*
	Jasmin - multitasking system.
	Version - 1.2.0
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

class Jas_storage{
	static private $_instance = null;

	public static function & Instance()
	{
		if (is_null(self::$_instance))
		{
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private $storage = array();

	private function __construct(){}

	public function __destruct(){}

	public function __clone(){
		trigger_error('Cloning instances of this class is forbidden.', E_USER_ERROR);
	}

	public function __wakeup(){
		trigger_error('Unserializing instances of this class is forbidden.', E_USER_ERROR);
	}

	public function push( $array, $path = false ){
		$this->storage = array_merge_recursive($this->storage, $array);
	}

	public function get(){
		return $this->storage;
	}
}



function jas_db_query( $sql, $execute = array(':' => ''), $typequery = 'select' ){ 
	global $db, $storage;

	if( isset($sql))
	$sqlmd5 = md5($sql);


	$code = 'fail';
	$error = 'dberror';

	// Сделаем так чтобы одинаковые запросы не совершались 
	// в рамках одного запроса к серверу
	if( $storage->get()['sqlcache'][$sqlmd5] and $typequery == 'select' ){
		$result = $storage['sqlcache'][$sqlmd5];
		$code = 'success';
	}else{

		// Проверяем входные значения
		if( !is_array($execute) )
			$bad_param[] = 'execute not array';

		if( !is_string($sql) )
			$bad_param[] = 'sql not string';


		if( !$bad_param ){
			// Через этот метод не пройдет ни одна sql инъекция
			$sql = $db->prepare($sql);

			// Подстановка входных параметр в sql запрос, 
			// тут тоже не пройдет никакая sql инъекция

			$sql->execute($execute);

			// Так написал, потому что так $sql->errorInfo()[0][0] не дадут обратиться, 
			// как обратиться нормально?
			$errsql = $sql->errorInfo();

			// код 0000 значит что ошибки нет при запросе
			if( $errsql[0][0] == '0000' ){
				$result = $sql->fetchAll(PDO::FETCH_ASSOC);



				if( $typequery == 'select' ){

					if( $result)
						$code = 'success';
					else
						$error = 'no_result';

				}else if( in_array( $typequery, array('delete','insert','update') ) ){
					$code = 'success';

					if( $typequery == 'insert' )
						$result = $db->lastInsertId();
				}

			}else{
				$error = 'dberror';
				$outsql['error'] = $errsql;
			}

			$outsql['sql'] = $sql->queryString;

			// Собираем все sql запросы в хранилище для отладки

			$storage->push(array('debug' => array('allsql' => $outsql) ) );
	
		}else{
			$error = 'badparam';
			$errors = $bad_param;
		}
		
	}

	$storage->push(array('sqlcache' => array($sqlmd5 => $result) ) );

	$out = array('code' => $code);

	if( $code == 'fail' ){
		$out['error'] = $error;

		if( $errors )
			$out['errors'] = $errors;
	}elseif( $result ){
		$out['result'] = $result;
	}



	return $out;
}




// Здесь все наши действия//
$jas_actions = array(
	'select' => function( $arg ){
		global $jas_actions;

		// paging

		$maxperpage = ( isset($arg['format']['global']['maxperpage']) ) ? $arg['format']['global']['maxperpage'] : 100 ; 
		
		$perpage = ( isset($arg['format']['global']['perpage']) ) ? $arg['format']['global']['perpage'] : 10 ; 

		if( isset($arg['mod']['select']['maxperpage']) ) 
			$maxperpage = $arg['mod']['select']['maxperpage'];

		if( isset($arg['mod']['select']['perpage']) ) 
			$perpage = $arg['mod']['select']['perpage'];

		if( isset($arg['r']['perpage']) and $arg['r']['perpage'] <= $maxperpage )
			$perpage = $arg['r']['perpage'];


		$arg['r']['page'] = ( isset($arg['r']['page']) ) ? $arg['r']['page']-1 : 0 ; 
		$limit_from = ( $arg['r']['page'] == 0 ) ? 0 : $arg['r']['page']*$perpage;
		$limit = $limit_from . "," . $perpage;

		// end paging



		// Filtering

		$fields = $arg['mod']['select']['filtering']['fields'];

		if( $arg['r']['filtering'] == 'filter' or $arg['r']['filtering'] == 'search' ){

			if( $arg['r']['filtering'] == 'filter' )
				$to_fields = $arg['mod']['select']['filtering']['filter']['fields'];
			else
				$to_fields = $arg['mod']['select']['filtering']['search'];

			if($to_fields)
			foreach ( $to_fields as $field ) {
				if( isset($fields[$field]) ){

					$field_to_key = $arg['mod']['fields'][$field];

					if( $field_to_key['key'] )
						$key = $field_to_key['key'];
					else
						$key = $field;


					if( $field_to_key['table'] )
						$key = $field_to_key['table'].".".$key;


					if( $arg['r']['filtering'] == 'filter')
						$value = $arg['r'][$field];
					else
						$value = $arg['r']['search'];

					if( !isset($arg['r'][$field]) and $arg['r']['filtering'] == 'filter' )
						unset($key);

					if( $key )
						$filter_sql[] =  strtr( $fields[$field]['sql'], array_merge( $arg['mod_access_in_param'], array(':this->key' => $key, ':this->value' => $value) ) ) ;
				}
			}

			if( $filter_sql ){

				if( $arg['r']['filtering'] == 'search' )
					$compare = 'or';
				else
					$compare = 'and';

				$filter_sql = " and (" . implode(' ' . $compare . ' ', $filter_sql) . ") ";

				$filter_sql = strtr( $filter_sql,$arg['mod_access_in_param'] );
			}
		}


		// Подставим доступные переменные для подстановки в части sql запросов
		if( $arg['mod']['select']['where'] != '1' )
			$arg['mod']['select']['where'] = strtr( $arg['mod']['select']['where'], $arg['mod_access_in_param'] );

		if( isset($arg['mod']['select']['join']) )
			$arg['mod']['select']['join'] = strtr( $arg['mod']['select']['join'], $arg['mod_access_in_param'] );

		// Формируем fields

		$modfields = $arg['mod']['fields'];

		foreach ( $arg['mod']['select']['fields'] as $field ){
			if( $modfields[$field]['table'] )
				$table = $modfields[$field]['table'] . '.';

			if( $modfields[$field]['key'] ){
				$select_fields[] = $table . $modfields[$field]['key'] . ' as ' . $field;
			}else{
				$select_fields[] = $table . $field;
			}
		}

		if( !$arg['mod']['select']['where'] )
			$arg['mod']['select']['where'] = 1;


		if( $arg['mod']['select']['orderby'])
			$orderby = "ORDER BY " . $arg['mod']['select']['orderby'];

		$sql = "select " . implode( ',', $select_fields ) . " from " . $arg['mod']['from'] . " " . $arg['mod']['select']['join']  . " where " . $arg['mod']['select']['where'] . $filter_sql . " " . $orderby . " limit " . $limit;

		$sql_for_count = "select count(*) from ". $arg['mod']['from'] . " " . $arg['mod']['select']['join']  . " where " . $arg['mod']['select']['where']. $filter_sql ;		

		$records = jas_db_query( $sql_for_count );
		$records = $records['result'][0]['count(*)'];

		$result = jas_db_query($sql);


		if( $result['code'] == 'success' ){

			foreach ( $result['result'] as $keyrow => $fieldrow )
					foreach ( $fieldrow as $key => $value ){
						/*Convert*/
						$conv_fields = $arg['mod']['select']['convert_fields'];

						if( $conv_fields[$key] )
							if( is_callable( $conv_fields[$key] ) )
								$convert_value = $conv_fields[$key]( $value, $arg['r'], $fieldrow );
						
						if( $convert_value ) 
							$value = $convert_value;
						
						unset( $convert_value );

						/*Convert*/

						$result['result'][$keyrow][$key] = array('field' => $key, 'val' => $value);
					}

			$result['page'] = $arg['page']+1;
			$result['perpage'] = $arg['perpage'];

			if( $arg['r']['filtering'] ){
				$filters = jas_action('filtering',$arg);

				if( $filters['code'] == 'success' )
					$result['filters'] = $filters;
			}


			if( $records ) 
				$result['records'] = $records;
		}

		return $result;
	},
	'insert' => function($arg ){

		$fields = $arg['mod']['insert']['fields'];


		if( is_array($fields) ){

			foreach ( $fields as $field ) {

				/* Default*/
				$def_fields = $arg['mod']['insert']['default_fields'];
				if( $def_fields ){

					if( is_callable( $def_fields[$field] ) )
						$default_value = $def_fields[$field]( $arg['r'][$field], $arg['r'] );
					
					if( $default_value ) 
						$arg['r'][$field] = $default_value;
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

				if( is_array($out_fields[$field]['errors']) )
					$show_error = true;


			}
		}
		//print_r($show_error);

		if( $show_error ){
			foreach ( $out_fields as $field => $v )
				if( !is_array( $v['errors'] ) )
					unset( $out_fields[$field] );

			return array('code' => 'fail', 'rows' => $out_fields, 'error' => 'error_check');
		}elseif( is_array( $out_fields ) ){
			foreach ( $out_fields as $field => $v ){

				if( $arg['mod']['fields'][$field]['table'] )
					$table = $arg['mod']['fields'][$field]['table'] . ".";

				$set[] = '`' . $table . $field . '`="' . $arg['r'][$field] . '"';
			}

			if( $arg['mod']['insert']['from'] )
				$table = $arg['mod']['insert']['from'];
			else
				$table = $arg['mod']['from'];


			$sql = 'insert into ' . $table . ' set ' . implode(',', $set);

			$sql = strtr( $sql, $arg['mod_access_in_param'] );

			$result = jas_db_query( $sql, array(), 'insert' );

			return array('code' => 'success', 'result' => $result );

		}else{
			return array('code' => 'fail','error' => 'nothing_update');
		}

		return $out_fields;

	},
	'update' => function($arg ){

		$fields = $arg['mod']['update']['fields'];


		if( is_array($fields) ){


			// Вызываем экшн селект
			$old_values = jas_action('select',array(
				'mod' => $arg['mod'], 
				'r' => $arg['r'], 
				'limit' => '0,1',
				'mod_access_in_param' => $arg['mod_access_in_param'],
				'page' => 1,
				'perpage' => 1 
			));

			$old_values = $old_values['result'][0];


			foreach ( $fields as $field) {


				if( array_key_exists($field, $arg['r']) ){
					$out_fields[$field]['errors'] = check_field(
						array(
							'field' => $field, 
							'value' => $arg['r'][$field],
							'old_values' => $old_values,
							'old_value' => $old_values[$field],
							'checks' => array('simple_check' => $arg['mod']['update']['checks']['simple_check'], 'sql_check' => $arg['mod']['update']['checks']['sql_check'])
						)
					);

					if( is_array($out_fields[$field]['errors']))
						$show_error = true;

				}


			}
		}


		if( $show_error ){
			foreach ( $out_fields as $field => $v)
				if( !is_array($v['errors']))
					unset($out_fields[$field]);

			return array('code' => 'fail', 'rows' => $out_fields, 'error' => 'error_check');
		}elseif( is_array($out_fields) ){
			foreach ( $out_fields as $field => $v ){

				if( $arg['mod']['fields'][$field]['table'])
					$table = $arg['mod']['fields'][$field]['table'].".";

				$set[] = '`'.$table.$field.'`="'.$arg['r'][$field].'"';
			}

			if( $arg['mod']['update']['from'])
				$from = $arg['mod']['update']['from'];
			else
				$from = $arg['mod']['from'];

			$where = $arg['mod']['update']['where'];

			$sql = 'update '.$from.' set '.implode(',', $set).' where '.$where;

			$sql = strtr($sql,$arg['mod_access_in_param']);

			jas_db_query($sql,array(),'insert');

			return array('code' => 'success');

		}else{
			return array('code' => 'fail','error' => 'nothing_update');
		}

		return $out_fields;

	},
	'multiupdate' => function($arg ){

		$max = ($arg['mod']['multiupdate']['maxrows'])? $arg['mod']['multiupdate']['maxrows'] :100;

		$inarg = $arg;

		unset($inarg['data']);

		$result = array('code' => 'success');

		if( count($arg['r']['data']) <= $max ){
			foreach ( $arg['r']['data'] as $value) {
				$inarg['r'] = $value;

				$mod_access_in_param = array();
				if( $arg['mod']['acces_in_param'] ){
					foreach ( $arg['mod']['acces_in_param'] as $value)
						if( $inarg['r'][$value])
							$mod_access_in_param[':r_'.$value] = $inarg['r'][$value];
				}

				$inarg['mod_access_in_param'] = $mod_access_in_param;

				$itemresult = jas_action('update',$inarg);

				if( $itemresult['code'] == 'fail' ){
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
	'delete' => function($arg ){

		$sql = 'DELETE FROM '.$arg['mod']['from'].' WHERE '.$arg['mod']['delete']['where'];

		$sql = strtr($sql,$arg['mod_access_in_param']);

		$result = jas_db_query($sql,array(),'delete');

		return $result;
	},
	'multidelete' => function($arg ){

		$max = ($arg['mod']['multidelete']['maxrows'])? $arg['mod']['multidelete']['maxrows'] :100;

		$inarg = $arg;

		unset($inarg['data']);

		$result = array('code' => 'success');

		//print_r($arg['r']);

		if( $arg['r']['data'] and count($arg['r']['data']) <= $max ){
			foreach ( $arg['r']['data'] as $value) {
				$inarg['r'] = $value;

				$mod_access_in_param = array();
				if( $arg['mod']['acces_in_param'] ){
					foreach ( $arg['mod']['acces_in_param'] as $value)
						if( $inarg['r'][$value])
							$mod_access_in_param[':r_'.$value] = $inarg['r'][$value];
				}

				$inarg['mod_access_in_param'] = $mod_access_in_param;

				$itemresult = jas_action('delete',$inarg);

				if( $itemresult['code'] == 'fail' ){
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
	'filtering' => function( $arg ){

		if( is_array($arg['mod']['select']['filtering']['filter']) ){

			$filtering = $arg['mod']['select']['filtering'];

			$value_filter = $arg['mod']['select']['filtering']['filter'];
				
			foreach ( $value_filter['fields'] as $field ) {

				
				$out_field['field'] = $field;

				$out_field['type'] = ( isset($filtering['fields'][$field]['type']) ) ? $filtering['fields'][$field]['type'] : 'text'; // дефолтный

				$out_field['name'] = ( isset($arg['format']['global']['names'][$field]) ) ? $arg['format']['global']['names'][$field] : null;


				
				if( $out_field['type'] == 'select' ){

					$filtering['fields'][$field]['options'];

					if( is_array($filtering['fields'][$field]['options']) )
						$options = $filtering['fields'][$field]['options'];

					else if( jas_is_sql( $filtering['fields'][$field]['options']) )
						$options = jas_db_query(strtr($filtering['fields'][$field]['options'],$arg['access_in_param']));
					
					$out_field['options'] = $options;
				}


				if( is_array($value_filter['fields_extra']) )
					foreach ( $value_filter['fields_extra'] as $key_extra => $value_extra )
						if( in_array($field, $value_extra) and isset($arg['format']['global']['extra'][$key_extra]) )
							$extra = $arg['format']['global']['extra'][$key_extra]+(array)$extra;
							
				if( $extra )
					$out_field['extra'] = $extra;

				unset($extra);

				if( $out_field )
					$fields[$field] = $out_field;

				unset($out_field);
			}


		}

		if( isset($fields) )
			return array('code' => 'success', 'result' => $fields);
		else
			return array('code' => 'fail', 'code' => 'nothing_to_display');
	},
	'frame' => function($arg){
		$insert_fields = $arg['mod']['insert']['fields'];

		$fields = $arg['mod']['frame']['fields'];

		if ( is_array($insert_fields) and is_array($fields) ) {
			foreach ($fields as $field) {
				if ( in_array($field, $insert_fields) ) {
					$out_fields[$field] = jas_get_more_info($arg+array('field' => $field,'subquery_val' => array()));
				}
			}
		}

		if ( $out_fields ) {
			return array('code' => 'success', 'result' => $out_fields);
		} else {
			return array('code' => 'fail', 'code' => 'nothing_to_display_frame');
		}
	}
);


function jas_action($action_name,$arg){
	global $jas_actions;

	if( is_callable($jas_actions[$action_name]) and is_array($arg) ){
		
		jas_set_mem('before action - '.$action_name);

		$result =  $jas_actions[$action_name]($arg);

		jas_set_mem('after action - '.$action_name);

		return $result;
	}else
		return array('code' => 'fail', 'error' => 'this_action_notfound');
	
}


$jas_result_views = array(
	'list' => function( $arg ){

		if( $arg['r']['extra']):

			foreach ( $arg['result_action']['result'] as $keyrow => $fieldrow):


				foreach ( $fieldrow as $key => $value)
					$subquery_val[':q_'.$value['field']] = $value['val'];


				foreach ( $fieldrow as $key => $value ){
					$extra = jas_get_more_info( $arg+$value+array('subquery_val' => $subquery_val));
					$rows[$keyrow][$value['field']]= $extra+$value;
				}

			endforeach;	

			$arg['result_action']['result'] = $rows;

		endif;

		if( isset($row_options) )
			$arg['result_action']['row_options'] = $row_options;

		return $arg['result_action'];
	}
);

function jas_view($view,$arg){
	global $jas_result_views;

	if( is_callable($jas_result_views[$view]) and is_array($arg) ){
		
		jas_set_mem('before view - '.$view);

		$result =  $jas_result_views[$view]($arg);

		jas_set_mem('after view - '.$view);

		return $result;
	}else
		return array('code' => 'fail', 'error' => 'this_view_notfound');
	
}

/*==== Checks ====*/



function jas_is_domain( $email ){
	$p = '/^([-a-z0-9]+\.)+([a-z]{2,3}';
	$p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';

	return preg_match($p, $email);         
}


function jas_is_email( $email ){
	$p = '/^[a-z0-9!#$%&*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
	$p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
	$p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';

	return preg_match($p, $email);         
}

// 12.04.1985 or 12-04-1985
function jas_is_date( $date ){
	$p = '/^([0-9]{2})[\.-]([0-9]{2})[\.-]([0-9]{2,4}';
	$p.= '$/ix';

	return preg_match($p, $date);  
}


// *@mail.com
function jas_is_email_mask( $email ){
	if(  $email == '*' ) 
		return true;

	$p = '/^[a-z0-9!#$%&*\*+-=?^_`{|}~]+(\.[a-z0-9!#$%&*+-=?^_`{|}~]+)*';
	$p.= '@([-a-z0-9]+\.)+([a-z]{2,3}';
	$p.= '|info|arpa|aero|coop|name|museum|mobi)$/ix';
	
	return preg_match($p, $email);         
}


function jas_is_phone( $phone ){
	$p = '/^\+{0,1}\d{1,2}\(\d{2,6}\)[\d-]{3,8}$/ix';
	return preg_match($p, $phone);
}


function jas_is_number( $value ){
	$p = '/^[0-9]+$/ix';
	return preg_match($p, $value);
}


function jas_is_ip( $ip ){
	$p = '/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/ix';
	return preg_match($p, $ip);
}

function jas_is_host( $host ){
	return jas_is_domain($host) or jas_is_ip( $host);
}

// Определяет, является ли строка sql запросом
function jas_is_sql( $str ){
	$str = mb_strtolower($str);

	$finder = array('select','insert','update','delete');
	$ret = false;

	foreach ( $finder as $value ) {
		if( preg_match('#^'.$value.' .*$#',$str) ){

			// если это select или delete, то полюбому должен быть from
			if( $value == 'select' or $value == 'delete' ){
				if( preg_match('#^.* from .*$#',$str))
					$ret = true;
			// У insert'a обязателен into
			}elseif ($value == 'insert' ){
				if( preg_match('#^.* into .*$#',$str))
					$ret = true;
			// У updat'a обязателен set
			}elseif ($value == 'update' ){
				if( preg_match('#^.* set .*$#',$str))
					$ret = true;
			}
		}
	}

	return $ret;
}




//  функция проверки значения по sql запросу
function jas_check_val_in_table($arg ){
	global $mod_access_in_param;

	if(  isset($arg['sql']) )
		return jas_db_query(strtr($arg['sql'],$mod_access_in_param));
	else
		return false;		    
}


$jas_checks['simple'] = array(

	'required' => function($arg ){
		

		if( !$arg['value'])
			return array('error' => 'require');
		else
			return true;
	},
	'date' => function($arg ){
		if( $arg['value']!=''  && jas_is_date($arg['value'])==0 )
		   	return array('error'=>'baddatel','val'=>$arg['value']);
		else
			return true;

	},
	'host' => function($arg ){
		if(  $arg['value']!='' && !jas_is_host( $arg['value']) )
			return array('error' => 'badhost','val' => "bad value '".$arg['value']."'");
		else
			return true;
	},
	'email' => function($arg ){
		if( $arg['value']!=''  && jas_is_email($arg['value'])==0 )
		   	return array('error'=>'bademail','val'=>$arg['value']);
		else
			return true;

	},
	'email_mask' => function($arg ){
		if( $arg['value']!=''  && jas_is_email_mask($arg['value'])==0 )
		   	return array('error'=>'bademail','val'=>$arg['value']);
		else
			return true;

	},
	'phone' => function($arg ){
		if( $arg['value']!=''  && !jas_is_phone($arg['value']) )
		   	return array('error'=>'badphone','val'=>$arg['value']);
		else
			return true;

	},
	'domain' => function($arg ){
		if( $arg['value']!=''  && jas_is_domain($arg['value'])==0 ) 
			return array('error'=>'baddomain','val'=>"bad value '".$arg['value']."'");
		else
			return true;
	},
	'port' => function($arg ){
		if( $arg['value']!='' && (!is_digits($arg['value']) || $arg['value']<=0 || $arg['value']>=65535) )
			return array('error'=>'badport','val'=>"bad value '".$arg['value']."'");
		else
			return true;
	}
);



$jas_checks['sql'] = array(
	'exist' => function($arg ){

		$result = jas_check_val_in_table($arg);

		if( $result['code'] == 'fail')
			return array('error' => 'notexist','val' => $arg['value']);
		else 
			return true;
		
	},
	'unique' => function($arg ){

		$result = jas_check_val_in_table($arg);

		if( $result['code']  != 'fail')
			return array('error' => 'notunique', 'val' => $arg['value']);
		else
			return true;
	}
);



function jas_check_field( $arg ){
	global $jas_checks;

	// Делаем простые проверки
	if( $arg['checks']['simple_check'])
		foreach ( $arg['checks']['simple_check'] as $check_name => $fields) {


			// Если объявлено поле для этой проверки, то выполняем проверку
			if( in_array($arg['field'], $fields) and is_callable($jas_checks['simple'][$check_name]) ){
				$check = $jas_checks['simple'][$check_name]($arg);

				if( $check['error'])
					$errors[] = $check_name;

				//if( $errors[$check_name] == 1) unset($errors[$check_name]);
			}
	}
	
	if( $arg['checks']['sql_check'])
		foreach ( $arg['checks']['sql_check'] as $check_name => $fields ){
			if( array_key_exists($arg['field'], $fields) and is_callable($jas_checks['sql'][$check_name]) ){
				$arg['sql'] = $fields[$arg['field']];

				$check = $jas_checks['sql'][$check_name]($arg);

				if( $check['error'])
					$errors[] = $check_name;

				//if( $errors[$check_name] == 1) unset($errors[$check_name]);
			}
		}

	if( $errors)
		return $errors;
	else
		return true;
}

/*==== end Checks ====*/


function jas_get_more_info( $arg ){

	global $storage;

	$ret['type'] = jas_set_value_by_key($arg['mod']['type_fields'],$arg['field'],'text');

	$ret['edit'] = (in_array($arg['field'],(array)$arg['mod']['update']['fields'])) ? 'yes' : 'no';

	$ret['name'] = (isset($arg['mod']['fields'][$arg['field']]['name'])) ? $arg['mod']['fields'][$arg['field']]['name'] : $arg['format']['global']['names'][$arg['field']];


	// Extra
	if( is_array($arg['mod'][$arg['r']['action']]['fields_extra']))
		foreach ( $arg['mod'][$arg['r']['action']]['fields_extra'] as $field_extra => $value_extra ){

			if( in_array($arg['field'], $value_extra) and isset($arg['format']['global']['extra'][$field_extra]) )
				$extra = $arg['format']['global']['extra'][$field_extra]+(array)$extra;
		}

	if( $extra)
		$ret['extra'] = $extra;


	if( array_key_exists($arg['field'],$arg['mod']['select']['select_fields']) )
		$ret = jas_create_options($arg,$ret);
	
	return $ret;
}

function jas_create_options($arg,$ret){
	global $storage;

	if( !in_array($ret['type'],array('select','multiselect','radio')))
		$ret['type'] = 'select';

	if( is_string($arg['mod']['select']['select_fields'][$arg['field']]))
		$options = jas_db_query(strtr($arg['mod']['select']['select_fields'][$arg['field']],$arg['subquery_val']));


	if( is_array($arg['mod']['select']['select_fields'][$arg['field']]))
		$options = $arg['mod']['select']['select_fields'][$arg['field']];


	$md5_options = md5(json_encode($options));

	if( !isset($row_options[$arg['field']."-".$md5_options]) ){
		$storage->push(array('out' => array('options' => array($arg['field']."-".$md5_options => $options) ) ) );
	}

	$ret['options'] = $arg['field']."-".$md5_options;

	return $ret;
}

function jas_set_access_param( $access_param, $params, $prefix = 'r->' ){

	$out_access_param = array();

	if( is_array($access_param) )
		foreach ( $access_param as $param )
			if( $params[$param] )
				$out_access_param['{' . $prefix . $param . '}'] = $params[$param];

	return $out_access_param;
}


function jas_set_value_by_key($set_param,$key,$defaultval ){
	$set = $defaultval;

	if( isset($set_param))
		foreach ( $set_param as $keyset => $value)
			if( in_array($key, $value))
				$set = $keyset;

	return $set;
}


function jas_set_mem( $place, $line = false ){
	global $storage;

	if( $line )
		$line = '- string ' . $line;

	$current_mem = memory_get_usage();
	$storage->push(array('debug' => array('mem_usage' => array( $place => (($current_mem/1024)/1024) . " mb " . $line) ) ) );
}


function jas_addslashes( &$value ){
	$value = addslashes($value);
}