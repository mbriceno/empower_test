<?php if (! SYS) die;

class DATAGRID
{
	protected $_page = '';
	protected $_variant = '';
	protected $_noTable = false;
	protected $_table = null;
	protected $_where = '';
	protected $_orderBy = '';
	protected $_params = array();
	protected $_fields = array();
	protected $_filters = array();
	protected $_innerJoins = array();
	protected $_leftJoins = array();
	protected $_replyChanges = array();
	protected $_from = '';
	protected $_langTable = '';
	protected $_disableCUD = false;
	protected $_multiselect = false;
	protected $_db = null;
	protected $_js = '';
	protected $_css = '';
	protected $_auditFields = true;
	protected $_inactiveField = true;
	protected $_fixedFieldsVals = null;
	protected $_defaultFieldsVals = array();
	protected $_insertId = null;
	protected $_isEmployee = true;
	protected $_filterByComp = true;

	function __construct()
	{
		global $db, $lang, $company, $userid, $country;

		if ($this->_fixedFieldsVals === null)
			$this->_fixedFieldsVals = array('Company' => $company, 'User' => $userid);
		
		if (empty($this->_variant)) $this->_variant = $_REQUEST['variant'].'';
		
		$this->_page = substr($_REQUEST['pg'], 4);
		//if (empty($this->_page)) msgerror('No se ha indicado el código de página.');

		$lstpage = 'pg_lst_'.$this->_page;

		$query = '
		SELECT P.Page, Type PageType, Text Caption, P.`Table`, `Transaction`, FormWidth, FormHeight
		FROM SY_Pages P
		LEFT JOIN SY_PagesTexts T ON P.Page = T.Page AND P.Variant = T.Variant 
		WHERE P.Page = ? AND P.Variant = ? AND T.Language = ?
		';
		$params = array($lstpage, $this->_variant, $lang);
		$r = $db->getOne($query, $params);
		//debuglog('pages'.$lstpage,$query.print_r($page.' '.$lstpage.' '.$variant.' '.$lang,true));
		if (empty($r['Page']))
		{
			$params[2] = 'EN';
			$r = $db->getOne($query, $params);
		}
		$pagemsg = 'Página : '.$lstpage.(empty($this->_variant) ? '' : ' Variante : '.$this->_variant);
		if (empty($r['Page'])) msgerror($pagemsg.' no encontrada en SY_Pages');
		
		if ($this->_table === null)
		{
			if (empty($r['Table']))
			{
				if (! $this->_noTable) msgerror('Tabla No Definida Para '.$pagemsg);
				return false;
			}	
			$this->_table = $r['Table'];
		}
		
		//$pagetype    = $result['PageType'];
	
		$r2 = $db->getOne('SELECT Type FROM SY_Tables T WHERE T.`Table` = ?', array($this->_table));
	
		$select = $key = array();
		foreach($db->getTablePrimaryKey($this->_table) as $k)
		{
			if ($r2['Type'] != 'X' || ($k['Column_name'] != 'Country' && $k['Column_name'] != 'Company'))
			{
				$key[] = $k['Column_name'];
				$select[] = 'A.'.$k['Column_name'];
			}
			if ($k['Column_name'] == 'Company' && $this->_filterByComp)
			{
				$this->_where .= ' AND A.Company = ?';
				$this->_params[] = $company;
			}
		}
		if (! isset($key[0])) msgerror('Indice No Definido Para Tabla '.$this->_table);

		$appendurl = '';
		foreach(array('listid', 'q', 'nextfield') as $k)
			if (! empty($_REQUEST[$k]))
				$appendurl .= '&'.$k.'='.$_REQUEST[$k];
		
		$filters = array(
		'elid' => $_REQUEST['container'],
		'appendurl' => '&container='.$_REQUEST['container'].'&opcs=CUD'.$appendurl,
		'filters' => array()
		);

		switch($r2['Type'])
		{
		//FILTRO BASE (VACIO)
		case 'B':
			break;
		//TABLAS PAIS / COMPANIA POR DEFECTO AGREGA LOS BOTONES
		case 'X':
			$filters['filters'] = array(
			array(
			'on_lbl' => 'Normal',
			'off_lbl' => 'Scope Reporte',
			'type' => 'toogle'
			),
			array(
			'on_lbl' => text('Country'),
			'off_lbl' => text('Company'),
			'type' => 'toogle'
			)
			);
			break;
		//TABLAS DE EMPLEADO - FILTRO POR DEFECTO
		case 'E':
		{
			$this->_where .= getPermissions('Employee', 'AND');
			if ($_REQUEST['topfltrs'][0] != 1)
				$this->_where .= ' AND E.Inactive = '.($_REQUEST['topfltrs'][0] == 2 ? 0 : 1);
			if (! empty($_REQUEST['topfltrs'][1]) && $_REQUEST['topfltrs'][1] != 'undefined')
			{
				$this->_where .= ' AND E.Payroll = ?';
				$this->_params[] = $_REQUEST['topfltrs'][1];
			}
			if (! empty($_REQUEST['topfltrs'][2]))
			{
				$this->_where .= ' AND (E.Employee LIKE ? OR E.FirstName LIKE ? OR E.LastName LIKE ?)';
				$p = '%'.$_REQUEST['topfltrs'][2].'%';
				$this->_params = array_merge($this->_params, array($p, $p, $p));
			}
			$filters['filters'][] = array($this->_isEmployee ? 'HREMPLOYEE' : 'RECANDIDATE', array('fields' => array('E.'.($this->_isEmployee ? 'Employee' : 'Candidate'), 'E.FirstName', 'E.LastName')));
			break;
		}
		case 'D': //TABLAS DATOS DE COMPAÑIA - FILTRO POR DEFECTO
			//addFilter('SYCOMPANY');
			break;
		case 'C': //TABLAS CONF. DE COMPAÑIA - FILTRO POR DEFECTO
			//addFilter('SYCOMPANY');
			break;
		case 'Y': //PANTALLAS DE PAIS - FILTRO PAIS POR DEFECTO
           //$index = 0;
           //$filters['filters'][] = getSuggest('SYCOUNTRY');
		   //Nota no carga el filtro de pa�s porque siempre se est� trabajando en un solo pa�s
			break;
		case 'Z':
			break;
		default:
			msgerror('Tipo de Filtro No Definido : '.$r2['Type']);
		}
		
		$filters['filters'] = array_merge($filters['filters'], $this->_filters);

		$this->_macroFields($filters['filters']);
		
		$_filters = array();
		foreach($filters['filters'] as $k => $v)
			if (isset($v[0]))
			{
				$__filters = $this->_addField($v[0]);
				if (isset($v[1]))
					foreach($__filters as $k2 => $v2)
						$__filters[$k2] = array_merge($v2, $v[1]);
				$_filters = array_merge($_filters, $__filters);
			}
			else
				$_filters[] = $v;
		$filters['filters'] = $_filters;
		
		foreach($filters['filters'] as $k => $v)
			if ($v['type'] == 'droplist')
				$this->_js .= '
				$("#gf_'.$this->_page.'_'.$k.'").change(function()
				{
					$("#gf_'.$this->_page.'_set").trigger("click");
				});
				';
		
		if ($_REQUEST['action']) foreach($filters['filters'] as $k => $f) if ($f['fields'])
		{
			if ($f['fields_type'] == 'D')
			{
				$datebeg = (empty($_REQUEST['fltrs'][$k.'_from']) ? '1900-01-01' : CORE::getMySQLDate($_REQUEST['fltrs'][$k.'_from']));
				$dateend = (empty($_REQUEST['fltrs'][$k.'_to']) ? '9999-12-31' : CORE::getMySQLDate($_REQUEST['fltrs'][$k.'_to']));
				$this->_where .= ' AND ('.$f['fields'][0].' BETWEEN ? AND ?'.($f['fields'][1] ? ' OR '.$f['fields'][1].' BETWEEN ? AND ? OR ('.$f['fields'][0].' < ? AND '.$f['fields'][1].' > ?)' : '').')';
				$this->_params = array_merge($this->_params, array($datebeg, $dateend));
				if ($f['fields'][1])
					$this->_params = array_merge($this->_params, array($datebeg, $dateend, $datebeg, $dateend));
			}
			else if ($_REQUEST['fltrs'][$k])
			{
				if (! is_array($f['fields'])) $f['fields'] = array($f['fields']);
				switch($f['fields_type'])
				{
				case 'B': //CONDICION BOOLEANA
					$this->_where .= ' AND '.$f['fields'][0].' = '.($_REQUEST['fltrs'][$k] == 'X' ? 1 : 0);
					break;
				case 'E': //CONDICION IGUAL
				{
					$this->_where .= ' AND '.$f['fields'][0].' = ?';
					$this->_params[] = $_REQUEST['fltrs'][$k];
					break;
				}
				case 'O':
				{
					foreach($f['fields'] as $v => $v2)
						if ($_REQUEST['fltrs'][$k] == $v)
						{
							$this->_where .= ' AND '.$v2;
							break;
						}
					break;
				}
				default:
				{
					$_where = array();
					foreach($f['fields'] as $v)
					{
						$_where[] = $v.' LIKE ?';
						$this->_params[] = $_REQUEST['fltrs'][$k];
					}
					$this->_where .= ' AND ('.implode(' OR ', $_where).')';
				}
				}
			}
		}
		
		$select = array('CONCAT('.implode(', "|", ', $select).') id');
		$_fields = array();
		
		if ($this->_inactiveField) $this->_fields[] = array('INACTIVE');
		if ($this->_auditFields) $this->_fields = array_merge($this->_fields, array(
		array('SYUSER'),
		array('TIMESTAMP'),
		array('INFO', array('fieldQuery' => 'CONCAT(A.`User`, "|", UNIX_TIMESTAMP(A.TimeStamp))'))
		));
		$this->_macroFields($this->_fields);
		foreach($this->_fields as $k => $f)
		{
			if (isset($f[0]))
			{
				$fields = $this->_addField($f[0]);
				if (isset($f[1])) 
					foreach($fields as $k2 => $v2)
						$fields[$k2] = array_merge($v2, $f[1]);
			}
			else
				$fields = array($f);
			foreach($fields as $f)
				$select[] = (empty($f['fieldQuery']) ? (empty($f['noinrow']) ? 'A.`'.$f['field'].'`' : '"" as `'.$f['field'].'`') : $f['fieldQuery'].' `'.$f['field'].'`');
			$_fields = array_merge($_fields, $fields);
		}
		$this->_fields = $_fields;
		
		if ($this->_langTable)
		{
			$f = array();
			$p = array($lang);
			if (! is_array($this->_langTable)) $this->_langTable = array($this->_langTable);
			foreach($this->_langTable as $k3 => $t)
			{
				$k2 = 'T'.($k3 > 0 ? $k3 : '');
				foreach($db->getTablePrimaryKey($t) as $k)
					if ($k['Column_name'] != 'Language')
					{
						if ($k['Column_name'] == 'Country')
						{
							$f[] = $k2.'.`Country` = ?';
							$p[] = $country;
						}
						else
							$f[] = 'A.`'.$k['Column_name'].'` = '.$k2.'.`'.$k['Column_name'].'`';
					}
				$this->_leftJoins[] = array($t.' '.$k2, $k2.'.`Language` = ? AND '.implode(' AND ', $f), $p);	
			}
		}

		$params = array();
		foreach(array($this->_leftJoins, $this->_innerJoins) as $k => $v)
			foreach($v as $t)
			{
				if (! is_array($t)) $t = array($t);
				switch($t[0])
				{
				case 'EMPLOYEE':
					$t = array(EMPLOYEE::TBL.' E', 'A.Company = E.Company AND A.Employee = E.Employee');
					break;
				}
				$this->_from .= ($k == 1 ? ',' : ' LEFT JOIN').' '.$t[0].($k == 1 ? '' : ' ON '.$t[1]);
				if ($k == 1) $this->_where .= ' AND '.$t[1];
				if (isset($t[2]))
				{
					foreach($t[2] as $k2 => $v2) switch($v2)
					{
					case 'LANG': $t[2][$k2] = $lang; break;
					}
					$params = array_merge($params, $t[2]);
				}
			}
		$params = array_merge($params, $this->_params);
	
		if ($this->_disableCUD) unset($_REQUEST['opcs']);
			
		if (substr($this->_where, 0, 5) == ' AND ') $this->_where = substr($this->_where, 5);

		if ($_REQUEST['action'] == 'edit')
		{
			foreach($this->_defaultFieldsVals as $k => $v)
				if (empty($_REQUEST[$k]))
					$_REQUEST[$k] = $v;
			$this->_beforeAction();
			switch($_REQUEST['oper'])
			{
			case 'add': $this->_beforeAdd(); break;
			case 'edit': $this->_beforeEdit(); break;
			}
		}
		
		if ($this->_db !== null)
		{
			$dbBack = $db;
			$db = $this->_db;
		}

		if ($this->_multiselect) $_REQUEST['multiselect'] = '1';
	
		processGrid(
		$r['Caption'],
		$this->_page,
		$this->_fields,
		'id',
		'SELECT '.implode(', ', $select).' FROM '.$this->_table.' A '.$this->_from.($this->_where ? ' WHERE '.$this->_where : '').($this->_orderBy ? ' ORDER BY '.$this->_orderBy : ''),
		$params,
		$appendurl,
		'',
		$key,
		$this->_table,
		$filters,
		true,
		array(),
		false,
		true,
		$r['FormHeight'],
		$r['FormWidth'],
		$this->_fixedFieldsVals,
		'',
		empty($this->_js) && empty($this->_css),
		false,
		array(),
		'',
		'',
		'',
		'',
		$this->_replyChanges,
		false,
		false,
		false,
		'',
		array($this, 'format'),
		array($this, 'confirmSave'),
		array($this, 'confirmDel')
		);
				
		$this->_insertId = $db->insert_id;
		
		if ($this->_db !== null) $db = $dbBack;
		
		if ($_REQUEST['action'] == 'edit')
		{
			$this->_afterAction();
			switch($_REQUEST['oper'])
			{
			case 'add': $this->_afterAdd(); break;
			case 'edit': $this->_afterEdit(); break;
			}
		}
		
		if ($_REQUEST['action']) die;
		
		if (! empty($this->_js))
			echo '<script type="text/javascript">'.$this->_js.'</script>';
		if (! empty($this->_css))
			echo '<style type="text/css">'.$this->_css.'</style>';
	}
	
