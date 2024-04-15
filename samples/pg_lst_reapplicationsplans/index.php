<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	protected $_inactiveField = false;
	
	protected $_filters = array(array('REAPPLICATIONPLAN'));
	
	protected $_langTable = CANDIDATE::TBL_APPPLAN_LANG;
	
	function __construct()
	{	
		global $db_GLIAR;
		
		$this->_replyChanges = array($db_GLIAR);
		
		$this->_fields = array(
		array('REAPPLICATIONPLAN', array(
			'type' => 'text'
		)),
		array('DESCRIPTION')
		);

		return parent::__construct();
	}
	
	function format($col, $val, $row, $orow)
	{
		return $val;
	}
}

$datagrid = new _DATAGRID();