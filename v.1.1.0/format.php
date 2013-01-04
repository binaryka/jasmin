<?php
$_format['global'] = array(
    'maxperpage' => 1000,
    'perpage' => 1000
);



$_format['list'] = array(
    'from' => 'list',
    'access' => array(
        'select' => array('authorized'),
    ),
    'select' => array(
        'fields' => array('id','send_status','name','email','company','project','sex','unical_id'),
        'perpage' => '30',
        'fields_extra' => array(
            'tableone' => array('id','name','sex'),
            'col1' => array('id','lang'),
            'actions' => array('unical_id')
        ),
        'select_fields' => array(
            'sex' => array(
                array('name' => 'male', 'value' => 'Мужчина'),
                array('name' => 'female', 'value' => 'Женщина'),
            ),
            'project' => array(
                array('name' => 'project1', 'value' => 'Project1'),
                array('name' => 'project2', 'value' => 'Project2'),
                array('name' => 'project3', 'value' => 'Project3'),
            ),
        ),
        'convert_fields' => array(
            'unical_id' => function($val,$r,$vals){
                //print_r($vals);
                $newval = URLSITE."?unical_id=".$vals['id'];
                return $newval;
            },
            'send_status' => function($val){
                if($val == 'Y')
                    $newval = 'Sending';
                else
                    $newval = 'Wait';

                return $newval;
            }
        ),
        'filtering' => array(
            'fields' => array(
                'project' => array('sql' => ':this->key=":this->value"'),
            ),
            'filter' => array(
                'fields' => array('project'),
            )
        ),
        "orderby" => 'id DESC'
    ),
    'type_fields' => array(
        'radio' => array('sex'),
        'hidden' => array('id'),
        'link' => array('unical_id'),
        'simpletext' => array('send_status')
    ),
    'acces_in_param' => array('id','name','sex','company','email'),
);



$_format['item_list'] = array(
    'from' => 'list',
    'access' => array(
        'select' => array('all'),
        'insert' => array('authorized'),
        'update' => array('authorized'),
        'multiupdate' => array('authorized'),
        'delete' => array('authorized'),
        'multidelete' => array('authorized'),

    ),
    'select' => array(
        'fields' => array('id','name','sex','company','email','project','unical_id'),
        'perpage' => '30',
        'where' => 'id=:r_id',
        'require' => array('id')
    ),
    'insert' => array(
        'fields' => array('name','sex','company','email','project','unical_id'),
        'checks' => array(
            'simple_check' => array(
                'required' => array('name','sex','lang','project','email'),
                'email' => array('email')
            )
        ),
        'default_fields' => array(
            'unical_id' => function($val,$val_fields){
                $newval = md5(json_encode($val_fields) .time());
                return $newval;
            }
        ),
    ),
    'update' => array(
        'fields' => array('name','sex','company','project','email','send_status'),
        'checks' => array(
            'simple_check' => array(
                'required' => array('name','sex','lang','project','email'),
                'email' => array('email')
            )
        ),
        'where' => 'id=:r_id',
        'require' => array('id')
    ),
    'multiupdate' => array(
        'maxrows' => 500
    ),
    'delete' => array(
        'where' => 'id=:r_id',
        'require' => array('id')
    ),
    'multidelete' => array(
        'maxrows' => 500
    ),
    'acces_in_param' => array('id')
);


$_format['list_for_send'] = array(
    'from' => 'list',
    'access' => array(
        'select' => array('authorized'),
    ),
    'select' => array(
        'fields' => array('id','name','sex','company','email','project','unical_id'),
        'perpage' => '100',
        'where' => 'id in(:r_ids)',
        'require' => array('ids')
    ),

    'acces_in_param' => array('ids')
);




$_format['global']['names'] = array(
    "id" => "Record",
    "name" => "Name",
    "sex" => "Sex",
    "company" => "Company",
    "project" => "Project",
    "email" => "Email",
    "unical_id" => "Link"
);


$_format['global']['extra'] = array(
    'tableone' => array('table' => 'tableone'),
    'col1' => array('width' => '150px'),
    'actions' => array(
        'actions' => array(
            array('name' => 'Delete', 'action' => 'del'),
            array('name' => 'Send', 'action' => 'send')
        )
    ),
);