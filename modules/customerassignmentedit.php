<?php

/*
 * LMS version 1.11-git
 *
 *  (C) Copyright 2001-2012 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

// get customer name and check privileges using customersview
$customer = $DB->GetRow('SELECT a.customerid AS id, c.divisionid, '
    .$DB->Concat('c.lastname',"' '",'c.name').' AS name
    FROM assignments a
    JOIN customersview c ON (c.id = a.customerid)
    WHERE a.id = ?', array($_GET['id']));

if(!$customer)
{
	$SESSION->redirect('?'.$SESSION->get('backto'));
}

if($_GET['action'] == 'suspend')
{
    $LMS->SuspendAssignment($_GET['id'], $_GET['suspend']);
    $SESSION->redirect('?'.$SESSION->get('backto'));
}

if(isset($_POST['assignment']))
{
	$a = $_POST['assignment'];

	foreach($a as $key => $val)
		if(!is_array($val))
			$a[$key] = trim($val);

	$a['id'] = $_GET['id'];
	$a['customerid'] = $customer['id'];
	$a['liabilityid'] = isset($_GET['lid']) ? $_GET['lid'] : 0;

	$period = sprintf('%d',$a['period']);

	switch($period)
	{
		case DAILY:
			$at = 0;
		break;

		case WEEKLY:
			$at = sprintf('%d',$a['at']);

			if(chkconfig($CONFIG['phpui']['use_current_payday']) && $at==0)
			{
				$at = strftime('%u', time());
			}

			if($at < 1 || $at > 7)
				$error['at'] = trans('Incorrect day of week (1-7)!');
		break;

		case MONTHLY:
			$at = sprintf('%d',$a['at']);

			if(isset($CONFIG['phpui']['use_current_payday']) && chkconfig($CONFIG['phpui']['use_current_payday']) && $at==0)
			{
				$at = date('j', time());
			}
			elseif(!chkconfig($CONFIG['phpui']['use_current_payday'])
				 && $CONFIG['phpui']['default_monthly_payday']>0 && $at==0)
			{
				$at = $CONFIG['phpui']['default_monthly_payday'];
			}

			$a['at'] = $at;

			if($at > 28 || $at < 1)
				$error['at'] = trans('Incorrect day of month (1-28)!');
		break;

		case QUARTERLY:
			if(chkconfig($CONFIG['phpui']['use_current_payday']) && !$a['at'])
			{
				$d = date('j', time());
				$m = date('n', time());
				$a['at'] = $d.'/'.$m;
			}
			elseif(!preg_match('/^[0-9]{2}\/[0-9]{2}$/', $a['at']))
			{
				$error['at'] = trans('Incorrect date format! Enter date in DD/MM format!');
			}
			else
			{
				list($d,$m) = explode('/',$a['at']);
			}

			if(!$error)
			{
				if($d>30 || $d<1 || ($d>28 && $m==2))
					$error['at'] = trans('This month doesn\'t contain specified number of days');
				if($m>3 || $m<1)
					$error['at'] = trans('Incorrect month number (max.3)!');

				$at = ($m-1) * 100 + $d;
			}
		break;

		case HALFYEARLY:
			if(!preg_match('/^[0-9]{2}\/[0-9]{2}$/', $a['at']) && $a['at'])
			{
				$error['at'] = trans('Incorrect date format! Enter date in DD/MM format!');
			}
			elseif(chkconfig($CONFIG['phpui']['use_current_payday']) && !$a['at'])
			{
				$d = date('j', time());
				$m = date('n', time());
				$a['at'] = $d.'/'.$m;
			}
			else
			{
				list($d,$m) = explode('/',$a['at']);
			}

			if(!$error)
			{
				if($d>30 || $d<1 || ($d>28 && $m==2))
					$error['at'] = trans('This month doesn\'t contain specified number of days');
				if($m>6 || $m<1)
					$error['at'] = trans('Incorrect month number (max.6)!');

				$at = ($m-1) * 100 + $d;
			}
		break;

		case YEARLY:
			if(chkconfig($CONFIG['phpui']['use_current_payday']) && !$a['at'])
			{
				$d = date('j', time());
				$m = date('n', time());
				$a['at'] = $d.'/'.$m;
			}
			elseif(!preg_match('/^[0-9]{2}\/[0-9]{2}$/', $a['at']))
			{
				$error['at'] = trans('Incorrect date format! Enter date in DD/MM format!');
			}
			else
			{
				list($d,$m) = explode('/',$a['at']);
			}

			if(!$error)
			{
				if($d>30 || $d<1 || ($d>28 && $m==2))
					$error['at'] = trans('This month doesn\'t contain specified number of days');
				if($m>12 || $m<1)
					$error['at'] = trans('Incorrect month number');

				$ttime = mktime(12, 0, 0, $m, $d, 1990);
				$at = date('z',$ttime) + 1;
			}
		break;

		default: // DISPOSABLE
            $period = DISPOSABLE;

	        if(preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/', $a['at']))
			{
				list($y, $m, $d) = explode('/', $a['at']);
				if(checkdate($m, $d, $y))
				{
					$at = mktime(0, 0, 0, $m, $d, $y);
					if ($at < mktime(0, 0, 0) && !$a['atwarning']) {
						$a['atwarning'] = TRUE;
						$error['at'] = trans('Incorrect date!');
					}
				}
				else
					$error['at'] = trans('Incorrect date format! Enter date in YYYY/MM/DD format!');
			}
			else
				$error['at'] = trans('Incorrect date format! Enter date in YYYY/MM/DD format!');
		break;
	}

	if($a['datefrom'] == '')
		$from = 0;
	elseif(preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/', $a['datefrom']))
	{
		list($y, $m, $d) = explode('/', $a['datefrom']);
		if(checkdate($m, $d, $y))
			$from = mktime(0, 0, 0, $m, $d, $y);
		else
			$error['datefrom'] = trans('Incorrect charging start time!');
	}
	else
		$error['datefrom'] = trans('Incorrect charging start time!');

	if ($a['dateto'] == '')
		$to = 0;
	elseif (preg_match('/^[0-9]{4}\/[0-9]{2}\/[0-9]{2}$/', $a['dateto']))
	{
		list($y, $m, $d) = explode('/', $a['dateto']);
		if(checkdate($m, $d, $y))
			$to = mktime(23, 59, 59, $m, $d, $y);
		else
			$error['dateto'] = trans('Incorrect charging end time!');
	}
	else
		$error['dateto'] = trans('Incorrect charging end time!');

	if ($to < $from && $to != 0 && $from != 0)
		$error['dateto'] = trans('Incorrect date range!');

	$a['discount'] = str_replace(',', '.', $a['discount']);
	$a['pdiscount'] = 0.0;
	$a['vdiscount'] = 0.0;
	if (preg_match('/^[0-9]+(\.[0-9]+)*$/', $a['discount']))
	{
		$a['pdiscount'] = ($a['discount_type'] == DISCOUNT_PERCENTAGE ? floatval($a['discount']) : 0);
		$a['vdiscount'] = ($a['discount_type'] == DISCOUNT_AMOUNT ? floatval($a['discount']) : 0);
	}
	if ($a['pdiscount'] < 0 || $a['pdiscount'] > 99.99)
		$error['discount'] = trans('Wrong discount value!');

	if ($a['tariffid'] == -1)
	{
		unset($error['at']);
		$at = 0;
	}
	elseif (!$a['tariffid'])
	{
		if ($a['name'] == '')
			$error['name'] = trans('Liability name is required!');
		if (!$a['value'])
			$error['value'] = trans('Liability value is required!');
		elseif (!preg_match('/^[-]?[0-9.,]+$/', $a['value']))
			$error['value'] = trans('Incorrect value!');
	}

	if(!$error) 
	{
		$DB->BeginTrans();

		if($a['liabilityid'])
		{
				if ($a['tariffid']) {
					$DB->Execute('DELETE FROM liabilities WHERE id=?', array($a['liabilityid']));
					$a['liabilityid'] = 0;
				}
				else
					$DB->Execute('UPDATE liabilities SET value=?, name=?, taxid=?, prodid=? WHERE id=?',
						array(str_replace(',','.',$a['value']),
							$a['name'],
							intval($a['taxid']),
							$a['prodid'],
							$a['liabilityid']
						));
		}
		else if (!$a['tariffid']) {
			$DB->Execute('INSERT INTO liabilities (name, value, taxid, prodid) 
				VALUES (?, ?, ?, ?)',
				array($a['name'],
					$a['value'],
					intval($a['taxid']),
					$a['prodid']
				));

			$a['liabilityid'] = $DB->GetLastInsertID('liabilities'); 
		}

		if ($a['tariffid'] == -1) {
			$a['tariffid'] = 0;
			$a['discount'] = 0;
			$a['pdiscount'] = 0;
			$a['vdiscount'] = 0;
			unset($a['invoice']);
			unset($a['settlement']);
		}

		$DB->Execute('UPDATE assignments SET tariffid=?, customerid=?, period=?, at=?,
			invoice=?, settlement=?, datefrom=?, dateto=?, pdiscount=?, vdiscount=?,
			liabilityid=?, numberplanid=?, paytype=?
			WHERE id=?',
				array(intval($a['tariffid']),
					$customer['id'],
					$period,
					$at,
					isset($a['invoice']) ? 1 : 0,
					isset($a['settlement']) ? 1 : 0,
					$from,
					$to,
					str_replace(',', '.', $a['pdiscount']),
					str_replace(',', '.', $a['vdiscount']),
					$a['liabilityid'],
					!empty($a['numberplanid']) ? $a['numberplanid'] : NULL,
					!empty($a['paytype']) ? $a['paytype'] : NULL,
					$a['id'],
				));

		$DB->Execute('DELETE FROM nodeassignments WHERE assignmentid=?', array($a['id']));

		if (isset($a['nodes']) && sizeof($a['nodes']))
		{
			foreach($a['nodes'] as $nodeid)
				$DB->Execute('INSERT INTO nodeassignments (nodeid, assignmentid) VALUES (?,?)',
					array($nodeid, $a['id']));
		}

		$DB->CommitTrans();

		$SESSION->redirect('?'.$SESSION->get('backto'));
	}

	$SMARTY->assign('error', $error);
}
else
{
	$a = $DB->GetRow('SELECT a.id AS id, a.customerid, a.tariffid, a.period,
				a.at, a.datefrom, a.dateto, a.numberplanid, a.paytype,
				a.invoice, a.settlement, a.pdiscount, a.vdiscount, a.liabilityid, 
				(CASE liabilityid WHEN 0 THEN tariffs.name ELSE liabilities.name END) AS name, 
				liabilities.value AS value, liabilities.prodid AS prodid, liabilities.taxid AS taxid
				FROM assignments a
				LEFT JOIN tariffs ON (tariffs.id = a.tariffid)
				LEFT JOIN liabilities ON (liabilities.id = a.liabilityid)
				WHERE a.id = ?', array($_GET['id']));

	$a['pdiscount'] = floatval($a['pdiscount']);
	$a['vdiscount'] = floatval($a['vdiscount']);
	if (!empty($a['pdiscount'])) {
		$a['discount'] = $a['pdiscount'];
		$a['discount_type'] = DISCOUNT_PERCENTAGE;
	}
	elseif (!empty($a['vdiscount'])) {
		$a['discount'] = $a['vdiscount'];
		$a['discount_type'] = DISCOUNT_AMOUNT;
	}

	if($a['dateto']) 
		$a['dateto'] = date('Y/m/d', $a['dateto']);
	if($a['datefrom'])
		$a['datefrom'] = date('Y/m/d', $a['datefrom']);

	switch($a['period'])
	{
		case QUARTERLY:
			$a['at'] = sprintf('%02d/%02d',$a['at']%100,$a['at']/100+1);
			break;
		case HALFYEARLY:
			$a['at'] = sprintf('%02d/%02d',$a['at']%100,$a['at']/100+1);
			break;
		case YEARLY:
			$a['at'] = date('d/m',($a['at']-1)*86400);
			break;
		case DISPOSABLE:
			if($a['at'])
				$a['at'] = date('Y/m/d', $a['at']);
			break;
	}

	if (!$a['tariffid'] && !$a['liabilityid'])
		$a['tariffid'] = -1;

	// nodes assignments
	$a['nodes'] = $DB->GetCol('SELECT nodeid FROM nodeassignments WHERE assignmentid=?', array($a['id']));
}

$expired = isset($_GET['expired']) ? $_GET['expired'] : false;

$layout['pagetitle'] = trans('Liability Edit: $a', '<A href="?m=customerinfo&id='.$customer['id'].'">'.$customer['name'].'</A>');

$SESSION->save('backto', $_SERVER['QUERY_STRING']);

$customernodes = $LMS->GetCustomerNodes($customer['id']);
unset($customernodes['total']);

$SMARTY->assign('customernodes', $customernodes);
$SMARTY->assign('tariffs', $LMS->GetTariffs());
$SMARTY->assign('taxeslist', $LMS->GetTaxes());
$SMARTY->assign('expired', $expired);
$SMARTY->assign('assignment', $a);
$SMARTY->assign('assignments', $LMS->GetCustomerAssignments($customer['id'], $expired));
$SMARTY->assign('numberplanlist', $LMS->GetNumberPlans(DOC_INVOICE, NULL, $customer['divisionid'], false));
$SMARTY->assign('customerinfo', $customer);
$SMARTY->display('customerassignmentsedit.html');

?>
