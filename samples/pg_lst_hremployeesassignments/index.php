<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	protected $_multiselect = true;
	protected $_inactiveField = false;
	
	protected $_innerJoins = array('EMPLOYEE');
	protected $_langTable = array('HR_AssignmentsTypesTexts', 'HR_AssignmentsTypesByCompaniesTexts');
	
	function __construct()
	{
		global $db;
		
		$this->_filters = array(
		array(
		'lbl' => text('Assignment'),
		'field' => 'AssignmentType',
		'type' => 'suggest','width' => '50',
		'suggest_id' => 'assignmentstypes',
		'suggest_browse' => 'hrassignmentstypesf',
		'fields' => array('A.AssignmentType', 'T.Text', 'T1.Text')
		),
		array(
		'lbl' => 'Serial No.',
		'field' => 'SerialNumber',
		'width' => '120',
		'size' => '50',
		'type' => 'text',
		'fields' => array('SerialNumber'),
		'fields_type' => 'L'
		)
		);
		
		$this->_fields = array(
		array('HREMPLOYEE'),
		array('HREMPLOYEENAME', array(
			'fieldQuery' => 'CONCAT(E.FirstName, " ", E.LastName)'
		)),
		array(
		'lbl' => text('Assignment').' '.text('Type'),
		'field' => 'AssignmentType',
		'size' => '30',
		'maxlength' => 2,
		'editable' => true,
		'align' => 'center',
		'required' => true,
		'type' => 'suggest',
		'suggest_id' => 'assignmentstypes',
		'suggest_browse' => 'hrassignmentstypesf'
		),
		array(
		'lbl' => text('Description'),
		'field' => 'AssignmentTypeDesc',
		'size' => '60',
		'width' => 30,
		'align' => 'center',
		'fieldQuery' => 'IF(T1.Text IS NULL OR T1.Text = "", T.Text, T1.Text)'
		),
		array('SECUENCY', array(
			'readonly' => true
		)),
		array(
		'lbl' => text('Price'),
		'field' => 'Price',
		'ord' => 'Salary',
		'size' => '25',
		'align' => 'right',
		'editable' => true,
		'normalizeNumber' => true,
		'type' => 'calculator',
		),
		array(
		'lbl' => text('Size'),
		'field' => 'Size',
		'size' => '25',
		'align' => 'center',
		'editable' => true,
		'normalizeNumber' => true
		),
		array(
		'lbl' => 'Serial No.',
		'field' => 'SerialNumber',
		'size' => '30',
		'hideingrid' => false,
		'align' => 'center',
		'editable' => true,
		'maxlength' => 60
		),
		array('DATE', array(
			'lbl' => text('Assignment').' '.text('Date'),
			'field' => 'AssignmentDate',
		)),
		array('DATE', array(
			'lbl' => text('Return').' '.text('Date'),
			'field' => 'ReturnDate',
			'required' => false
		)),
		array(
		'lbl' => text('Description'),
		'field' => 'Description',
		'size' => '30',
		'maxlength' => 20,
		'editable' => true,
		'required' => true,
		'align' => 'center',
		'attach' => true,
		'attach_width' => 700,
		'attach_height' => 700,
		),
		array('ATTACHMENT')
		);

		$this->_defaultFieldsVals = array(
		'ReturnDate' => $db->minDate
		);
		
		return parent::__construct();
	}
	
	function format($col, $val, $row, $orow)
	{
		global $_company, $company;

		if ($col == 'Price') return $_company->numberFormat($val);

		if ($col == 'Attachment')
		{
			if ($val)
			{
				$info = pathinfo($val);
				return '<a href="#" onclick="window.open(\''.getResourceURL('hremployeesassignments-Description', $company, $info['filename']).'\', \'_blank\');"><img src="img/attachment2.png" /></a>';
			}
			return '';
		}

		return $val;
	}
	
	protected function _afterAction()
	{
		global $db, $company;
		
		if ($_REQUEST['oper'] != 'del' && empty($_REQUEST['Secuency']))
		{
			$r = $db->getOne('SELECT MAX(Secuency) s FROM HR_EmployeesAssignments WHERE Company = ? AND Employee = ? AND AssignmentType = ?', array($company, $_REQUEST['Employee'], $_REQUEST['AssignmentType']));
			$_REQUEST['Secuency'] = sprintf('%04d', (empty($r['s']) ? 1 : $r['s'] + 1));
		}
	}
}

$datagrid = new _DATAGRID();