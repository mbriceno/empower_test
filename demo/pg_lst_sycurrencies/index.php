<?php if (! SYS) die;

class _DATAGRID extends DATAGRID
{
	function __construct()
	{
		global $company, $lang;

        $this->_fields = array(
            array('SYCURRENCY'),
            array('Description'),
        );
        
        if (! $_REQUEST['suggest']) {
            $this->_fields = array_merge($this->_fields, 
                array(
                    array(
                        'lbl' => text('Code'),
                        'field' => 'NumCode',
                        'size' => '30',
                        'maxlength' => 3,
                        'editable' => true,
                        'required' => true,
                        'align' => 'center',
                    ),
                    array(
                        'lbl' => text('Symbol'),
                        'field' => 'Symbol',
                        'size' => '30',
                        'maxlength' => 3,
                        'editable' => true,
                        'required' => true,
                        'align' => 'center',
                    )
                )
            );
        }

        $this->_fields = array_merge($this->_fields, 
            array(
                array('DATE'),
                array(
                    'lbl' => text('Exchange') .' '. text('Rate'),
                    'field' => 'ExchageRate',
                    'size' => '30',
                    'align' => 'center',
                ),
                array('SYUSER'),
                array('TIMESTAMP'),
                array('INFO'),
            )
        );

        $this->_leftJoins[] = array(
            CURRENCY::TBL_LANG .' T',
            'C.Currency = T.Currency AND T.Language = ?'
        );
        $this->_leftJoins[] = array(
            CURRENCY::TBL_EXCH .' EX',
            'C.Currency = C.Currency = EX.Currency AND EX.Company = ?'
        );

        $this->_params[] = $lang;
        $this->_params[] = $company;

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
                $this->_where  .= 'AND ' . implode(' AND ', $where);
        }

        if ($_REQUEST['q'])
        {
            $this->_where = 'C.Currency LIKE ? OR Text LIKE ? OR NumCode LIKE ?';
            $q = '%'.$_REQUEST['q'].'%';
            $this->_params = array_merge($this->_params, array($q, $q, $q));
        }
        elseif ($_REQUEST['suggest'])
        {
            $this->_where = 'C.Company = ? AND ExchageRate IS NOT NULL';
            $this->_params[] = $company;
            $this->_table = CURRENCY::TBL_COMP;
        }

		return parent::__construct();
    }

    function format($col, $val, $row, $orow)
	{
        global $_company;

        if ($_REQUEST['suggest'] && $col == 'Currency')
            return '<a href="#" onclick="suggestSetValueAndLabel(\''.$_REQUEST['suggest'].'\', \''.$row['fullname'].'\', \''.$val.'\', \''.$_REQUEST['nextfield'].'\', \''.$_REQUEST['listid'].'\');" >'.$val.'</a>';
        return
            $val;
    }
}

$datagrid = new _DATAGRID();