<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * This script is distributed under the GNU General Public License 2 or later. 
 *
 * Filename $RCSfile: usersview.php,v $
 *
 * @version $Revision: 1.3 $
 * @modified $Date: 2006/02/25 21:48:27 $
 *
 * This page shows all users
 *
 * 20053112 - scs - cleanup, due to removing bulk update of users
 * 20060103 - scs - ADOdb changes
 * 20060107 - fm  - refactoring init_args()
 *
**/
include('../../config.inc.php');
require_once("users.inc.php");
testlinkInitPage($db);

$sqlResult = null;
$action = null;
$update_title_bar = 0;
$reload = 0;

$bDelete = isset($_GET['delete']) ? $_GET['delete'] : 0;
$userID = isset($_GET['user']) ? $_GET['user'] : 0;
$sessionUserID = $_SESSION['userID'];

if ($bDelete && $userID)
{
	$sqlResult = userDelete($db,$userID);
	
	//if the users deletes itself then logout
	if ($userID == $sessionUserID)
	{
		header("Location: ../../logout.php");
		exit();
	}
	$action = "deleted";
}
	
$users = getAllUsers($db);
$roles = getAllRoles($db);

$smarty = new TLSmarty();
$smarty->assign('optRoles',$roles);
$smarty->assign('update_title_bar',$update_title_bar);
$smarty->assign('reload',$reload);
$smarty->assign('users',$users);
$smarty->assign('result',$sqlResult);
$smarty->assign('action',$action);
$smarty->display($g_tpl['usersview']);
?>