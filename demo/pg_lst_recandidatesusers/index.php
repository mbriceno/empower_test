<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	function __construct()
	{
		global $company;

        $this->_filters = array(
            array(
                'lbl' => text('Bloqueado'),
                'type' => 'droplist',
                'values' => $val_Checked,
            ),
            array('RECANDIDATE', array(
                'secondary' => true
            )),
            array('INACTIVEDL', array(
                'secondary' => true
            )),
        );

        $this->_fields = array(
            array('RECANDIDATE', array(
                'readonly' => true, 
                'type' => 'text'
            )),
            array('HREMPLOYEENAME', array(
                'fieldQuery' => 'CONCAT(C.FirstName, " ", C.LastName)'
            )),
            array('EMAIL'),
            array(
                'lbl' => text('Blocked'),
                'field' => 'Blocked',
                'fieldQuery' => 'C.Blocked',
                'align' => 'center',
                'type' => 'checkbox',
                'editable' => true,
                'hideingrid' =>  true,
            ),
            array(
                'lbl' => text('Unlock'),
                'field' => 'Unlock',
                'fieldQuery' => 'C.Unlock',
                'align' => 'center',
                'type' => 'checkbox',
                'editable' => false,
            ),
            array(
                'lbl' => text('Password'),
                'field' => 'Password',
                'align' => 'center',
                'editable' => true,
                'maxlength' => 150,
                'type' => 'password',
                'hideingrid' => true,
            ),
            array('INACTIVE'),
            array('SYUSER'),
            array('TIMESTAMP'),
            array('INFO'),
        );

        $this->_where = 'Company = ?';
		$this->_params = array($company);

        if ($_REQUEST['action']) {
            if ($_REQUEST['fltrs'][1]) {
                $where[]        = ($_REQUEST['fltrs'][1] == 'X') ? 'Blocked' : 'Blocked = 0';
            }
            if ($_REQUEST['fltrs'][2]) {
                $where[]        = ($_REQUEST['fltrs'][2] == 'X') ? 'Inactive' : 'Inactive = 0';
            }

            if ($where && is_array($where))
                $this->_where .= implode(' AND ', $where);
        }
		return parent::__construct();
    }

    function format($col, $val, $row, $orow)
	{
		global $_company;

        if($col == 'Unlock') {
            $icon = ($orow['Blocked']) ? '<img width="20" height="20" src="img/unlock.png" />' : '';
            return '<a href="#" onclick="loadElem(\'treePage\',\'?ajax=1&pg=lst_recandidatesusers&container=treePage&opcs=U&toggleLock='.$orow['Candidate'].'\')">'.$icon.'</a>';
        }
        elseif($col == 'Regenerate')
        {
            $icon = '<img width="20" height="20" src="img/regenerate.png" />';
            return '<a href="#" onclick="loadElem(\'treePage\',\'?ajax=1&pg=lst_recandidatesusers&container=treePage&opcs=U&regenerate='.$orow['Candidate'].'&type='.$_REQUEST['type'].'\')">'.$icon.'</a>';
        }
        elseif($col == 'EmailToken')
        {
            $icon = '<img width="20" height="20" src="img/envelope.png" />';
            return '<a href="#" onclick="loadElem(\'content\',\'?ajax=1&pg=lst_recandidatesusers&container=content&opcs=U&emailtoken='.$orow['Candidate'].'&type='.$_REQUEST['type'].'\')">'.$icon.'</a>';
        }
        elseif($col == 'ResetToken')
        {
            $icon = '<img width="20" height="20" src="img/broom.png" />';
            return '<a href="#" onclick="loadElem(\'content\',\'?ajax=1&pg=lst_recandidatesusers&container=content&opcs=U&resettoken='.$orow['Candidate'].'&type='.$_REQUEST['type'].'\')">'.$icon.'</a>';
        }

            return $val;
	}
}

$datagrid = new _DATAGRID();