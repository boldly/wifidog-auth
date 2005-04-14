<?php


/********************************************************************\
 * This program is free software; you can redistribute it and/or    *
 * modify it under the terms of the GNU General Public License as   *
 * published by the Free Software Foundation; either version 2 of   *
 * the License, or (at your option) any later version.              *
 *                                                                  *
 * This program is distributed in the hope that it will be useful,  *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of   *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the    *
 * GNU General Public License for more details.                     *
 *                                                                  *
 * You should have received a copy of the GNU General Public License*
 * along with this program; if not, contact:                        *
 *                                                                  *
 * Free Software Foundation           Voice:  +1-617-542-5942       *
 * 59 Temple Place - Suite 330        Fax:    +1-617-542-2652       *
 * Boston, MA  02111-1307,  USA       gnu@gnu.org                   *
 *                                                                  *
 \********************************************************************/
/**@file ContentGroupElement.php
 * @author Copyright (C) 2005 Benoit Grégoire <bock@step.polymtl.ca>,
 * Technologies Coeus inc.
 */
require_once BASEPATH.'classes/Content/ContentGroup.php';
require_once BASEPATH.'classes/Node.php';
/** A content content group where the user must subscribe to the project */
class ContentGroupElement extends Content
{
	private $content_group_element_row;

	/** Thelike the same class as defined in Content, this methos will create a ContentGroupElement based on the content type specified by getNewContentInterface
	 * @param $user_prefix A identifier provided by the programmer to recognise it's generated form
	 * @param $content_group Must be present
	 * @return the ContentGroup object, or null if the user didn't greate one
	 */
	static function processNewContentInterface($user_prefix, ContentGroup $content_group)
	{
		global $db;
		$content_group_element_object = null;
		$name = "get_new_content_{$user_prefix}_add";
		if (!empty ($_REQUEST[$name]) && $_REQUEST[$name] == true)
		{
			$name = "get_new_content_{$user_prefix}_content_type";
			$content_type = FormSelectGenerator :: getResult($name, null);
			$displayed_content_object = self :: createNewContent($content_type);

			$content_id = get_guid();
			$content_type = 'ContentGroupElement';
			$sql = "INSERT INTO content (content_id, content_type) VALUES ('$content_id', '$content_type')";

			if (!$db->ExecSqlUpdate($sql, false))
			{
				throw new Exception(_('Unable to insert new content into database!'));
			}
			$sql = "INSERT INTO content_group_element (content_group_element_id, content_group_id) VALUES ('$content_id', '".$content_group->GetId()."')";
			if (!$db->ExecSqlUpdate($sql, false))
			{
				throw new Exception(_('Unable to insert new content into database!'));
			}
			$content_group_element_object = self :: getContent($content_id);
			$content_group_element_object->replaceDisplayedContent($displayed_content_object);
		}
		return $content_group_element_object;
	}

	function __construct($content_id)
	{
		parent :: __construct($content_id);
		$this->setIsTrivialContent(true);

		global $db;
		$content_id = $db->EscapeString($content_id);

		$sql_select = "SELECT * FROM content_group_element WHERE content_group_element_id='$content_id'";
		$db->ExecSqlUniqueRes($sql_select, $row, false);
		if ($row == null)
		{
			$db->ExecSqlUniqueRes($sql_select, $row, false);
			if ($row == null)
			{
				throw new Exception(_("The content with the following id could not be found in the database: ").$content_id);
			}

		}
		$this->content_group_element_row = $row;

	}

	public function getAdminInterface($subclass_admin_interface = null)
	{
		$html = '';
		$html .= "<div class='admin_container'>\n";
		$html .= "<div class='admin_class'>ContentGroupElement (".get_class($this)." instance)</div>\n";

		/* content_group_element_has_allowed_nodes */
		$html .= "<div class='admin_section_container'>\n";
		$html .= "<span class='admin_section_title'>"._("AllowedNodes:")."</span>\n";
		
				$html .= "<ol class='admin_section_list'>\n";
				
				global $db;
		$sql = "SELECT * FROM content_group_element_has_allowed_nodes WHERE content_group_element_id='$this->id'";
		$db->ExecSql($sql, $allowed_node_rows, false);
		if ($allowed_node_rows != null)
		{
			foreach ($allowed_node_rows as $allowed_node_row)
			{
				$node = Node :: getNode($allowed_node_row['node_id']);
				$html .= "<li class='admin_section_list_item'>\n";
				$html .= "".$node->GetId().": ".$node->GetName()."";
				$html .= "<div class='admin_section_tools'>\n";
				$name = "content_group_element_".$this->id."_allowed_node_".$node->GetId()."_remove";
				$html .= "<input type='submit' name='$name' value='"._("Remove")."' onclick='submit();'>";
				$html .= "</div>\n";
				$html .= "</li>\n";

			}
		}

		$sql = "SELECT node_id, name from nodes WHERE node_id NOT IN (SELECT node_id FROM content_group_element_has_allowed_nodes WHERE content_group_element_id='$this->id') ORDER BY node_id";
		$db->ExecSql($sql, $not_allowed_node_rows, false);
		if ($not_allowed_node_rows != null)
		{

			$i = 0;
			foreach ($not_allowed_node_rows as $not_allowed_node_row)
			{
				$tab[$i][0] = $not_allowed_node_row['node_id'];
				$tab[$i][1] = $not_allowed_node_row['node_id'].": ".$not_allowed_node_row['name'];
				$i ++;
			}
			$html .= "<li class='admin_section_list_item'>\n";
			$name = "content_group_element_{$this->id}_new_allowed_node";
			$html .= FormSelectGenerator :: generateFromArray($tab, null, $name, null, false);
			$name = "content_group_element_{$this->id}_new_allowed_node_submit";
			$html .= "<input type='submit' name='$name' value='"._("Add allowed node")."' onclick='submit();'>";
		$html .= "</li'>\n";
		}
				$html .= "</ol>\n";
		$html .= "</div>\n";

		/* displayed_content_id */
		$html .= "<div class='admin_section_container'>\n";
		$html .= "<span class='admin_section_title'>"._("Displayed content:")."</span>\n";
		if (empty ($this->content_group_element_row['displayed_content_id']))
		{
			$html .= self :: getNewContentInterface("content_group_element_{$this->id}_new_displayed_content");
		}
		else
		{
			$displayed_content = self :: getContent($this->content_group_element_row['displayed_content_id']);
			$html .= $displayed_content->getAdminInterface();
			$html .= "<div class='admin_section_tools'>\n";
			$name = "content_group_element_{$this->id}_erase_displayed_content";
			$html .= "<input type='submit' name='$name' value='"._("Delete")."' onclick='submit();'>";
			$html .= "</div>\n";
		}
		$html .= "</div>\n";

		$html .= $subclass_admin_interface;
		$html .= "</div>\n";
		return parent :: getAdminInterface($html);
	}

