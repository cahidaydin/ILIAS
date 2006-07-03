<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/

/**
* Class ilObjUserTrackingGUI
*
* @author Stefan Meyer <smeyer@databay.de>
*
* @version $Id$
*
* @ilCtrl_Calls ilLPListOfObjectsGUI: ilLPFilterGUI
*
* @package ilias-tracking
*
*/

include_once './Services/Tracking/classes/class.ilLearningProgressBaseGUI.php';
include_once './Services/Tracking/classes/class.ilLPStatusWrapper.php';
include_once 'Services/Tracking/classes/class.ilLPObjSettings.php';

class ilLPListOfObjectsGUI extends ilLearningProgressBaseGUI
{
	var $details_id = 0;
	var $details_type = '';
	var $details_mode = 0;

	function ilLPListOfObjectsGUI($a_mode,$a_ref_id)
	{
		global $ilUser;

		parent::ilLearningProgressBaseGUI($a_mode,$a_ref_id);

		$this->__initFilterGUI();

		// Set item id for details
		$this->__initDetails((int) $_REQUEST['details_id']);

		$this->item_id = (int) $_REQUEST['item_id'];
		$this->offset = (int) $_GET['offset'];
		$this->ctrl->saveParameter($this,'offset',$this->offset);
		$this->max_count = $ilUser->getPref('hits_per_page');
	}
	/**
	* execute command
	*/
	function &executeCommand()
	{
		global $ilBench;

		$ilBench->start('LearningProgress','1000_LPListOfObjects');

		$this->ctrl->setReturn($this, "");

		switch($this->ctrl->getNextClass())
		{
			case 'illpfiltergui':

				$this->ctrl->forwardCommand($this->filter_gui);
				break;

			default:
				$cmd = $this->__getDefaultCommand();
				$this->$cmd();

		}

		$ilBench->stop('LearningProgress','1000_LPListOfObjects');
		return true;
	}

	function updateUser()
	{
		include_once 'Services/Tracking/classes/class.ilLPMarks.php';

		$marks = new ilLPMarks($this->item_id,$_REQUEST['user_id']);
		$marks->setMark(ilUtil::stripSlashes($_POST['mark']));
		$marks->setComment(ilUtil::stripSlashes($_POST['comment']));
		$marks->setCompleted((bool) $_POST['completed']);
		$marks->update();
		sendInfo($this->lng->txt('trac_update_edit_user'));
		$this->details();
	}

	function editUser()
	{
		// Load template
		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.lp_edit_user.html','Services/Tracking');

		include_once("classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);

		$this->__showObjectDetails($info,$this->item_id);
		$this->__appendLPDetails($info,$this->item_id,(int) $_GET['user_id']);

		// Finally set template variable
		$this->tpl->setVariable("INFO_TABLE",$info->getHTML());

		$this->__showEditUser();
	}

	function __showEditUser()
	{
		global $ilObjDataCache;

		include_once 'Services/Tracking/classes/class.ilLPMarks.php';

		$marks = new ilLPMarks($this->item_id,$_REQUEST['user_id']);

		$this->ctrl->setParameter($this,'user_id',(int) $_GET['user_id']);
		$this->ctrl->setParameter($this,'item_id',(int) $this->item_id);
		$this->ctrl->setParameter($this,'details_id',$this->details_id);
		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));

		$this->tpl->setVariable("TYPE_IMG",ilObjUser::_getPersonalPicturePath((int) $_GET['user_id'],'xxsmall'));
		$this->tpl->setVariable("ALT_IMG",$ilObjDataCache->lookupTitle((int) $_GET['user_id']));
		$this->tpl->setVariable("TXT_LP",$this->lng->txt('trac_learning_progress_tbl_header'));

		$this->tpl->setVariable("COMMENT",ilUtil::prepareFormOutput($marks->getComment(),false));

		$type = $ilObjDataCache->lookupType($this->item_id);
		if($type != 'lm')
		{
			$this->tpl->setVariable("TXT_MARK",$this->lng->txt('trac_mark'));
			$this->tpl->setVariable("MARK",ilUtil::prepareFormOutput($marks->getMark(),false));
		}


