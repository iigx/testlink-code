<?php
/** TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * 
 * @filesource $RCSfile: testsuite.class.php,v $
 * @version $Revision: 1.18 $
 * @modified $Date: 2006/10/09 10:28:50 $
 * @author franciscom
 *
 * 20060805 - franciscom - changes in viewer_edit_new()
 *                         keywords related functions
 * 
 * 20060425 - franciscom - changes in show() following Andreas Morsing advice (schlundus)
 *
 */

class testsuite
{
	var $db;
	var $tree_manager;
	var $node_types_descr_id;
	var $node_types_id_descr;
	var $my_node_type;

function testsuite(&$db)
{
	$this->db = &$db;	
	
	$this->tree_manager =  new tree($this->db);
	$this->node_types_descr_id=$this->tree_manager->get_available_node_types();
	$this->node_types_id_descr=array_flip($this->node_types_descr_id);
	$this->my_node_type=$this->node_types_descr_id['testsuite'];
}

// 20060309 - franciscom
// returns hash with:	$ret['status_ok'] -> 0/1
//                    $ret['msg']
//                    $ret['id']        -> when status_ok=1, id of the new element
//
//                  
function create($parent_id,$name,$details,
                $check_duplicate_name=0,
                $action_on_duplicate_name='allow_repeat')
{
  
  // 20060309 - franciscom
  $ret['status_ok']=0;
  $ret['msg']='ok';
  $ret['id']=-1;
  
    
	$prefix_name_for_copy = config_get('prefix_name_for_copy');
	
	$name = trim($name);
	$ret = array('status_ok' => 1, 'id' => 0, 'msg' => 'ok');
	if ($check_duplicate_name)
	{
		
    $sql = " SELECT count(*) AS qty FROM testsuites,nodes_hierarchy 
		         WHERE nodes_hierarchy.name = '" . $this->db->prepare_string($name) . "'" . 
		       " AND testsuites.id=nodes_hierarchy.id
		         AND node_type_id = {$this->my_node_type} 
		         AND nodes_hierarchy.parent_id={$parent_id} "; 
		
		$result = $this->db->exec_query($sql);
		$myrow = $this->db->fetch_array($result);
		
		if( $myrow['qty'])
		{
			if ($action_on_duplicate_name == 'block')
			{
				$ret['status_ok'] = 0;
				$ret['msg'] = lang_get('component_name_already_exists');	
			} 
			else
			{
				$ret['status_ok'] = 1;      
				if ($action_on_duplicate_name == 'generate_new')
				{ 
					$ret['status_ok'] = 1;      
					$name = config_get('prefix_name_for_copy') . " " . $name ;      
				}
			}
		}       
	}
	
	if ($ret['status_ok'])
	{
		// get a new id
		$tsuite_id = $this->tree_manager->new_node($parent_id,$this->my_node_type,$name);
		$sql = "INSERT INTO testsuites (id,details) " .
				"VALUES ({$tsuite_id},'" . $this->db->prepare_string($details) . "')";
		             
		$result = $this->db->exec_query($sql);
		if ($result)
		{
			$ret['id'] = $tsuite_id;
		}
	}
	return $ret;
}


function update($id, $name, $details)
{
	//TODO - check for existent name
	$sql = " UPDATE testsuites
	         SET details = '" . $this->db->prepare_string($details) . "'" .
	       " WHERE id = {$id}";
	$result = $this->db->exec_query($sql);
  
	if ($result)
	{
		$sql = " UPDATE nodes_hierarchy SET name='" . 
				$this->db->prepare_string($name) . "' WHERE id= {$id}";
		$result = $this->db->exec_query($sql);
	}

	
	$ret['msg']='ok';
	if (!$result)
	{
		$ret['msg'] = $this->db->error_msg();
	}
	return $ret;
}


function get_by_name($name)
{
	$sql = " SELECT testsuites.*, nodes_hierarchy.name " .
		   " FROM testsuites, nodes_hierarchy " .
		   " WHERE nodes_hierarchy.name = '" . 
			$this->db->prepare_string($name) . "'";
	
	$recordset = $this->db->get_recordset($sql);
	return $recordset;
}

/*
get info for one test suite
*/
function get_by_id($id)
{
	$sql = " SELECT testsuites.*, nodes_hierarchy.name,nodes_hierarchy.node_type_id 
	         FROM testsuites,nodes_hierarchy 
	         WHERE testsuites.id = nodes_hierarchy.id
	         AND testsuites.id = {$id}";
  $recordset = $this->db->get_recordset($sql);
  return($recordset ? $recordset[0] : null);
}


/*
get array of info for every test suite
without any kind of filter.
Every array element contains an assoc array with test suite info

*/
function get_all()
{
	$sql = " SELECT testsuites.*, nodes_hierarchy.name
	         FROM testsuites,nodes_hierarchy
	         WHERE testsuites.id = nodes_hierarchy.id";
  $recordset = $this->db->get_recordset($sql);
  return($recordset);
}


/**
 * show()
 *
 * @param type $smarty [reference]
 * @param type $id 
 * @param type $sqlResult [default = '']
 * @param type $action [default = 'update']
 * @param type $modded_item_id [default = 0]
 * @return type documentation
 *
 **/
function show(&$smarty,$id, $sqlResult = '', $action = 'update',$modded_item_id = 0)
{
	$smarty->assign('modify_tc_rights', has_rights($this->db,"mgt_modify_tc"));

	if($sqlResult)
	{ 
		$smarty->assign('sqlResult', $sqlResult);
		$smarty->assign('sqlAction', $action);
	}
	
	$item = $this->get_by_id($id);
	$modded_item = $item;
	if ($modded_item_id)
	{
		$modded_item = $this->get_by_id($modded_item_id);
	}
  
	$keywords_map = $this->get_keywords_map($id,' ORDER BY KEYWORD ASC ');
	$smarty->assign('keywords_map',$keywords_map);
	$smarty->assign('moddedItem',$modded_item);
	$smarty->assign('level', 'testsuite');
	$smarty->assign('container_data', $item);
	$smarty->assign('sqlResult',$sqlResult);
	$smarty->display('containerView.tpl');
}


// 20060805 - franciscom - added new argument smarty reference
function viewer_edit_new(&$smarty,$amy_keys, $oFCK, $action, $parent_id, $id=null)
{
	$a_tpl = array( 'edit_testsuite' => 'containerEdit.tpl',
					        'new_testsuite'  => 'containerNew.tpl');
	
	$the_tpl = $a_tpl[$action];
	$component_name='';
	$smarty->assign('sqlResult', null);
	$smarty->assign('containerID',$parent_id);	 
	
	$the_data = null;
	$name = '';
	if ($action == 'edit_testsuite')
	{
		$the_data = $this->get_by_id($id);
		$name=$the_data['name'];
		$smarty->assign('containerID',$id);	
	}
	
	// fckeditor 
	foreach ($amy_keys as $key)
	{
		// Warning:
		// the data assignment will work while the keys in $the_data are identical
		// to the keys used on $oFCK.
		$of = &$oFCK[$key];
		$of->Value = isset($the_data[$key]) ? $the_data[$key] : null;
		$smarty->assign($key, $of->CreateHTML());
	}
	
	$smarty->assign('level', 'testsuite');
	$smarty->assign('name',$name);
	$smarty->assign('container_data',$the_data);
	
	$smarty->display($the_tpl);
}

function copy_to($id, $parent_id, $user_id,
                 $check_duplicate_name = 0,
				 $action_on_duplicate_name = 'allow_repeat',
				 $copyKeywords = 0
				 )
{
	$tcase_mgr = new testcase($this->db);
	
	$tsuite_info = $this->get_by_id($id);
	$ret = $this->create($parent_id,$tsuite_info['name'],$tsuite_info['details'],
						$check_duplicate_name,$action_on_duplicate_name);
	
	$new_tsuite_id = $ret['id'];
	
	$subtree = $this->tree_manager->get_subtree($id,array('testplan' => 'exclude_me'),
													array('testcase' => 'exclude_my_children'));
	
	if (!is_null($subtree))
	{
		$the_parent_id = $new_tsuite_id;	
		foreach($subtree as $the_key => $elem)
		{
			if( $elem['parent_id'] == $id )
				$the_parent_id = $new_tsuite_id;	
			
			switch ($elem['node_type_id'])
			{
				case $this->node_types_descr_id['testcase']:
					$tcase_mgr->copy_to($elem['id'],$the_parent_id,$user_id,$copyKeywords);
					break;
				case $this->node_types_descr_id['testsuite']:
					$tsuite_info = $this->get_by_id($elem['id']);
					$ret = $this->create($the_parent_id,$tsuite_info['name'],$tsuite_info['details']);      
					$the_parent_id = $ret['id'];
					break;
			}
		}
	}
}

// get all test cases in the test suite and all children test suites
// no info about tcversions is returned
function get_testcases_deep($id,$bIdsOnly = false)
{
	$subtree = $this->tree_manager->get_subtree($id,
												array('testplan' => 'exclude_me'),
	             								array('testcase' => 'exclude_my_children'));
	$testcases = null;
	if(!is_null($subtree))
	{
		$testcases = array();
		$tcNodeType = $this->node_types_descr_id['testcase'];
		foreach ($subtree as $the_key => $elem)
		{
			if($elem['node_type_id'] == $tcNodeType)
			{
				if ($bIdsOnly)
					$testcases[] = $elem['id'];
				else
					$testcases[]= $elem;
			}
		}
	}
	
	return $testcases; 
}

function delete_deep($id)
{
  $tcase_mgr = New testcase($this->db);

	$tsuite_info = $this->get_by_id($id);
  $subtree = $this->tree_manager->get_subtree($id,array('testplan' => 'exclude_me', 'testcase' => 'exclude_me'),
                                                  array('testcase' => 'exclude_my_children'));
	
	// add me, to delete me 
	$subtree[]=array('id' => $id);
	$testcases = $this->get_testcases_deep($id);

  if (!is_null($subtree))
	{
    // -------------------------------------------------------------------
		$node_list = array();
		$node_list[]=$id;
	  foreach($subtree as $the_key => $elem)
	  {
      $node_list[]= $elem['id'];
	  }
    $tsuites_id_list=implode(",",$node_list);    
	
	  $sql = "DELETE FROM testsuites WHERE id IN ({$tsuites_id_list})";
		$result = $this->db->exec_query($sql);
    // -------------------------------------------------------------------

    // -------------------------------------------------------------------
    if (!is_null($testcases))
	  {
	    foreach($testcases as $the_key => $elem)
	    {
        $tcase_mgr->delete($elem['id']);
	    }
	  }  
    // -------------------------------------------------------------------

    $this->tree_manager->delete_subtree($id);

	}
} // end function


function getKeywords($id,$kw_id = null)
{
	$sql = "SELECT keyword_id,keywords.keyword, keywords.keyword notes
	        FROM object_keywords,keywords 
	        WHERE keyword_id = keywords.id AND fk_id = {$id}";
	if (!is_null($kw_id))
	{
		$sql .= " AND keyword_id = {$kw_id}";
	}	
	$map_keywords = $this->db->fetchRowsIntoMap($sql,'keyword_id');
	
	return($map_keywords);
} 

function get_keywords_map($id,$order_by_clause='')
{
	$sql = "SELECT keyword_id,keywords.keyword 
	        FROM object_keywords,keywords 
	        WHERE keyword_id = keywords.id ";
	if (is_array($id))
		$sql .= " AND fk_id IN (".implode(",",$id).") ";
	else
		$sql .= " AND fk_id = {$id} ";
		
	$sql .= $order_by_clause;

	$map_keywords = $this->db->fetchColumnsIntoMap($sql,'keyword_id','keyword');
	return($map_keywords);
} 



function addKeyword($id,$kw_id)
{
	$kw = $this->getKeywords($id,$kw_id);
	if (sizeof($kw))
	{
		return 1;
	}	
	$sql = " INSERT INTO object_keywords (fk_id,fk_table,keyword_id) " .
		     " VALUES ($id,'nodes_hierarchy',$kw_id)";

	return ($this->db->exec_query($sql) ? 1 : 0);
}

function addKeywords($id,$kw_ids)
{
	$status = 1;
	$num_kws = sizeof($kw_ids);
	for($idx = 0; $idx < $num_kws; $idx++)
	{
		$status = $status && $this->addKeyword($id,$kw_ids[$idx]);
	}
	return($status);
}


function deleteKeywords($id,$kw_id = null)
{
	$sql = " DELETE FROM object_keywords WHERE fk_id = {$id} ";
	if (!is_null($kw_id))
	{
		$sql .= " AND keyword_id = {$kw_id}";
	}	
	return($this->db->exec_query($sql));
}

function exportTestSuiteDataToXML($container_id,$optExport = array())
{
	$xmlTC = null;
	$bRecursive = @$optExport['RECURSIVE'];
	if ($bRecursive)
	{
		$tsuiteData = $this->get_by_id($container_id);
		$kwXML = null;
		if (@$optExport['KEYWORDS'])
		{
			$kwMap = $this->getKeywords($container_id);
			if ($kwMap)
				$kwXML = exportKeywordDataToXML($kwMap,true);
		}
		$xmlTC = "<testsuite name=\"".htmlspecialchars($tsuiteData['name'])."\"><details><![CDATA[\n{$tsuiteData['details']}\n]]>{$kwXML}</details>";
	}
	else
		$xmlTC = "<testcases>";

	$test_spec = $this->tree_manager->get_subtree($container_id,array('testplan'=>'exclude me'),
												 array('testcase'=>'exclude my children'),null,null,true);
	$childNodes = @$test_spec['childNodes'];
	for($i = 0;$i < sizeof($childNodes);$i++)
	{
		$cNode = $childNodes[$i];
		$nTable = $cNode['node_table'];
		if ($bRecursive && $nTable == 'testsuites')
		{
			$ts = new testsuite($this->db);
			$xmlTC .= $ts->exportTestSuiteDataToXML($cNode['id'],$optExport);
		}
		else if ($nTable == 'testcases')
		{
			$tc = new testcase($this->db);
			$xmlTC .= $tc->exportTestCaseDataToXML($cNode['id'],TC_LATEST_VERSION,true,$optExport);
		}
	}
	if ($bRecursive)
		$xmlTC .= "</testsuite>";
	else
		$xmlTC .= "</testcases>";
	return $xmlTC;
}


} // end class

?>