	/**Replace and delete the old displayed_content (if any) by the new content (or no content)
	 * @param $new_displayed_content Content object or null.  If null the old content is still deleted.
	 */
	private function replaceDisplayedContent($new_displayed_content)
	{
		global $db;
		$old_displayed_content = null;
		if (!empty ($this->content_group_element_row['displayed_content_id']))
		{
			$old_displayed_content = self :: getContent($this->content_group_element_row['displayed_content_id']);
		}
		if ($new_displayed_content != null)
		{
			$new_displayed_content_id_sql = "'".$new_displayed_content->GetId()."'";
		}
		else
		{
			$new_displayed_content_id_sql = "NULL";
		}

		$db->ExecSqlUpdate("UPDATE content_group_element SET displayed_content_id = $new_displayed_content_id_sql WHERE content_group_element_id = '$this->id'", FALSE);

		if ($old_displayed_content != null)
		{
			$old_displayed_conten->delete();
		}

	}

	function processAdminInterface()
	{
		parent :: processAdminInterface();

		/* content_group_element_has_allowed_nodes */
		global $db;
		$sql = "SELECT * FROM content_group_element_has_allowed_nodes WHERE content_group_element_id='$this->id'";
		$db->ExecSql($sql, $allowed_node_rows, false);
		if ($allowed_node_rows != null)
		{
			foreach ($allowed_node_rows as $allowed_node_row)
			{
				$node = Node :: getNode($allowed_node_row['node_id']);
				$name = "content_group_element_".$this->id."_allowed_node_".$node->GetId()."_remove";
				if (!empty ($_REQUEST[$name]) && $_REQUEST[$name] == true)
				{
					$sql = "DELETE FROM content_group_element_has_allowed_nodes WHERE content_group_element_id='$this->id' AND node_id='".$node->GetId()."'";
					$db->ExecSqlUpdate($sql, false);
				}
			}
		}
		$name = "content_group_element_{$this->id}_new_allowed_node_submit";
		if (!empty ($_REQUEST[$name]) && $_REQUEST[$name] == true)
		{
			$name = "content_group_element_{$this->id}_new_allowed_node";
			$node_id = FormSelectGenerator :: getResult($name, null);
			$node = Node :: getNode($node_id);
			$node_id = $node->GetId();
			$db->ExecSqlUpdate("INSERT INTO content_group_element_has_allowed_nodes (content_group_element_id, node_id) VALUES ('$this->id', '$node_id')", FALSE);
		}

		/* displayed_content_id */
		if (empty ($this->content_group_element_row['displayed_content_id']))
		{
			$displayed_content = Content :: processNewContentInterface("content_group_element_{$this->id}_new_displayed_content");
			if ($displayed_content != null)
			{
				$displayed_content_id = $displayed_content->GetId();
				$db->ExecSqlUpdate("UPDATE content_group_element SET displayed_content_id = '$displayed_content_id' WHERE content_group_element_id = '$this->id'", FALSE);
			}
		}
		else
		{
			$displayed_content = self :: getContent($this->content_group_element_row['displayed_content_id']);
			$name = "content_group_element_{$this->id}_erase_displayed_content";
			if (!empty ($_REQUEST[$name]) && $_REQUEST[$name] == true)
			{
				$db->ExecSqlUpdate("UPDATE content_group_element SET displayed_content_id = NULL WHERE content_group_element_id = '$this->id'", FALSE);
				$displayed_content->delete();
			}
			else
			{
				$displayed_content->processAdminInterface();
			}
		}

	}

	/** Get the order of the element in the content group
	 * @return the order of the element in the content group */
	public function getOrder()
	{
		echo "<h1>WRITEME</h1>";
		return false;
	}
	/** Set the order of the element in the content group
	 * @param $order
	 * @return true on success, false on failure */
	public function setOrder($order)
	{
		echo "<h1>WRITEME</h1>";
		return false;
	}
	
	/** Delete this Content from the database 
	 * @todo Implement proper Access control */
	public function delete()
	{	
		if (!empty ($this->content_group_element_row['displayed_content_id']))
		{
			$displayed_content = self :: getContent($this->content_group_element_row['displayed_content_id']);
			$displayed_content->delete();
		}
		parent::delete();
	}
} // End class
?>