		$this->tpl->setVariable("TXT_COMMENT",$this->lng->txt('trac_comment'));

		if(ilLPObjSettings::_lookupMode($this->item_id) == LP_MODE_MANUAL)
		{
			$completed = ilLPStatusWrapper::_getCompleted($this->item_id);
			
			$this->tpl->setVariable("mode_manual");
			$this->tpl->setVariable("TXT_COMPLETED",$this->lng->txt('trac_completed'));
			$this->tpl->setVariable("CHECK_COMPLETED",ilUtil::formCheckbox(in_array((int) $_GET['user_id'],$completed),
																		   'completed',
																		   '1'));
		}


		$this->tpl->setVariable("TXT_CANCEL",$this->lng->txt('cancel'));
		$this->tpl->setVariable("TXT_SAVE",$this->lng->txt('save'));
	}

	function __renderContainerRow($a_parent_id,$a_item_id,$a_usr_id,$type,$level)
	{
		global $ilObjDataCache,$ilUser;

		include_once 'Services/Tracking/classes/ItemList/class.ilLPItemListFactory.php';

		$item_list =& ilLPItemListFactory::_getInstance($a_parent_id,$a_item_id,$type);
		$item_list->setCurrentUser($a_usr_id);
		$item_list->readUserInfo();
		$item_list->setIndentLevel($level);

		// Edit link, mark
		if($type != 'sahs_item' and
		   $type != 'objective')
		{

			// Mark
			$this->obj_tpl->setVariable("MARK",$item_list->getMark());

			// Edit link
			$this->obj_tpl->setCurrentBlock("item_command");
			$this->ctrl->setParameter($this,'details_id',$this->details_id);
			$this->ctrl->setParameter($this,"user_id",$a_usr_id);
			$this->ctrl->setParameter($this,'item_id',$a_item_id);
			$this->obj_tpl->setVariable('HREF_COMMAND',$this->ctrl->getLinkTarget($this,'editUser'));
			$this->obj_tpl->setVariable("TXT_COMMAND",$this->lng->txt('edit'));
			$this->obj_tpl->parseCurrentBlock();

			// Show checkbox and details button
			if(ilLPObjSettings::_isContainer($item_list->getMode()))
			{
				$item_list->addCheckbox(array('user_item_ids[]',
											  $a_usr_id.'_'.$a_item_id,
											  $this->__detailsShown($a_usr_id,$a_item_id)));
				$this->obj_tpl->setCurrentBlock("item_command");
				$this->ctrl->setParameter($this,'details_id',$this->details_id);
				$this->ctrl->setParameter($this,'user_item_ids',$a_usr_id.'_'.$a_item_id);
				if($this->__detailsShown($a_usr_id,$a_item_id))
				{
					$this->obj_tpl->setVariable('HREF_COMMAND',$this->ctrl->getLinkTarget($this,'hideDetails'));
					$this->obj_tpl->setVariable("TXT_COMMAND",$this->lng->txt('hide_details'));
				}
				else
				{
					$this->obj_tpl->setVariable('HREF_COMMAND',$this->ctrl->getLinkTarget($this,'showDetails'));
					$this->obj_tpl->setVariable("TXT_COMMAND",$this->lng->txt('show_details'));
				}
				$this->obj_tpl->parseCurrentBlock();
			}

		}
		else
		{
			$item_list->setIndentLevel($level+1);
		}
		
		// Status image
		$this->obj_tpl->setCurrentBlock("container_standard_row");

		$item_list->renderObjectDetails();

		$this->obj_tpl->setVariable("ITEM_HTML",$item_list->getHTML());
		$this->__showImageByStatus($this->obj_tpl,$item_list->getUserStatus());
		$this->obj_tpl->setVariable("TBLROW",ilUtil::switchColor($this->container_row_counter,'tblrow1','tblrow2'));
		$this->obj_tpl->parseCurrentBlock();

		if(!$this->__detailsShown($a_usr_id,$a_item_id))
		{
			return true;
		}

		include_once './Services/Tracking/classes/class.ilLPCollections.php';
		foreach(ilLPCollections::_getItems($a_item_id) as $child_id)
		{
			switch($item_list->getMode())
			{
				case LP_MODE_OBJECTIVES:
					$this->__renderContainerRow($a_item_id,$child_id,$a_usr_id,'objective',$level + 1);
					break;

				case LP_MODE_SCORM:
					$this->__renderContainerRow($a_item_id,$child_id,$a_usr_id,'sahs_item',$level + 1);
					break;

				default:
					$this->__renderContainerRow($a_item_id,$child_id,$a_usr_id,$ilObjDataCache->lookupType($child_id),$level + 1);
					break;
			}
		}
	}


	function details()
	{
		global $ilObjDataCache;
		global $ilBench;

		$ilBench->start('LearningProgress','1200_LPListOfObjects_details');

		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.lp_loo.html','Services/Tracking');

		// Show back button
		if($this->getMode() == LP_MODE_PERSONAL_DESKTOP or
		   $this->getMode() == LP_MODE_ADMINISTRATION)
		{
			$this->__showButton($this->ctrl->getLinkTarget($this,'show'),$this->lng->txt('trac_view_list'));
		}

		include_once("classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);

		$this->__showObjectDetails($info);
		$this->tpl->setVariable("INFO_TABLE",$info->getHTML());

		$this->__showUsersList();
		$ilBench->stop('LearningProgress','1200_LPListOfObjects_details');
	}



	function __showUsersList()
	{
		include_once 'Services/Tracking/classes/class.ilLPMarks.php';
		include_once 'Services/Tracking/classes/ItemList/class.ilLPItemListFactory.php';

		global $ilObjDataCache;

		$not_attempted = ilLPStatusWrapper::_getNotAttempted($this->details_id);
		$in_progress = ilLPStatusWrapper::_getInProgress($this->details_id);
		$completed = ilLPStatusWrapper::_getCompleted($this->details_id);

		$all_users = $this->__sort(array_merge($completed,$in_progress,$not_attempted),'usr_data','lastname','usr_id');
		$sliced_users = array_slice($all_users,$this->offset,$this->max_count);
		
		$this->obj_tpl = new ilTemplate('tpl.lp_loo_user_list.html',true,true,'Services/Tracking');


		$this->__initFilter();
		$type = $this->filter->getFilterType();
		$this->obj_tpl->setVariable("HEADER_IMG",ilUtil::getImagePath('icon_usr.gif'));
		$this->obj_tpl->setVariable("HEADER_ALT",$this->lng->txt('objs_usr'));
		$this->obj_tpl->setVariable("BLOCK_HEADER_CONTENT",$this->lng->txt('trac_usr_list'));

		// Show table header
		$this->obj_tpl->setVariable("HEAD_STATUS",$this->lng->txt('trac_status'));
		$this->obj_tpl->setVariable("HEAD_MARK",$this->lng->txt('trac_mark'));
		$this->obj_tpl->setVariable("HEAD_OPTIONS",$this->lng->txt('actions'));


		// Render item list
		$this->container_row_counter = 0;
		foreach($sliced_users as $user)
		{
			$this->__renderContainerRow(0,$this->details_id,$user,'usr',0);
			$this->container_row_counter++;
		}

		// Hide button
		$this->obj_tpl->setVariable("DOWNRIGHT",ilUtil::getImagePath('arrow_downright.gif'));
		$this->obj_tpl->setVariable("BTN_HIDE_SELECTED",$this->lng->txt('show_details'));
		$this->obj_tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));

		$this->tpl->setVariable("LP_OBJECTS",$this->obj_tpl->get());
		$this->tpl->setVariable("LEGEND", $this->__getLegendHTML());
	}

	function __showUserList()
	{
		include_once 'Services/Tracking/classes/class.ilLPMarks.php';

		global $ilObjDataCache;

		$not_attempted = ilLPStatusWrapper::_getNotAttempted($this->details_id);
		$in_progress = ilLPStatusWrapper::_getInProgress($this->details_id);
		$completed = ilLPStatusWrapper::_getCompleted($this->details_id);

		$all_users = $this->__sort(array_merge($completed,$in_progress,$not_attempted),'usr_data','lastname','usr_id');
		$sliced_users = array_slice($all_users,$this->offset,$this->max_count);

		$this->ctrl->setParameter($this,'details_id',$this->details_id);
		$this->tpl->setVariable("FORMACTION",$this->ctrl->getFormAction($this));
		$this->tpl->setVariable("HEADER_IMG",ilUtil::getImagePath('icon_usr.gif'));
		$this->tpl->setVariable("HEADER_ALT",$this->lng->txt('trac_usr_list'));
		$this->tpl->setVariable("TXT_STATUS",$this->lng->txt('trac_status'));
		$this->tpl->setVariable("TXT_OPTIONS",$this->lng->txt('actions'));


		if($this->details_type != 'lm')
		{
			$this->tpl->setVariable("TXT_MARK",$this->lng->txt('trac_mark'));
		}

		if($this->details_mode == LP_MODE_COLLECTION)
		{
			$this->__readItemStatusInfo(array_merge($this->items = ilLPCollections::_getItems($this->details_id)),
										array($this->details_id));
		}
		else
		{
			$this->__readItemStatusInfo(array($this->details_id));
		}

		$counter = 0;
		foreach($sliced_users as $user_id)
		{
			$cssrow = ilUtil::switchColor($counter++,'tblrow1','tblrow2');

			// show user_info
			$this->tpl->setVariable("TXT_TITLE",$ilObjDataCache->lookupTitle($user_id));
			$this->tpl->setVariable("TXT_DESC",'['.ilObjUser::_lookupLogin($user_id).']');

			// Status
			$status = $this->__readStatus($this->details_id,$user_id);
			$this->tpl->setVariable("TXT_PROP",$this->lng->txt('trac_status'));
			$this->tpl->setVariable("VAL_PROP",$this->lng->txt($status));

			$this->__showImageByStatus($this->tpl,$status);

			// Status info
			if($status_info = $this->__getStatusInfo($this->details_id,$user_id))
			{
				$this->tpl->setCurrentBlock("status_info");
				$this->tpl->setVariable("STATUS_PROP",$status_info[0]);
				$this->tpl->setVariable("STATUS_VAL",$status_info[1]);
				$this->tpl->parseCurrentBlock();
			}

			// Comment
			if(strlen($comment = ilLPMarks::_lookupComment($user_id,$this->details_id)))
			{
				$this->tpl->setCurrentBlock("comment_prop");
				$this->tpl->setVariable("TXT_PROP_COMM",$this->lng->txt('trac_comment'));
				$this->tpl->setVariable("VAL_PROP_COMM",$comment);
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setVariable("CSSROW",$cssrow);
			$this->tpl->setVariable("TYPE_IMG",ilObjUser::_getPersonalPicturePath($user_id,'xxsmall'));
			$this->tpl->setVariable("TYPE_ALT",$ilObjDataCache->lookupTitle($user_id));
			$this->ctrl->setParameter($this,"user_id",$user_id);
			$this->ctrl->setParameter($this,'item_id',$this->details_id);

			$this->tpl->setCurrentBlock("cmd");
			$this->tpl->setVariable("EDIT_COMMAND",$this->ctrl->getLinkTarget($this,'editUser'));
			$this->tpl->setVariable("TXT_COMMAND",$this->lng->txt('edit'));
			$this->tpl->parseCurrentBlock();

			if($this->details_type != 'lm')
			{
				$this->tpl->setVariable("MARK_TDCSS",$cssrow);
				$this->tpl->setVariable("MARK",ilLPMarks::_lookupMark($user_id,$this->details_id));
			}
			
			// Details for course mode collection
			if($this->details_mode == LP_MODE_COLLECTION ||
				$this->details_mode == LP_MODE_SCORM)
			{
				// estimated processing time
				if ($this->details_mode == LP_MODE_COLLECTION)
				{
					$processing_time_info = $this->__readProcessingTime($user_id);
					if($this->tlt_sum and $processing_time_info['total_percent'] != "0.00%")
					{
						$this->tpl->setCurrentBlock("tlt_prop");
						$this->tpl->setVariable("TXT_PROP_TLT",$this->lng->txt('trac_processing_time'));
						$this->tpl->setVariable("VAL_PROP_TLT",$processing_time_info['total_percent']);
						$this->tpl->parseCurrentBlock();
					}
				}

				// user check
				$this->tpl->setVariable("CHECK_USER",ilUtil::formCheckbox((int) $this->__detailsShown($user_id),
																		  'user_ids[]',
																		  $user_id));
				
				$this->tpl->setCurrentBlock("cmd");
				$this->ctrl->setParameter($this,'details_user',$user_id);
				$this->tpl->setVariable("EDIT_COMMAND",$this->ctrl->getLinkTarget($this,$this->__detailsShown($user_id) ?
																				  'hideDetails' : 'showDetails'));
																				  
				$this->tpl->setVariable("TXT_COMMAND",$this->__detailsShown($user_id) ? 
										$this->lng->txt('hide_details') : $this->lng->txt('show_details'));
				$this->tpl->parseCurrentBlock();
			}				
			

			// show course details
			if($this->details_mode == LP_MODE_COLLECTION &&
				$this->__detailsShown($user_id))
			{
				foreach(ilLPCollections::_getItems($this->details_id) as $obj_id)
				{
					// show item_info
					$this->tpl->setVariable("ITEM_TITLE",$ilObjDataCache->lookupTitle($obj_id));

					// Status
					$status = $this->__readStatus($obj_id,$user_id);
					$this->tpl->setVariable("ITEM_PROP",$this->lng->txt('trac_status'));
					$this->tpl->setVariable("ITEM_VAL",$this->lng->txt($status));
					$this->__showImageByStatus($this->tpl,$status,'ITEM_');

					if($processing_time_info[$obj_id] and 
					   $processing_time_info[$obj_id] != "0.00%")

					{
						$this->tpl->setCurrentBlock("tlt_item_prop");
						$this->tpl->setVariable("TXT_PROP_ITEM_TLT",$this->lng->txt('trac_processing_time'));
						$this->tpl->setVariable("VAL_PROP_ITEM_TLT",$processing_time_info[$obj_id]);
						$this->tpl->parseCurrentBlock();
					}

					if(strlen($comment = ilLPMarks::_lookupComment($user_id,$obj_id)))
					{
						$this->tpl->setCurrentBlock("item_comment_prop");
						$this->tpl->setVariable("ITEM_TXT_PROP_COMM",$this->lng->txt('trac_comment'));
						$this->tpl->setVariable("ITEM_VAL_PROP_COMM",$comment);
						$this->tpl->parseCurrentBlock();
					}
					if($status_info = $this->__getStatusInfo($obj_id,$user_id))
					{
						$this->tpl->setCurrentBlock("item_status_info");
						$this->tpl->setVariable("ITEM_STATUS_PROP",$status_info[0]);
						$this->tpl->setVariable("ITEM_STATUS_VAL",$status_info[1]);
						$this->tpl->parseCurrentBlock();
					}
					
					$this->tpl->setCurrentBlock("item_image");
					$this->tpl->setVariable("ITEM_IMG",ilUtil::getImagePath('icon_'.$ilObjDataCache->lookupType($obj_id).'.gif'));
					$this->tpl->setVariable("ITEM_ALT",$this->lng->txt('obj_'.$ilObjDataCache->lookupType($obj_id)));
					$this->tpl->parseCurrentBlock();

					$this->ctrl->setParameter($this,'user_id',$user_id);
					$this->ctrl->setParameter($this,"item_id",$obj_id);
					$this->ctrl->setParameter($this,'details_id',$this->details_id);
					$this->tpl->setCurrentBlock("edit_command");
					$this->tpl->setVariable("ITEM_EDIT_COMMAND",$this->ctrl->getLinkTarget($this,'editUser'));
					$this->tpl->setVariable("ITEM_TXT_COMMAND",$this->lng->txt('edit'));
					$this->tpl->parseCurrentBlock();

					$this->tpl->setVariable("ITEM_CSSROW",$cssrow);
					$this->tpl->setVariable("ITEM_MARK",ilLPMarks::_lookupMark($user_id,$obj_id));

					$this->tpl->setCurrentBlock("item_row");
					$this->tpl->parseCurrentBlock();
				}			
			}

			// show scorm details 
			if($this->details_mode == LP_MODE_SCORM &&
				$this->__detailsShown($user_id))
			{
				include_once './content/classes/SCORM/class.ilSCORMItem.php';
				foreach(ilLPCollections::_getItems($this->details_id) as $item_id)
				{
					// show item_info
					$this->tpl->setVariable("ITEM_TITLE",ilSCORMItem::_lookupTitle($item_id));

					// Status
					//$status = $this->__readStatus($obj_id,$user_id);
					$status = $this->__readSCORMStatus($item_id,$this->details_id,$user_id);
					$this->tpl->setVariable("ITEM_PROP",$this->lng->txt('trac_status'));
					$this->tpl->setVariable("ITEM_VAL",$this->lng->txt($status));
					$this->__showImageByStatus($this->tpl,$status,'ITEM_');
					

					$this->tpl->setVariable("ITEM_CSSROW", $cssrow);
					//$this->tpl->setVariable("ITEM_IMG",ilUtil::getImagePath('icon_'.$ilObjDataCache->lookupType($obj_id).'.gif'));
					//$this->tpl->setVariable("ITEM_ALT",$this->lng->txt('obj_'.$ilObjDataCache->lookupType($obj_id)));

					//$this->tpl->setVariable("ITEM_MARK",ilLPMarks::_lookupMark($user_id,$obj_id));

					/*
					$this->ctrl->setParameter($this,'user_id',$user_id);
					$this->ctrl->setParameter($this,"item_id",$obj_id);
					$this->ctrl->setParameter($this,'details_id',$this->details_id);
					$this->tpl->setVariable("ITEM_EDIT_COMMAND",$this->ctrl->getLinkTarget($this,'editUser'));
					$this->tpl->setVariable("ITEM_TXT_COMMAND",$this->lng->txt('edit'));
					*/

					$this->tpl->setCurrentBlock("item_row");
					$this->tpl->parseCurrentBlock();
				}			
			}

			$this->tpl->setCurrentBlock("user_row");
			$this->tpl->parseCurrentBlock();

				
		}
		// show commands
		if($this->details_mode == LP_MODE_COLLECTION ||
			$this->details_mode == LP_MODE_SCORM)
		{
			$this->tpl->setCurrentBlock("button_footer");
			$this->tpl->setVariable("FOOTER_CMD",'showDetails');
			$this->tpl->setVariable("FOOTER_CMD_TEXT",$this->lng->txt('show_details'));
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("tblfooter");
			$this->tpl->setVariable("DOWNRIGHT",ilUtil::getImagePath('arrow_downright.gif'));
			$this->tpl->parseCurrentBlock();
		}

		// Show linkbar
		if(count($all_users) > $this->max_count)
		{
			$this->tpl->setCurrentBlock("linkbar");
			$this->ctrl->setParameter($this,'details_id',$this->details_id);
			$this->tpl->setVariable("LINKBAR",ilUtil::Linkbar($this->ctrl->getLinkTarget($this,'details'),
															  count($all_users),
															  $this->max_count,
															  (int) $this->offset,
															  array(),
															  array('link' => '',
																	'prev' => '<<<',
																	'next' => '>>>')));
			$this->tpl->parseCurrentBlock();
		}
		// no users found
		if(!count($all_users))
		{
			$this->tpl->setCurrentBlock("no_content");
			$this->tpl->setVariable("NO_CONTENT",$this->lng->txt('trac_no_content'));
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			$this->tpl->setVariable("LEGEND", $this->__getLegendHTML());
		}
		
		return true;
	}

	function showDetails()
	{
		if(isset($_GET['user_item_ids']))
		{
			$ids = array($_GET['user_item_ids']);
		}
		else
		{
			unset($_SESSION['lp_show'][$this->details_id]);
			$ids = $_POST['user_item_ids'] ? $_POST['user_item_ids'] : array();
		}
		foreach($ids as $id)
		{			
			$_SESSION['lp_show'][$this->details_id][$id] = true;
		}
		$this->details();

		return true;
	}

	function hideDetails()
	{
		if(isset($_GET['user_item_ids']))
		{
			unset($_SESSION['lp_show'][$this->details_id]["$_GET[user_item_ids]"]);
			$this->details();
			return true;
		}
	}

	function __detailsShown($a_usr_id,$item_id)
	{
		return $_SESSION['lp_show'][$this->details_id][$a_usr_id.'_'.$item_id] ? true : false;
		#return $_SESSION['lp_show'][$this->details_id][$a_usr_id];
	}


	function show()
	{
		global $ilObjDataCache;
		global $ilBench;

		$ilBench->start('LearningProgress','1100_LPListOfObjects_show');

		// Show only detail of current repository item if called from repository
		switch($this->getMode())
		{
			case LP_MODE_REPOSITORY:
				$this->__initDetails($ilObjDataCache->lookupObjId($this->getRefId()));
				$this->details();
				
				$ilBench->stop('LearningProgress','1100_LPListOfObjects_show');
				return true;
		}
		$this->__listObjects();
		$ilBench->stop('LearningProgress','1100_LPListOfObjects_show');
		return true;
	}

	function __listObjects()
	{
		global $ilUser,$ilObjDataCache;

		include_once './Services/Tracking/classes/ItemList/class.ilLPItemListFactory.php';

		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.lp_loo.html','Services/Tracking');

		$this->__initFilter();
		$this->__showFilter();

		$tpl = new ilTemplate('tpl.lp_loo_objects.html',true,true,'Services/Tracking');

		$this->filter->setRequiredPermission('edit_learning_progress');
		if(!count($objs = $this->filter->getObjects()))
		{
			sendInfo($this->lng->txt('trac_filter_no_access'));
			return true;
		}

		// Limit info
		if($this->filter->limitReached())
		{
			$info = sprintf($this->lng->txt('trac_filter_limit_reached'),$this->filter->getLimit());
			$this->tpl->setVariable("LIMIT_REACHED",$info);
		}

		// Show table header
		$tpl->setVariable("HEAD_STATUS",$this->lng->txt('trac_status'));
		$tpl->setVariable("HEAD_OPTIONS",$this->lng->txt('actions'));
		
		$type = $this->filter->getFilterType();
		$tpl->setVariable("HEADER_IMG",ilUtil::getImagePath('icon_'.$type.'.gif'));
		$tpl->setVariable("HEADER_ALT",$this->lng->txt('objs_'.$type));
		$tpl->setVariable("BLOCK_HEADER_CONTENT",$this->lng->txt('objs_'.$type));

		// Sort objects by title
		$sorted_objs = $this->__sort(array_keys($objs),'object_data','title','obj_id');

		// Render item list
		$counter = 0;
		foreach($sorted_objs as $object_id)
		{
			$item_list =& ilLPItemListFactory::_getInstance(0,$object_id,$ilObjDataCache->lookupType($object_id));
			$item_list->read();
			$item_list->addCheckbox(array('item_id[]',$object_id,false));
			$item_list->addReferences($objs[$object_id]['ref_ids']);
			$item_list->enable('path');
			$item_list->renderObjectList();

			// Details link
			if(!$this->isAnonymized())
			{
				$tpl->setCurrentBlock("item_command");
				$this->ctrl->setParameter($this,'details_id',$object_id);
				$tpl->setVariable("HREF_COMMAND",$this->ctrl->getLinkTarget($this,'details'));
				$tpl->setVariable("TXT_COMMAND",$this->lng->txt('details'));
				$tpl->parseCurrentBlock();
			}
			
			// Hide link
			$tpl->setCurrentBlock("item_command");
			$this->ctrl->setParameterByClass('illpfiltergui','hide',$object_id);
			$tpl->setVariable("HREF_COMMAND",$this->ctrl->getLinkTargetByClass('illpfiltergui','hide'));
			$tpl->setVariable("TXT_COMMAND",$this->lng->txt('trac_hide'));
			$tpl->parseCurrentBlock();

			$tpl->setCurrentBlock("container_standard_row");
			$tpl->setVariable("ITEM_HTML",$item_list->getHTML());
			$tpl->setVariable("TBLROW",ilUtil::switchColor($counter++,'tblrow1','tblrow2'));
			$tpl->parseCurrentBlock();
		}

		// Hide button
		$tpl->setVariable("DOWNRIGHT",ilUtil::getImagePath('arrow_downright.gif'));
		$tpl->setVariable("BTN_HIDE_SELECTED",$this->lng->txt('trac_hide'));
		$tpl->setVariable("FORMACTION",$this->ctrl->getFormActionByClass('illpfiltergui'));

		$this->tpl->setVariable("LP_OBJECTS",$tpl->get());
	}

	// Private
	function __readProcessingTime($a_usr_id)
	{
		include_once 'Services/Tracking/classes/class.ilLearningProgress.php';
		include_once 'Services/Tracking/classes/class.ilLPStatusWrapper.php';

		if(!is_array($this->tlt))
		{
			$this->__readTLT();
		}

		$pt_info = array();
		foreach($this->tlt as $item_id => $info)
		{
			if(in_array($a_usr_id,ilLPStatusWrapper::_getCompleted($item_id)))
			{
				$pt_info['total'] += $info['tlt'];
				$pt_info[$item_id] = "100%";
				continue;
			}

			switch($this->obj_data[$item_id]['type'])
			{
				case 'lm':
			
					$lp_info = ilLearningProgress::_getProgress($a_usr_id,$item_id);
					$pt_info['total'] += min($lp_info['spent_time'],$info['tlt']);
					$pt_info[$item_id] = $this->__getPercent($info['tlt'],min($lp_info['spent_time'],$info['tlt']));
					break;
			}
		}

		$pt_info['total_percent'] = $this->__getPercent($this->tlt_sum,$pt_info['total']);

		return $pt_info ? $pt_info : array();
	}

	function __readTLT()
	{
		global $ilObjDataCache;

		include_once 'Services/MetaData/classes/class.ilMDEducational.php';

		$this->tlt_sum = 0;
		$this->tlt = array();
		foreach($this->items as $item_id)
		{
			if($tlt = ilMDEducational::_getTypicalLearningTimeSeconds($item_id))
			{
				$this->tlt[$item_id]['tlt'] = $tlt;
			}
			$this->tlt_sum += $this->tlt[$item_id]['tlt'];
		}
		return true;
	}
		
	function __showFilter()
	{
		global $ilBench;

		$ilBench->start('LearningProgress','1110_LPListOfObjects_showFilter');
		$this->tpl->setVariable("FILTER",$this->filter_gui->getHTML());
		$ilBench->stop('LearningProgress','1110_LPListOfObjects_showFilter');
	}

	function __initFilterGUI()
	{
		global $ilUser;

		include_once './Services/Tracking/classes/class.ilLPFilterGUI.php';

		$this->filter_gui = new ilLPFilterGUI($ilUser->getId());
	}

	function __initFilter()
	{
		global $ilUser;

		include_once './Services/Tracking/classes/class.ilLPFilter.php';

		$this->filter = new ilLPFilter($ilUser->getId());
	}

	function __initDetails($a_details_id)
	{
		global $ilObjDataCache;


		if($a_details_id)
		{
			$this->details_id = $a_details_id;
			$this->details_type = $ilObjDataCache->lookupType($this->details_id);
			$this->details_mode = ilLPObjSettings::_lookupMode($this->details_id);
		}
	}
}
?>