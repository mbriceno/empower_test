<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	function __construct()
	{
		global $company, $db_GLIAR;
		
		$this->_filters = array(array('EMPTYPE'));
		
		$isEmployee = ($_REQUEST['fltrs'][1] == 'E');
		
		$this->_fields = array(
		array('SYCOMPANY'),
		array($isEmployee ? 'HREMPLOYEE' : 'RECANDIDATE', array(
			'fieldQuery' => 'A.'.($isEmployee ? 'Employee' : 'Candidate')
		)),
		array('HREMPLOYEENAME', array(
			'field' => 'NameCandidate',
			'fieldQuery' => 'CONCAT(E.FirstName, " ", E.LastName)'
		)),
		array('TIME', array(
			'hideingrid' => true
		)),
		array('HREMPLOYEE', array(
			'field' => 'Referrer'
		)),
		array('HREMPLOYEENAME', array(
			'fieldQuery' => 'CONCAT(E2.FirstName, " ", E2.LastName)'
		)),		
		array('STATUS', array(
			'editable' => true,
			'required' => true,
			'type' => 'droplist',
			'values' => array(
				array(
				'lbl' => text('New'),
				'val' => 'N'
				),
				array(
				'lbl' => text('Evaluation'),
				'val' => 'E'
				),
				array(
				'lbl' => text('Submitted'),
				'val' => 'S'
				),
				array(
				'lbl' => text('Approved'),
				'val' => 'A'
				),
				array(
				'lbl' => text('Rejected'),
				'val' => 'R'
				),
				array(
				'lbl' => text('Canceled'),
				'val' => 'C'
				)
			)
		)),
		array('NOTE')
		);
		
		if ($isEmployee)
		{
			$this->_table = EMPLOYEE::TBL_REFERALS;
			$this->_replyChanges = array($db_GLIAR);
			$this->_leftJoins = array('EMPLOYEE');
		}
		else
		{
			$this->_table = CANDIDATE::TBL_REFERALS;
			$this->_where = 'A.Company IN ("GLIAR", ?)';
			$this->_params = array($company);
			$this->_filterByComp = false;
			$this->_leftJoins = array(array(
				CANDIDATE::TBL.' E',
				'(A.Company = E.Company OR E.Company = "GLIAR") AND A.Candidate = E.Candidate'
			));
			$this->_db = $db_GLIAR;
		}
		
		$this->_leftJoins[] = array(
		EMPLOYEE::TBL.' E2',
		'A.Company = E2.Company AND A.Referrer = E2.Employee'
		);
		
		return parent::__construct();
	}
	
	function format($col, $val, $row, $orow)
	{
		return $val;
	}
}

$datagrid = new _DATAGRID();