<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	protected $_inactiveField = false;
	
	protected $_orderBy = 'DateEnd DESC';
	
	protected $_filters = array(
	array('SYUSERID', array(
		'fields' => array('A.UserId')
	))
	);
	
	function __construct()
	{
		global $company;
		
		$this->_fields = array(
		array('SYUSERID', array(
		'lbl' => text('User'),
		'field' => 'UserId1',
		'suggest_id' => 'usersa',
		'suggest_browse' => 'syusersaf'
		)),
		array('DATEBEG'),
		array('DATEEND'),
		array('TITLE', array(
		'required' => true,
		'width' => 450
		)),
		array('TEXTAREA', array(
		'lbl' => text('Documentation'),
		'field' => 'Documentation',
		'width' => 450,
		'rows' => 6
		)),
		array('TEXTAREA', array(
		'lbl' => text('ChangesLog'),
		'field' => 'ChangesLog',
		'width' => 450,
		'readonly' => true,
		'rows' => 6
		)),
		array('SYUSERID', array(
		'lbl' => text('User').' 2',
		'field' => 'UserId2',
		'suggest_id' => 'usersa',
		'required' => false,
		'suggest_browse' => 'syusersaf'
		)),
		array('SYUSERID', array(
		'lbl' => text('User').' 3',
		'field' => 'UserId3',
		'suggest_id' => 'usersa',
		'required' => false,
		'suggest_browse' => 'syusersaf'
		)),
		array('INACTIVE'),
		array('INACTIVE', array(
		'lbl' => text('Closed'),
		'field' => 'Closed'
		))
		);
		
		$this->_where = 'Company = ?';
		$this->_params = array($company);
		
		return parent::__construct();
	}
	
	protected function _beforeAction()
	{
		global $db, $company, $userid;
		
		$this->_fixedFieldsVals = array('Company' => $company, 'User' => $userid);

		if ($_REQUEST['oper'] == 'edit')
		{
			list($id, $foo) = explode('|', $_REQUEST['id']);
			$r = $db->getOne('SELECT ChangesLog FROM '.SECURITY::TBL_USRSUPP.' WHERE Company = ? AND UserId1 = ?', array($company, $id));
			$this->_fixedFieldsVals['ChangesLog'] = (empty($r['ChangesLog']) ? array() : json_decode($r['ChangesLog']));		
		}
		else
			$this->_fixedFieldsVals['ChangesLog'] = array();
		
		$this->_fixedFieldsVals['ChangesLog'][] = array('User' => $_REQUEST['UserId1'], 'User2' => $_REQUEST['UserId2'], 'User3' => $_REQUEST['UserId3'], 'TimeStamp' => date('Y-m-d H:i:s'), 'DateBeg' => $_REQUEST['DateBeg'], 'DateEnd' => $_REQUEST['DateEnd'], 'Title' => $_REQUEST['Title'], 'Documentation' => $_REQUEST['Documentation'], 'UserId' => $userid);
		
		$this->_fixedFieldsVals['ChangesLog'] = json_encode($this->_fixedFieldsVals['ChangesLog']);
	}
	
	function confirmSave($extrarowdata)
	{
		global $company, $db;
		
		$beg = CORE::getMySQLDate($_REQUEST['DateBeg']);
		$end = CORE::getMySQLDate($_REQUEST['DateEnd']);
		$p = explode('|', $_REQUEST['id']);
		
		if ($_REQUEST['oper'] == 'edit')
		{
			$r = $db->getOne('SELECT Closed, DateBeg FROM '.SECURITY::TBL_USRSUPP.' WHERE Company = ? AND UserId1 = ?', array($company, $p[0]));
			if ($r['Closed'] == 1)
				return 'Registro cerrado, no puede editarse.';
			if ($beg < $r['DateBeg'] && $beg < date('Y-m-d'))
				return 'No puede cambiar la fecha de inicio menor a la existente ('.CORE::formatMySQLDate($r['DateBeg']).') y pasada.';
		}
		else
		{
			$r = $db->getOne('SELECT Company FROM '.SECURITY::TBL_USRSUPP.' WHERE Company = ? AND UserId1 = ? AND Closed = 0 AND (DateEnd >= NOW() OR DateBeg >= NOW())', array($company, $_REQUEST['UserId1']));
			if (! empty($r['Company']))
				return 'Ya existe un registro abierto para el consultor.';
		}
		
		$users = array();
		
		for($i = 1; $i <= 3; $i++)
			if ($_REQUEST['UserId'.$i] != '')
			{
				if (in_array($_REQUEST['UserId'.$i], $users)) return 'Los usuarios deben ser distintos.';
				
				$r = $db->getOne('SELECT UserId FROM '.COMPANY::TBL_USERS.' WHERE Company = ? AND UserId = ?', array($company, $_REQUEST['UserId'.$i]));
				if (empty($r['UserId']))
					return 'Sólo puede seleccionar usuarios de la compañía.';	
				
				$r = $db->getOne('SELECT UserId FROM '.CORE::TBL_USERS.' WHERE UserId = ? AND Type IN ("A", "B", "C")', array($_REQUEST['UserId'.$i]));
				if (empty($r['UserId']))
					return 'Sólo puede seleccionar un usuario de tipo A, B o C.';
				
				$users[] = $_REQUEST['UserId'.$i];
			}
		
		if ($end < $beg)
			return 'La fecha de fin no puede ser inferior a la de inicio.';
		
		$today = date('Y-m-d');
		
		if ($_REQUEST['oper'] == 'add' && $beg < $today)
			return 'La fecha de inicio no puede ser pasada.';
		
		if ($end < $today)
			return 'La fecha de fin no puede ser pasada.';
		
		$dateEnd = CORE::getMySQLDate($_REQUEST['DateEnd']);
		$today = date('Y-m-d');
		
		if ($_REQUEST['Closed'] == 'Yes' && $dateEnd > $today)
			$_REQUEST['DateEnd'] = CORE::formatMySQLDate($today);
		
		return true;
	}
	
	function confirmDel($id)
	{
		global $db, $company;
	
		$p = explode('|', $id);
		
		$r = $db->getOne('SELECT Closed, IF(DateBeg >= NOW(), 0, 1) Past FROM '.SECURITY::TBL_USRSUPP.' WHERE Company = ? AND UserId1 = ?', array($company, $p[0]));
		
		if ($r['Closed'] == 1) return 'Registro cerrado, no puede borrarse.';
		
		if ($r['Past'] == 1) return 'Registro ya iniciado, no puede borrarse.';
		
		return true;
	}
	
	function format($col, $val, $row, $orow)
	{
		if ($col == 'ChangesLog')
		{
			$_val = '';
			foreach(json_decode($val, true) as $v)
			{
				if ($_val) $_val .= '==============================='."\n";
				$_val .= $v['UserId'].' '.$v['TimeStamp']."\n".'Usuario '.$v['User']."\n".'Fecha inicio '.$v['DateBeg']."\n".'Fecha fin '.$v['DateEnd']."\n".'Usuario 2 '.$v['User2']."\n".'Usuario 3 '.$v['User3']."\n".'Documentación: '.$v['Documentation']."\n".'Título: '.$v['Title']."\n";
			}
			return $_val;
		}
		
		return $val;
	}
}

$datagrid = new _DATAGRID();