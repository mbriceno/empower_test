<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	protected $_disableCUD = true;
	
	protected $_innerJoins = array('EMPLOYEE');
	
	protected $_langTable = CORE::TBL_ACTIONS_LANG;
	
	function __construct()
	{	
		$this->_filters = array(
		array('DATERANGE', array(
			'fields' => array('A.Date'),
			'default' => 'thisMonth'
		)),
		array(
		'lbl' => text('Action'),
		'type' => 'droplist',
		'values' => array(
			array('val' => 'E', 'lbl' => text('Hire')),
			array('val' => 'S', 'lbl' => text('Fire'))
		),
		'fields_type' => 'O',
		'fields' => array(
			'' => '(A.Action IN ("CO", "CR") OR SUBSTRING(A.Action, 1, 1) = "T"))',
			'E' => 'A.Action IN ("CO", "CR")',
			'S' => 'SUBSTRING(A.Action, 1, 1) = "T"'
		)
		//'onchange' => 'alert("test")'
		),
		array('FICOSTCENTER', array(
			'secondary' => true
		)),
		array('HRDEPARTMENT', array(
			'secondary' => true
		)),
		array('HRAREA', array(
			'secondary' => true
		)),
		array('HRSUBAREA', array(
			'secondary' => true
		)),
		array('PYPAYROLL', array(
			'secondary' => true
		))
		);
		
		$this->_fields = array(
		array('HREMPLOYEE'),
		array('HREMPLOYEENAME', array(
			'fieldQuery' => 'CONCAT(E.FirstName, " ", E.LastName)'
		)),
		array('HRACTION'),
		array(
		'lbl' => text('Action').' '.text('Description'),
		'field' => 'Text',
		'fieldQuery' => 'T.Text',
		'align' => 'left'
		),
		array(
		'lbl' => text('NationalID'),
		'field' => 'NationalID',
		'fieldQuery' => 'E.NationalID',
		'align' => 'center',
		),
		array('AMOUNT', array(
			'lbl' => text('Salary'),
			'field' => 'SalaryT'
		)),
		array('HRPOSITION', array(
			'field' => 'PositionT'
		)),
		array(
		'lbl' => text('Position').' Desc.',
		'field' => 'PositionDesc',
		'fieldQuery' => 'PL.Text',
		),
		array('HRSUBAREA', array(
			'fieldQuery' => 'E.SubArea'
		)),
		array('DATE', array(
			'fieldQuery' => 'A.Date'
		))
		);	
		
		$this->_leftJoins = array(
		array(POSITION::TBL_LANG.' PL', 'A.Company = PL.Company AND PositionT = PL.Position AND PL.Language = ?', array('LANG'))
		);
		
		return parent::__construct();
	}
	
	function format($col, $val, $row, $orow)
	{
		return $val;
	}
}

$datagrid = new _DATAGRID();