	private function _macroFields(&$fields)
	{
		$_fields = array();
		foreach($fields as $f)
			if (isset($f[0]))
				switch($f[0])
				{
				case 'EMPLOYEEFILTERS':
					$_fields = array_merge($_fields, array(
					array('HRGROUP', array('secondary' => true)),
					array('HRSUBGROUP', array('secondary' => true)),
					array('HRAREA', array('secondary' => true)),
					array('HRSUBAREA', array('secondary' => true)),
					array('HRDEPARTMENT', array('secondary' => true)),
					array('HRPOSITION', array('secondary' => true)),
					array('TMSCHEDULE', array('secondary' => true))
					));
					break;
				default:
					$_fields[] = $f;
				}
			else
				$_fields[] = $f;
		$fields = $_fields;
	}
	
	private function _addField($field)
	{
		global $db, $langs, $val_langs, $val_gender, $val_bloodType;

		require 'vars.php';
		
		$fields = (is_array($field) ? $field : array($field));
		$_fields = array();
  
		foreach($fields as $k => $field) switch($field) 
		{
		case 'DOWNLOAD':	
			$_fields[] = array(
			'type' => 'button',
			'subtype' => 'download',
			'align' => 'center',
			'size' => '10',
			);
			break;
		case 'BUTTON':	
			$_fields[] = array(
			'type' => 'button',
			'align' => 'center',
			'size' => '10',
			);
			break;
		case 'TITLE':
			$_fields[] = array(
			'lbl' => text('Title'),
			'field' => 'Title',
			'size' => '60',
			'editable' => true
			);
			break;
		case 'TEXTAREA':
			$_fields[] = array(
			'type' => 'textarea',
			'cols' => 40,
			'hideingrid' => true,
			'editable' => true
			);
			break;
		case 'HTMLEDITOR':
			$_fields[] = array(
			'size' => '50',
			'editable' => true,
			'hideingrid' => true,
			'type' => 'htmleditor',
			'rows' => '55',
			'cols' => '200',
			);
			break;
		case 'SECUENCY':
			$_fields[] = array(
			'lbl' => text('Secuency'),
			'field' => 'Secuency',
			'type' => 'text',
			'size' => '20',
			'align' => 'center',
			'hideingrid' => true,
			'editable' => true
			);
			break;
		case 'EMPTYPE':
			$_fields[] = array(
			'lbl' => text('Type'),
			'align' => 'center',
			'type' => 'droplist',
			'values' => array(array('val' => 'C', 'lbl' => text('External')), array('val' => 'E', 'lbl' => text('Internal'))),
			'noshowall' => true
			);
			break;
		case 'STATUS':
			$_fields[] = array(
			'lbl' => text('Status'),
			'size' => '20',
			'editable' => false,
			'field' => 'Status',
			'align' => 'center'
			);
			break;
		case 'POSTED':
			$_fields[] = array(
			'lbl' => text('Posted'),
			'size' => '30',
			'editable' => false,
			'field' => 'Posted',
			'align' => 'center',
			);
			break;
		case 'ATTACHMENT':
			$_fields[] = array(
			'lbl' => text('Attachment'),
			'field' => 'Attachment',
			'size' => '30',
			'align' => 'center'
			);
			break;
		case 'EMAIL':
			$_fields[] = array(
			'lbl' => text('Email'),
			'field' => 'Email',
			'size' => '30',
			'maxlength' => 250,
			'editable' => true,
			'hideingrid' => true,
			);
			break;
		case 'HPHONE':
			$_fields[] = array(
			'lbl' =>  text('Phone').' '.text('House'),
			'field' => 'HousePhone',
			'size' => '25',
			'maxlength' => 200,
			'editable' => true,
			'align' => 'center',
			);
			break;
		case 'OPHONE':
			$_fields[] = array(
			'lbl' => text('Phone').' '.text('Office'),
			'field' => 'OfficePhone',
			'size' => '25',
			'maxlength' => 200,
			'editable' => true,
			'align' => 'center',
			);
			break;
		case 'CPHONE':
			$_fields[] = array(
			'lbl' => text('Phone').' '.text('Cellular'),
			'field' => 'CellularPhone',
			'size' => '25',
			'maxlength' => 200,
			'editable' => true,
			'align' => 'center',
			);
			break;
		case 'STATE':
			$_fields[] = array(
			'lbl' => text('Province'),
			'size' => '15',
			'maxlength' => 2,
			'field' => 'State',
			'editable' => true,
			'hideingrid' => true,
			'align' => 'center',	
			'type' => 'suggest',
			'suggest_id' => 'estados',
			'suggest_browse' => 'sycountriesstatesf',
			'suggest_depends' => array('Country'),	
			);
			break;
		case 'ADDRESS1':
			$_fields[] = array(
			'lbl' => text('Address').' 1',
			'field' => 'Address1',
			'size' => '30',
			'editable' => true,
			'hideingrid' => true
			);
			break;
		case 'ADDRESS2':
			$_fields[] = array(
			'lbl' => text('Address').' 2',
			'field' => 'Address2',
			'size' => '30',
			'maxlength' => 200,
			'editable' => true,
			'hideingrid' => true
			);
			break;
		case 'BIRTHDAY':
			$_fields[] = array(
			'lbl' => text('Birthday'),
			'field' => 'Birthday',
			'size' => '25',
			'editable' => true,
			'align' => 'center',
			'type' => 'date',
			);
			break;
		case 'NATIONALID':
			$_fields[] = array(
			'lbl' => text('NationalID'),
			'field' => 'NationalID',
			'size' => '25',
			'maxlength' => 20,
			'editable' => true,
			'align' => 'center',
			);
			break;
		case 'GENDER':
			$_fields[] = array(
			'lbl' => text('Gender'),
			'field' => 'Gender',
			'size' => '100',
			'maxlength' => 1,
			'editable' => true,
			'hideingrid' => true,
			'align' => 'center',
			'type' => 'droplist',
			'values' => $val_gender,
			'select_msg' => ' ',
			'required' => true,
			);
			break;
		case 'FNAME':
			$_fields[] = array(
			'lbl' => text('Name'),
			'field' => 'FirstName',
			'size' => '30',
			'maxlength' => 20,
			'editable' => true,
			'required' => true,
			);
			break;
		case 'SNAME':
			$_fields[] = array(
			'lbl' => text('SecondName'),
			'field' => 'SecondName',
			'size' => '30',
			'maxlength' => 20,
			'editable' => true,
			'required' => true,
			);
			break;
		case 'LNAME':
			$_fields[] = array(
			'lbl' => text('LastName'),
			'field' => 'LastName',
			'size' => '30',
			'maxlength' => 20,
			'editable' => true,
			'required' => true,
			);
			break;
		case 'SLNAME':
			$_fields[] = array(
			'lbl' => text('SecondLastName'),
			'field' => 'LastName2',
			'size' => '30',
			'maxlength' => 20,
			'editable' => true,
			'required' => true,
			);
			break;
		case 'HREMPLOYEE':
			$_fields[] = array(
			'lbl' => text('Employee'),
			'field' => 'Employee',
			'size' => '40',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'employee',
			'suggest_browse' => 'employeef',
			'default' => ($_REQUEST['topfltrs'][2] <> '') ? 'topfilter_employee' : 'gf_paydeds_0'
			);
			break;
        case 'HRACTION':
            $_fields[] = array(
			'lbl' => text('Action'),
			'field' => 'Action',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'Action',
			'suggest_browse' => 'hractionsf'
			);
			break;
		case 'HRLETTER':
			$_fields[] = array(
			'lbl' => text('Letter'),
			'field' => 'Letter',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrletter',
			'suggest_browse' => 'hrlettersf',
			'width' => '90'
			);
			break;
		case 'RECANDIDATE':
			$_fields[] = array(
			'lbl' => text('Candidate'),
			'field' => 'Candidate',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'candidate',
			'suggest_browse' => 'candidatef',
			'width' => '90'
			);
			break;
		case 'REAPPLICATIONPLAN':
			$_fields[] = array(
			'lbl' => 'AP '.text('Plan'),
			'field' => 'AppPlan',
			'size' => '15',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'applicationplan',
			'suggest_browse' => 'reapplicationsplansf',
			'width' => '60'
			);
			break;
		case 'REAPPLICATIONQUESTION':
			$_fields[] = array(
			'lbl' => text('Application').' '.text('Question'),
			'field' => 'AppQuestion',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'reapplicationquestion',
			'suggest_browse' => 'reapplicationsquestionsf',
			'width' => '90'
			);
			break;
		case 'HRAREAEXPERTISE' :		
			$_fields[] = array(
			'lbl' => text('Area').' '.text('Expertise'),
			'field' => 'AreaOfExpertise',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrareaofexpertise',
			'suggest_browse' => 'hrareasofexpertisef',
			'width' => '90'
			);
			break;
		case 'HRSKILL' :		
			$_fields[] = array(
			'lbl' => text('Skill'),
			'field' => 'Skill',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrskill',
			'suggest_browse' => 'hrskillsf',
			'width' => '90'
			);
			break;
		case 'REAPPLICATIONINDICATOR' :		
			$_fields[] = array(
			'lbl' => text('Application').' '.text('Indicator'),
			'field' => 'AppIndicator',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'reapplicationindicator',
			'suggest_browse' => 'reapplicationsindicatorsf',
			'width' => '90'
			);
			break;
		case 'REAPPLICATION' :		
			$_fields[] = array(		   
			'lbl' => text('Application'),
			'field' => 'Application',
			'size' => '15',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'application',
			'suggest_browse' => 'reapplicationsf',
			'width' => '90'
			);
			break;
		case 'TRCLASSROOM':		
			$_fields[] = array(		   
			'lbl' => text('ClassRoom'),
			'field' => 'ClassRoom',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trclassroom',
			'suggest_browse' => 'trclassroomsf',
			'width' => '90'
			);
			break;
		case 'TRTRAININGSESSION' :		
			$_fields[] = array(
			'lbl' => text('Training').' '.text('Session'),
			'field' => 'TrainingSession',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trtrainingsession',
			'suggest_browse' => 'trtrainingsessionsf',
			'width' => '90',	
			'default' => $default,			
			);
			break;
		case 'TRTRAININGCENTER' :		
			$_fields[] = array(
			'lbl' => text('Training').' '.text('Center'),
			'field' => 'TrainingCenter',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trtrainingcenter',
			'suggest_browse' => 'trtrainingcentersf',
			'width' => '90',	
			'default' => $default,			
			);
			break;
		case 'TRTRAINING' :		
			$_fields[] = array(
			'lbl' => text('Training'),
			'field' => 'Training',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trtraining',
			'suggest_browse' => 'trtrainingsf',
			'width' => '90',	
			'default' => $default,			
			);
			break;

		case 'TRRESOURCE' :		
			$_fields[] = array(
			'lbl' => text('Resource'),
			'field' => 'Resource',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trresource',
			'suggest_browse' => 'trresourcesf',
			'width' => '90',	
			'default' => $default,			
			);
			break;

		case 'TRRESOURCEGROUP' :		
			$_fields[] = array(
			'lbl' => text('Resource').' '.text('Group'),
			'field' => 'ResourceGroup',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trresourcegroup',
			'suggest_browse' => 'trresourcesgroupsf',
			'width' => '90',	
			'default' => $default,			
			);
			break;

		case 'TRTRAININGGROUP' :		
			$_fields[] = array(
			'lbl' => text('Training').' '.text('Group'),
			'field' => 'TrainingGroup',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trtraininggroup',
			'suggest_browse' => 'trtrainingsgroupsf',
			'width' => '90',	
			'default' => $default,			
			);
			break;

		case 'TRINSTRUCTOR' :		
			$_fields[] = array(
			'lbl' => text('Instructor'),
			'field' => 'Instructor',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'trinstructor',
			'suggest_browse' => 'trinstructorsf',
			'width' => '90',	
			'default' => $default,			
			);
			break;
	
		case 'HRPENALIZATION' :		
			$_fields[] = array(
			'lbl' => text('Penalization'),
			'field' => 'Penalization',
			'maxlength' => 4,
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrpenalization',
			'suggest_browse' => 'hrpenalizationsf',
			'width' => '90',	
			'default' => $default,			
			);
			break;
				
		case 'HREMPLOYEENAME' :
			$_fields[] = array(		   			
			'lbl' => text('Name'),
			'field' => 'NameEmployee',
			'editable' => false
			);
			break;
    
	
		case 'PYPAYROLL' :
			$_fields[] = array(			
			'lbl' => text('Payroll'),
			'field' => 'Payroll',
			'size' => '20',
			'align' => 'center',
			'maxlength' => 2,
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'payrolls',
			'suggest_browse' => 'pypayrollsf',
			'width' => '50',
			'fields_type' => 'E',
			'fields' => array('E.Payroll')
			);
			break; 
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'PYCALENDAR' :	   
			$_fields[] = array(			
			'lbl' => text('Calendar'),
			'field' => 'Calendar',
			'size' => '30',
			'maxlength' => 2,
			'editable' => true,
			'align' => 'center',
			'required' => true,  //(clave)		
			'type' => 'suggest',
			'suggest_id' => 'calendars',
			'suggest_browse' => 'pycalendarsf',
			'width' => '50'						
			);
			break;
	

		case 'PYACCUMULATOR' :
			$_fields[] = array(
			'lbl' => text('Accumulator'),
			'field' => 'Accumulator',
			'size' => '30',
			'maxlength' => 2,
			'editable' => true,
			'align' => 'center',
			'required' => true, 		
			'type' => 'suggest',
			'suggest_id' => 'accumulator',
			'suggest_browse' => 'pyaccumulatorsf',
			'width' => '50'					
			);
			break;
	

		case 'PYWAGETYPE' :
			$_fields[] = array(
			'lbl' => text('WageType'),
			'field' => 'WageType',
			'size' => '20',
			'maxlength' => 4,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'width' => '50',			
			'type' => 'suggest',
			'suggest_id' => 'wagetype',
			'suggest_browse' => 'pywagetypesf',
			'width' => '50'		
			);
			break;
	
 
			 
		case 'PYLOAN' :
			$_fields[] = array(
			'lbl' => text('Loan'),
			'field' => 'WageType',
			'size' => '25',
			'align' => 'center',
			'maxlength' => 5,
			'required' => true,
			'editable' => true,
			'type' => 'suggest',
			'suggest_id' => 'wagetype_loan',		
			'suggest_browse' => 'pywagetypesf_loan',	
			'width' => '50'				
			);
			break;
 
			
		case 'PYPAYREASON' :
			$_fields[] = array(
			'lbl' => text('PayReason'),
			'field' => 'PayReason',
			'size' => '30',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'payreasons',
			'suggest_browse' => 'pypayreasonsf',
			'width' => '50'	
			);
			break;
 

		case 'PYPAYMETHOD' :
			$_fields[] = array(
			'lbl' => text('PayMethod'),
			'field' => 'PayMethod',
			'size' => '100',
			'maxlength' => 2,
			'editable' => true,
			'required' => true,
			'align' => 'center',
			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'paymethods',
			'suggest_browse' => 'pypaymethodsf',
			'width' => '50'	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;
 

		case 'PYMODEL' :
			$_fields[] = array(
			'lbl' => text('Model'),
			'field' => 'Model',
			'size' => '20',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'models',
			'suggest_browse' => 'pymodelsf',
			'width' => '50'	
			);
			break;		   
//        		$result = array_merge($result,getSuggest($field));
			break;
 


		case 'PYPAYRUN' :
 			$_fields[] = array(
			'lbl' => text('PayRun'),
			'field' => 'PayRun',
			'size' => '20',
			'align' => 'center',
			'editable' => true,
			'readonly' => true,
			);
			break;

			
 		case 'PYSCHEMA' :
 			$_fields[] = array(			
			'lbl' => text('Payroll').' '.text('Schema'),
			'field' => 'Schema',
			'size' => '30',
			'maxlength' => 10,
			'align' => 'center',
			'editable' => true,
			'type' => 'suggest',
			'suggest_id' => 'PYDriverSchemas',
			'suggest_browse' => 'pydriverschemasf' 			
 			);
			break;


 		case 'PYPAYSLIP' :
 			$_fields[] = array(	
			'lbl' => text('PaySlip'),
			'field' => 'PaySlip',
			'size' => '30',
			'maxlength' => 10,
			'align' => 'center',
			'editable' => true,
			);
			break;

			
		case 'HRGROUP' :	   
			$_fields[] = array( 
			'lbl' => text('Group'),
			'field' => 'Group',
			'size' => '20',
			'maxlength' => 2,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'groups',
			'suggest_browse' => 'hrgroupsf',
			'width' => '30'	
			);
			break; 
//			$result = array_merge($result,getSuggest($field));
			break;
 

		case 'HRSUBGROUP' :	   
			$_fields[] = array( 
			'lbl' => text('SubGroup'),
			'size' => '20',
			'maxlength' => 2,
			'field' => 'SubGroup',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'subgroups',
			'suggest_browse' => 'hrsubgroupsf',
//			'suggest_depends' => array('Group'),
			'width' => '30'		
			);
			break; 
//			$result = array_merge($result,getSuggest($field));
			break;
 			
		
		case 'HRAREA' :	   
			$_fields[] = array( 
			'lbl' => text('Area'),
			'field' => 'Area',
			'size' => '20',
			'maxlength' => 4,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'areas',
			'suggest_browse' => 'hrareasf',
			'width' => '40',
			'fields_type' => 'E',
			'fields' => array('E.Area')
			);
			break; 


		case 'HRPARTITION' :	   
			$_fields[] = array( 
			'lbl' => text('Partition'),
			'field' => 'Partition',
			'size' => '30',
			'maxlength' => 6,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrpartitions',
			'suggest_browse' => 'hrpartitionsf',
			'width' => '80'	
			);
			break; 
 
		case 'HRRATE' :	   
			$_fields[] = array( 
			'lbl' => text('Rate'),
			'field' => 'Rate',
			'size' => '20',
			'maxlength' => 4,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrrate',
			'suggest_browse' => 'hrratesf',
			'width' => '40'	
			);
			break; 
//			$result = array_merge($result,getSuggest($field));
			break;
 
		case 'HRRATEGROUP' :	   
			$_fields[] = array( 
			'lbl' => text('Rate').' '.text('Group'),
			'field' => 'RateGroup',
			'size' => '20',
			'maxlength' => 2,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrrategroup',
			'suggest_browse' => 'hrratesgroupsf',
			'width' => '40'	
			);
			break; 
 
		case 'HRREQUISITION' :	   
			$_fields[] = array( 
			'lbl' => text('Requisition'),
			'field' => 'Requisition',
			'size' => '20',
			'maxlength' => 2,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'hrrequisition',
			'suggest_browse' => 'hrrequisitionsf',
			'width' => '40'	
			);
			break; 
 		
		case 'HRSUBAREA' :	   
			$_fields[] = array( 
			'lbl' => text('SubArea'),
			'field' => 'SubArea',
			'size' => '40',
			'maxlength' => 4,
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'subareas',
			'suggest_browse' => 'hrsubareasf',
			'width' => '40',
			'fields_type' => 'E',
			'fields' => array('E.SubArea')
			);
			break; 
 

		case 'HRPOSITION' :	   
			$_fields[] = array( 
			'lbl' => text('Position'),
			'size' => '40',
			'maxlength' => 20,
			'field' => 'Position',
			'align' => 'center',
			'editable' => true,
			'required' => true,
//			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'positions',
			'suggest_browse' => 'hrpositionsf',
			'width' => '80'			
			);
			break; 
 
			
		case 'REVACANCY' :	   
			$_fields[] = array( 
			'lbl' => text('Vacancy'),
			'size' => '50',
			'maxlength' => 20,
			'field' => 'Vacancy',
			'align' => 'center',
			'editable' => true,
			'required' => true,
//			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'vacancy',
			'suggest_browse' => 'revacanciesf',
			'width' => '80'			
			);
			break; 
 
			
		case 'HRFUNCTION' :	   
			$_fields[] = array( 
			'lbl' => text('Function'),
			'size' => '50',
			'maxlength' => 20,
			'field' => 'Function',
			'align' => 'center',
			'editable' => true,
			'required' => true,
//			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'functions',
			'suggest_browse' => 'hrfunctionsf',
			'width' => '80'			
			);
			break; 
 
			
		case 'HRJOB' :	   
			$_fields[] = array( 
			'lbl' => text('Job'),
			'size' => '50',
			'maxlength' => 20,
			'field' => 'Job',
			'align' => 'center',
			'editable' => true,
			'required' => true,
//			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'jobs',
			'suggest_browse' => 'hrjobsf',
			'width' => '80'			
			);
			break; 
 

		case 'HRDEPARTMENT':   
			$_fields[] = array( 		
			'lbl' => text('Department'),
			'field' => 'Department',
			'size' => '25',
			'maxlength' => 20,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'required' => true,			
			'suggest_id' => 'department2',
			'suggest_browse' => 'hrdepartmentsf',
			'width' => '80',
			'fields_type' => 'E',
			'fields' => array('E.Department')
			);
			break; 
//			$result = array_merge($result,getSuggest($field));
			break;
 
			
		case 'TMSCHEDULE' :	   
			$_fields[] = array(
			'lbl' => text('Schedule'),
			'field' => 'Schedule',
			'size' => '25',
			'maxlength' => 10,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'schedules',
			'suggest_browse' => 'tmschedulesf',
			'width' => '80'	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	

		case 'TMSCHEDULEDAILY' :	   
			$_fields[] = array(
			'lbl' => text('Daily').' '.text('Schedule'),
			'field' => 'Daily',
			'size' => '30',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'required' => true,			
			'type' => 'suggest',
			'suggest_id' => 'schedulesdaily',
			'suggest_browse' => 'tmschedulesdailyf',	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	


		case 'TMCALENDAR' :	   
			$_fields[] = array(
			'lbl' => text('Calendar'),
			'field' => 'Calendar',
			'size' => '100',
			'align' => 'center',
			'maxlength' => 2,
			'editable' => true,
			'required' => true,
			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'tmcalendars',
			'suggest_browse' => 'tmcalendarsf',
			'width' => '50'	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	


		case 'TMABSENCE' :	   
			$_fields[] = array(
			'lbl' => text('Absence'),
			'field' => 'Absence',
			'size' => '20',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'absence',
			'suggest_browse' => 'tmabsencesf'
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	

		case 'TMPRESENCE' :	   
			$_fields[] = array(
			'lbl' => text('Presence'),
			'field' => 'Presence',
			'size' => '20',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'presences',
			'suggest_browse' => 'tmpresencesf'
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	

		case 'TMSUBSTITUTION' :	   
			$_fields[] = array(
			'lbl' => text('Substitution'),
			'field' => 'Substitution',
			'size' => '20',
			'maxlength' => 4,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'substitutions',
			'suggest_browse' => 'tmsubstitutionsf'
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	

		case 'TMAUTHGROUP' :	   
			$_fields[] = array(
			'lbl' => text('AuthGroup'),
			'size' => '50',
			'field' => 'AuthGroup',
			'editable' => true,
			'hideingrid' => true,
			'align' => 'center',
			//'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'authgroup',
			'suggest_browse' => 'tmauthorizationgroupsf',
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	
			
		case 'TMAUTHAREA' :	   
			$_fields[] = array(
			'lbl' => text('AuthArea'),
			'field' => 'AuthArea',
			'size' => '20',
			'maxlength' => 2,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'authareas',
			'suggest_browse' => 'tmauthorizationareasf',
			'editable' => true,
			'align' => 'center',
			'hideingrid' => true,	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	
			
		case 'TMAUTHREASON' :	
			$_fields[] = array( 
			'lbl' => text('AuthReason'),
			'field' => 'AuthReason',
			'size' => '20',
			'maxlength' => 2,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'authreason',
			'suggest_browse' => 'tmauthorizationreasonsf',
			'editable' => true,
			'align' => 'center',
			'hideingrid' => true,	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;	
	

 		case 'TMSCHEMA' :
 			$_fields[] = array(			
			'lbl' => text('Time').' '.text('Schema'),
			'field' => 'Schema',
			'size' => '30',
			'maxlength' => 10,
			'align' => 'center',
			'editable' => true,
			'type' => 'suggest',
			'suggest_id' => 'TMDriverSchemas',
			'suggest_browse' => 'tmdriverschemasf' 			
 			);
			break;

			
		case 'FICOSTCENTER' :	   
			$_fields[] = array( 			
			'lbl' => text('CostCenter'),
			'field' => 'CostCenter',
			'size' => '30',
			'maxlength' => 20,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'costcenters',
			'suggest_browse' => 'ficostcentersf',
			'width' => '80',
			'fields_type' => 'E',
			'fields' => array('E.CostCenter')
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'FIGLACCOUNT' :	   
			$_fields[] = array( 			
			'lbl' => text('Account'),
			'field' => 'GLAccount',
			'size' => '40',
			'align' => 'center',
			'editable' => true,
			'maxlength' => 25,
			'type' => 'suggest',
			'suggest_id' => 'figlaccounts',	
			'suggest_browse' => 'figlaccountsf',
			//'required' => true,
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'FIBANK' :	   
			$_fields[] = array( 			
			'lbl' => text('Bank'),
			'field' => 'Bank',
			'size' => '30',
			'width' => '50',	
			'align' => 'center',
			'maxlength' => '5',
			'required' => true,
			'editable' => true,
			'type' => 'suggest',
			'suggest_id' => 'bank',
			'suggest_browse' => 'fibanksf'
			);
			break;


		case 'FIBANKC' :	   
			$_fields[] = array( 			
			'lbl' => text('Bank'),
			'field' => 'Bank',
			'size' => '100',
			'width' => '50',	
			'align' => 'center',
			'maxlength' => 6,
			'editable' => true,
			'hideingrid' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'bank2',
			'suggest_browse' => 'ficompaniesbanksf',
			);
			break;



		case 'EVEVALUATION' :	   
			$_fields[] = array( 
			'lbl' => text('Evaluation'),
			'size' => '25',
			'maxlength' => 4,
			'field' => 'Evaluation',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'evaluations',
			'suggest_browse' => 'evevaluationsf',
			'width' => '80'			
			);
			break; 
 
			case 'PECALIBRATION' :	   
			$_fields[] = array( 
			'lbl' => text('Calibration'),
			'size' => '40',
			'maxlength' => 4,
			'field' => 'Calibration',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'calibration',
			'suggest_browse' => 'pecalibrationf',
			'width' => '80'			
			);
			break; 
 
			case 'CALIBRATIONSESSION' :		
			$_fields[] = array(
			'lbl' => text('Session'),
			'field' => 'Session',
			'size' => '20',
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'calibrationsession',
			'suggest_browse' => 'pecalibrationsessionf',
			'width' => '90',	
			'default' => $default,			
			);
			break;

			case 'PEGRID' :	   
			$_fields[] = array( 
			'lbl' => text('Grid'),
			'size' => '40',
			'maxlength' => 6,
			'field' => 'Grid',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'grid',
			'suggest_browse' => 'pegridf',
			'width' => '80'			
			);
			break; 
 


		case 'TSTEST' :	   
			$_fields[] = array( 
			'lbl' => text('Test'),
			'size' => '50',
			'maxlength' => 4,
			'field' => 'Test',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'test',
			'suggest_browse' => 'tstestsf',
			'width' => '80'			
			);
			break; 

			case 'PTPSICOTEST' :	   
			$_fields[] = array( 
			'lbl' => text('PsicoTest'),
			'size' => '50',
			'maxlength' => 5,
			'field' => 'PsicoTest',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'psicotest',
			'suggest_browse' => 'ptpsicotestsf',
			'width' => '80'			
			);
			break; 
 
			case 'GOGOAL' :	   
			$_fields[] = array( 
			'lbl' => text('Goal'),
			'size' => '25',
			'maxlength' => 4,
			'field' => 'Goal',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'goals',
			'suggest_browse' => 'gogoalsf',
			'width' => '80'			
			);
			break; 
 
			case 'GOPARENTGOAL' :	   
			$_fields[] = array( 
			'lbl' => text('Parent').' '.text('Goal'),
			'size' => '25',
			'maxlength' => 4,
			'field' => 'ParentGoal',
			'align' => 'center',
			'editable' => true,
			'type' => 'suggest',
			'suggest_id' => 'goals',
			'suggest_browse' => 'gogoalsf',
			'width' => '80'			
			);
			break; 
 

		case 'EVINDICATOR' :	   
			$_fields[] = array( 
			'lbl' => text('Indicator'),
			'size' => '50',
			'maxlength' => 10,
			'field' => 'Indicator',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'evindicators',
			'suggest_browse' => 'evindicatorsf',
			'width' => '80'			
			);
			break; 

		case 'PEINDICATORRATE' :	   
			$_fields[] = array( 
			'lbl' => text('Indicator').' '.text('Indicator'),
			'size' => '50',
			'maxlength' => 10,
			'field' => 'IndicatorRate',
			'align' => 'center',
			'editable' => true,
			'required' => true,
//			'hideingrid' => true,
			'type' => 'suggest',
			'suggest_id' => 'peindicatorrate',
			'suggest_browse' => 'peindicatorsratesf',
			'width' => '80'			
			);
			break; 
 
		case 'EVINDICATORGROUP' :	   
			$_fields[] = array( 
			'lbl' => text('Indicator').' '.text('Group'),
			'size' => '50',
			'maxlength' => 10,
			'field' => 'IndicatorGroup',
			'align' => 'center',
			'editable' => true,
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'indicatorgroup',
			'suggest_browse' => 'evindicatorsgroupsf',
			'width' => '80'			
			);
			break; 
 
			case 'SVSURVERY' :	   
			$_fields[] = array(		
			'lbl' => text('Survery'),
			'field' => 'Survery',
			'size' => '15',
			'maxlength' => 2,
			'editable' => true,
			'hideingrid' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'survery',
			'suggest_browse' => 'surveryf',
			'width' => '50'	
			);
			break;

		case 'SYCOMPANY' :	   
			$_fields[] = array( 
			'lbl' => text('Company'),
			'field' => 'Company',
			'size' => '30',
			'maxlength' => 5,
			'hideingrid' => true, // (oculta en listado)
			'hideinview' => true, // (oculta en modo vista)
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'sycompanies',
			'suggest_browse' => 'sycompaniesf',
			'width' => '50'		
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;
		        break;

 
		case 'SYNATIONALITY' :	   
			$_fields[] = array(
			'lbl' => text('Nationality'),
			'field' => 'Nationality',
			'size' => '30',
			'maxlength' => 3,
			'editable' => true,
			'hideingrid' => true,			
			'type' => 'suggest',
			'suggest_id' => 'nationalities',
			'suggest_browse' => 'synationalitiesf',
			'width' => '50'	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;
	        	break;

		case 'SYCURRENCY' :	   
			$_fields[] = array(
			'lbl' => text('Currency'),
			'name' => 'Currency',
			'ord' => 'Currency',
			'field' => 'Currency',
			'size' => '30',
			'maxlength' => 3,
			'editable' => true,
//			'required' => true,	
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'currencies2',
			'suggest_browse' => 'sycurrenciesf'
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;

				
		case 'SYCURRENCYC' :	   
			$_fields[] = array(
			'lbl' => text('Currency'),
			'field' => 'Currency',
			'size' => '20',
			'maxlength' => 3,
			'editable' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'currencies',
			'suggest_browse' => 'sycompaniescurrenciesf',
			'width' => '50'	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;
			
		
		case 'SYCOUNTRY' :	   
			$_fields[] = array(		
			'lbl' => text('Country'),
			'field' => 'Country',
			'size' => '15',
			'maxlength' => 2,
			'editable' => true,
			'hideingrid' => true,
			'align' => 'center',
			'type' => 'suggest',
			'suggest_id' => 'countries',
			'suggest_browse' => 'sycountriesf',
			'width' => '50'	
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'SYLANGUAGE' :	   
			$_fields[] = array(
			'lbl' => text('Language'),
			'field' => 'Language',
			'size' => '30',
			'maxlength' => 2,
			'editable' => true,
			'align' => 'center',
			'hideingrid' => true,
			'align' => 'center',
			'type' => 'droplist',
			'values' => $val_langs,
			'select_msg' => ' ',
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'SYUSERID' :	   
			$_fields[] = array(
			'lbl' => text('User'),
			'field' => 'UserID',
			'size' => '20',
			'maxlength' => 10,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'users',
			'suggest_browse' => 'syusersf'
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;

			
		case 'SYPROFILE' :	   
			$_fields[] = array(
			'lbl' => text('Profile'),
			'field' => 'Profile',
			'size' => '30',
			'maxlength' => 10,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'profiles',
			'suggest_browse' => 'syprofilesf',
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'SYTABLE' :	   
			$_fields[] = array(
			'lbl' => text('Table'),
			'field' => 'Table',
			'size' => '50',
			'maxlength' => 100,
			'editable' => true,
			//	'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'sytables',
			'suggest_browse' => 'sytablesf'
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;

			
		case 'SYTRANSACTION' :	   
			$_fields[] = array(	
			'lbl' => text('Transaction'),
			'field' => 'Transaction',
			'size' => '30',
			'maxlength' => 10,
			'editable' => true,
			'align' => 'center',
			'required' => true,
			'type' => 'suggest',
			'suggest_id' => 'transactions',
			'suggest_browse' => 'sytransactionsf',
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;


		case 'DESCRIPTION' :	
			$_fields[] = array(	
			'lbl' => text('Description'),
			'field' => 'Text',			
			'fieldQuery' => 'T.Text',
			'size' => '100',
			'editable' => true,
			'type' => 'translation',
			'langs' => $langs,
			'required' => true,
			'width' => '350',
			'extraurl' => '&bycomp='.$_REQUEST['fltrs'][1]
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;



		case 'DESCRIPTIONT' :	
			$_fields[] = array(	
			'lbl' => text('Description'),
			'field' => 'descripcion',
			'size' => '60',
			);
			break;



		case 'TYPE' :	
			$_fields[] = array(
			'lbl' => text('Type'),
			'field' => 'Type',
			'size' => '20',
			'maxlength' => 1,
			'align' => 'center',
			'editable' => true,
			'required' => true,			
			'type' => 'droplist',
			'select_msg' => ' ',
			);
			break;
//			$result = array_merge($result,getSuggest($field));
			break;

			
		case 'BLOODTYPE' :			
			$_fields[] = array(
			'lbl' => text('BloodType'),
			'field' => 'BloodType',
			'size' => '25',
			'maxlength' => 3,
			'editable' => true,
			'align' => 'center',
			'type' => 'droplist',
			'values' => $val_bloodType,
			'select_msg' => ' '
			);
			break;


		case 'DATE' :			
			$_fields[] = array(			
			'lbl' => text('Date'),
			'field' => 'Date',
			'size' => '40',
			'editable' => true,
			'required' => true,	
			'align' => 'center',
			'type' => 'date',
			'width' => 100
			);
			break;

			
		case 'DATEBEG' :			
			$_fields[] = array(	
			'lbl' => text('DateBeg'),
			'field' => 'DateBeg',
			'size' => '40',
			'editable' => true,
			'required' => true,
			'align' => 'center',
			'type' => 'date',
			'width' => 100
			);
			break;

	
		case 'DATEEND' :			
			$_fields[] = array(	
			'lbl' => text('DateEnd'),
			'field' => 'DateEnd',
			'size' => '40',
			'editable' => true,
			'required' => true,
			'align' => 'center',
			'type' => 'date',
			'width' => 100
			);
			break;


		case 'TIME' :			
			$_fields[] = array(
			'lbl' => text('Time'),
			'field' => 'Time',
			'size' => '20',
			'width' => '50',
			'editable' => true,
			'required' => true,
			'align' => 'center',
			'type' => 'time',
			);
			break;


		case 'TIMEBEG' :			
			$_fields[] = array(
			'lbl' => text('TimeBeg'),
			'field' => 'TimeBeg',
			'size' => '20',
			'editable' => true,
			'required' => true,
			'align' => 'center',
			'type' => 'time',
			);
			break;


		case 'TIMEEND' :			
			$_fields[] = array(
			'lbl' => text('TimeEnd'),
			'field' => 'TimeEnd',
			'size' => '20',
			'editable' => true,
			'required' => true,
			'align' => 'center',
			'type' => 'time',
			);
			break;


		case 'YEAR' :			
			$_fields[] = array(			
			'lbl' => text('Year'),
			'field' => 'Year',
			'size' => '30',
			'editable' => true,
			'align' => 'center',
			'required' => true, 
			'type' => 'text',
			'width' => '50',
			);
			break;

			
		case 'PERIOD' :			
			$_fields[] = array(			
			'lbl' => text('Period'),
			'field' => 'Period',
			'size' => '30',
			'maxlength' => 2,
			'editable' => true,
			'align' => 'center',
			'required' => true, 
			'type' => 'text',
			'width' => '50',
			);
			break;
			
	
		case 'AMOUNT' :			
			$_fields[] = array(	
			'lbl' => text('Amount'),
			'field' => 'Amount',
			'size' => '30',
			'editable' => true,
			'align' => 'right',
			'hideingrid' => true,
			'type' => 'calculator',
			);
			break;


		case 'BOOLEAN' :			
			$_fields[] = array(	
			'lbl' => text('Closed'),
			'field' => 'Closed',
			'size' => '20',
			'editable' => true,
			'type' => 'checkbox',
			'align' => 'center',
			);
			break;


		case 'BOOLEANDL' :			
			$_fields[] = array(
			'lbl' => text('Boolen'),
			'type' => 'droplist',
			'values' => array(array('lbl' => 'X', 'val' => 'X'), array('lbl' => 'N', 'val' => 'N')),
			'width' => '40',			
			);
			break;	
	
			
		case 'INACTIVE' :			
			$_fields[] = array(	
			'lbl' => text('Inactive'),
			'field' => 'Inactive',
			'size' => '10',
			'editable' => true,
			'type' => 'checkbox',
			'align' => 'center',
			);
			break;


		case 'INACTIVEDL' :			
			$_fields[] = array(
			'lbl' => text('Inactive'),
			'type' => 'droplist',
			'values' => array(array('lbl' => 'X', 'val' => 'X'), array('lbl' => 'N', 'val' => 'N')),
			'width' => '40',				
			);
			break;	
		   

		case 'NOTE' :			
			$_fields[] = array(	
			'lbl' => text('Note'),
			'field' => 'Note',
			'size' => '30',
			'editable' => true,
			'width' => '250',
			);
			break; 

/*
		case 'LINK' :			
			$_fields[] = array(	
			'lbl' => 'Enlace',
			'field' => 'Link',
			'size' => '30',
			'maxlength' => 10,
			'editable' => true,
			'readonly' => true,
			'align' => 'center',
			);
			break;

			*/
		case 'SYUSER' :			
			$_fields[] = array(	
			'lbl' => text('User'),
			'field' => 'User',
			'size' => '30',
			'align' => 'center',
			'hideinview' => true,
			);
			break;

			
		case 'TIMESTAMP' :			
			$_fields[] = array(	
			'lbl' => text('TimeStamp'),
			'field' => 'TimeStamp',
			'type' => 'timestamp',
			'size' => '30',
			'align' => 'center',
			'hideinview' => true,
			);
			break;

			
		case 'INFO' :			
			$_fields[] = array(	
			'lbl' => text('Info'),
			'field' => 'info',
			'type' => 'info',
			'hideingrid' => true,
			'editable' => true,
			'readonly' => true,
			);
			break;
			

		case 'DATERANGE' :			
			$_fields[] = array(	
			'lbl' => Text('From'),
			'type' => 'daterange',
			'fields_type' => 'D'
			);
			break;
	

	   default: 
           
		   msgerror('Tipo de Campo No Definido : ' . $field.' ('.$k.')'/*.print_r($fields, true)*/);
	
        }
		
		return $_fields;
	}
	
	protected function _beforeAction()
	{
		
	}
	
	protected function _beforeEdit()
	{
		
	}
	
	protected function _beforeAdd()
	{
		
	}
	
	protected function _afterAction()
	{
		
	}
	
	protected function _afterEdit()
	{
		
	}
	
	protected function _afterAdd()
	{
		
	}
	
	function format($col, $val, $row, $orow)
	{
		return $val;
	}
	
	function confirmSave($extrarowdata)
	{
		return true;
	}
	
	function confirmDel($id)
	{
		return true;
	}
}