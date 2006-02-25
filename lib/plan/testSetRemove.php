<?php
/** 
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * @version $Id: testSetRemove.php,v 1.10 2006/02/25 21:48:26 schlundus Exp $ 
 * 
 * Remove Test Cases from Test Case Suite 
 * 
 * @author 20051001 - fm - sql refactoring
 * @author 20050915 - fm - refactoring
 * 20051112 - scs - removed undefined index warning, added escaping of comp and
 * 					cat names
 * 20051126 - scs - added test_case_removed_part2
 */         
require('../../config.inc.php');
require("../functions/common.php");
require_once("plan.inc.php");
testlinkInitPage($db);

// for genTC_info()
define('THIS_COMP',0);
define('THIS_CAT',0);
define('ALL_CAT',0);
define('ALL_TC',0);


$id = isset($_GET['data']) ? $_GET['data'] : null;
$level = isset($_GET['level']) ? $_GET['level'] : null;

$resultString = null;
$arrData = array();

/** process update if available */
if(isset($_POST['deleteTC']))
{
	$newArray = hash2array($_POST,false);

	$i = 1; //Start the counter at 3 because the first three variable is a submit box
	while ($i < count($newArray))
	{
		$tcID = $newArray[$i]; //Then the first value is the ID
		if($newArray[$i + 1] == 'break')
		{
			//do nothing
			$i = $i + 2;
		}
		else
		{
			$sqlMGT = "SELECT mgttcid,title FROM testcase WHERE id=" . $tcID;
			$resultMGT = $db->exec_query($sqlMGT);
			$mgtID = $db->fetch_array($resultMGT);

			//Delete the test case as well as its results and bugs
			$sqlTCDel = "DELETE FROM testcase WHERE id=" . $tcID;
			$sqlRESDel = "DELETE FROM results WHERE tcid=" . $tcID;
			$sqlBUGDel = "DELETE FROM bugs WHERE tcid=" . $tcID;
			$result = $db->exec_query($sqlTCDel);
			$result = $db->exec_query($sqlRESDel);
			$result = $db->exec_query($sqlBUGDel);

			$resultString .= lang_get("test_case_removed_part1") . " <b>". 
							 $mgtID['mgttcid'] . "</b>: " . 
			                 htmlspecialchars($mgtID['title']) . " " .
							 lang_get("test_case_removed_part2"). " <br />";
			$i = $i + 3;
		}
	}

}
elseif(isset($_POST['deletecomponent']))
{
	// 20051001 - fm
	$sqlComName = " SELECT name AS comp_name" .
	              " FROM component COMP, mgtcomponent MGTCOMP " .
	              " WHERE MGTCOMP.id = COMP.mgtcompid " .
	              " AND COMP.id=" . $id;
	$comResult = $db->exec_query($sqlComName);
	$comRow = $db->fetch_array($comResult);

	del_component_deep($db,$id);

	$resultString = "<b>". lang_get("component_removed_part1") ."</b> " . 
	                htmlspecialchars($comRow['comp_name']) ." ". lang_get("component_removed_part2");
	
}
elseif(isset($_POST['deletecategory'])) 
{
	$sql = " SELECT name " .
			" FROM mgtcategory MGTCAT, category CAT" .
			" WHERE MGTCAT.id = CAT.mgtcatid " .
			" AND CAT.id=" . $id;
	$result = $db->exec_query($sql);
	$myrow = $db->fetch_array($result);
	$cat_name = $myrow['name'];
	
	// 20051001 - fm
	del_category_deep($db,$id);
	$resultString =  "<b>". lang_get("category_removed_part1") . 
					" </b> " . htmlspecialchars($cat_name) . " ". 
					lang_get("category_removed_part2");
}

// ---------------------------------------------------------------------------------------
// grab data for table
// 20050915 - fm - refactoring
//
if($level == 'component')
{
	$arrData = genTC_info($id,ALL_CAT,ALL_TC);
}
else if ($level == 'category')
{
	$arrData = genTC_info(THIS_COMP,$id,ALL_TC);
}
else if($level == 'tc')
{
	$arrData = genTC_info(THIS_COMP,THIS_CAT,$id);
} 
else
{
	// show instructions
	redirect( $_SESSION['basehref'] . $g_rpath['instructions'].'/testSetRemove.html');
}
// ---------------------------------------------------------------------------------------


$smarty = new TLSmarty;
$smarty->assign('level', $level);
$smarty->assign('id', $id);
$smarty->assign('testPlanName', $_SESSION['testPlanName']);
$smarty->assign('resultString', $resultString);
$smarty->assign('arrData', $arrData);
$smarty->display('planRemoveTC.tpl');

/*
20050915 - fm - use the id that are != 0
*/
function genTC_info(&$db, $compID, $catID, $tcID)
{
	$sql = " SELECT mgtcategory.name AS cat_name, mgtcomponent.name AS comp_name, " .
	       " component.id AS comp_id, " .
	       " category.id AS cat_id, " .
	       " testcase.id, " . 
	       " title, summary,steps,exresult, " .
	       " active, version, mgttcid,TCorder " .
	       " FROM testcase,mgtcomponent,mgtcategory,component,category " .
	       " WHERE mgtcategory.id = category.mgtcatid " .
	       " AND mgtcomponent.id = component.mgtcompid " .
	       " AND component.id = category.compid " .
	       " AND category.id=testcase.catid ";

	if($compID)
	{
		$sql .= " AND component.id=" . $compID; 
	}	       
	if($catID)
	{
		$sql .= " AND category.id=" . $catID; 
	}	       
	if($tcID)
	{
		$sql .= " AND testcase.id=" . $tcID; 
	}	       
	$sql .= " ORDER BY TCorder";

	$tc_info = null;
	$result = $db->exec_query($sql);
	while($row = $db->fetch_array($result))
	{
		$tc_info[] = array(	'id' => $row['id'], 
					'name' => $row['title'], 
					'container' => $row['comp_name'] . '/' . $row['cat_name'],
					'comp_id' => $row['comp_id'],
					'cat_id' => $row['cat_id'] );
	}
	return $tc_info;
}
?>