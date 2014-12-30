<?php
if (!defined('IDIR')) { die; }
/*======================================================================*\
|| ####################################################################
|| # vBulletin Impex
|| # ----------------------------------------------------------------
|| # All PHP code in this file is Copyright 2000-2014 vBulletin Solutions Inc.
|| # This code is made available under the Modified BSD License -- see license.txt
|| # http://www.vbulletin.com 
|| ####################################################################
\*======================================================================*/
/**
*
* @package			ImpEx.vBforum2blog
* @version			$Revision: $
* @author			Jerry Hutchings <jerry.hutchings@vbulletin.com>
* @checkedout		$Name $
* @date				$Date: $
* @copyright		http://www.vbulletin.com/license.html
*
*/

class vBforum2blog_005 extends vBforum2blog_000
{
	var $_dependent = '004';

	function vBforum2blog_005(&$displayobject)
	{
		$this->_modulestring = $displayobject->phrases['import_blog'];
	}

	function init(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		$class_num = substr(get_class($this) , -3);

		if ($this->check_order($sessionobject,$this->_dependent))
		{
			if ($this->_restart)
			{
				if ($this->restart($sessionobject, $displayobject, $Db_target, $Db_source, 'clear_imported_blogs'))
				{;
					$displayobject->display_now("<h4>{$displayobject->phrases['blogs_cleared']}</h4>");
					$this->_restart = true;
				}
				else
				{
					$sessionobject->add_error($Db_target, 'fatal', $class_num, 0, $displayobject->phrases['blog_restart_failed'], $displayobject->phrases['check_db_permissions']);
				}
			}

			// Start up the table
			$displayobject->update_basic('title',$displayobject->phrases['import_blog']);
			$displayobject->update_html($displayobject->do_form_header('index', $class_num));
			$displayobject->update_html($displayobject->make_hidden_code($class_num, 'WORKING'));
			$displayobject->update_html($displayobject->make_table_header($this->_modulestring));

			// Ask some questions
			$displayobject->update_html($displayobject->make_input_code($displayobject->phrases['units_per_page'], 'perpage', 1000));

			// End the table
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],$displayobject->phrases['reset']));

			// Reset/Setup counters for this
			$sessionobject->add_session_var("{$class_num}_objects_done", '0');
			$sessionobject->add_session_var("{$class_num}_objects_failed", '0');
			$sessionobject->add_session_var('startat','0');
		}
		else
		{
			// Dependant has not been run
			$displayobject->update_html($displayobject->do_form_header('index', ''));
			$displayobject->update_html($displayobject->make_description("<p>{$displayobject->phrases['dependant_on']}<i><b> " . $sessionobject->get_module_title($this->_dependent) . "</b> {$displayobject->phrases['cant_run']}</i> ."));
			$displayobject->update_html($displayobject->do_form_footer($displayobject->phrases['continue'],''));
			$sessionobject->set_session_var($class_num, 'FALSE');
			$sessionobject->set_session_var('module','000');
		}
	}

	function resume(&$sessionobject, &$displayobject, &$Db_target, &$Db_source)
	{
		// Set up working variables.
		$displayobject->update_basic('displaymodules','FALSE');
		$t_db_type		= $sessionobject->get_session_var('targetdatabasetype');
		$t_tb_prefix	= $sessionobject->get_session_var('targettableprefix');
		$s_db_type		= $sessionobject->get_session_var('sourcedatabasetype');
		$s_tb_prefix	= $sessionobject->get_session_var('sourcetableprefix');

		// Per page vars
		$start_at		= $sessionobject->get_session_var('startat');
		$per_page		= $sessionobject->get_session_var('perpage');
		$class_num		= substr(get_class($this) , -3);
		$ID_blog_text 	= new ImpExData($Db_target, $sessionobject, 'blog_text', 'blog');
		$ID_blog		= new ImpExData($Db_target, $sessionobject, 'blog', 'blog');
		$idcache 		= new ImpExCache($Db_target, $t_db_type, $t_tb_prefix);

		// Start the timing
		if(!$sessionobject->get_session_var("{$class_num}_start"))
		{
			$sessionobject->timing($class_num , 'start' ,$sessionobject->get_session_var('autosubmit'));
		}

		// Get an array data
		$data_array = $this->get_source_data($Db_source, $s_db_type, "{$s_tb_prefix}thread", 'threadid', 0, $start_at, $per_page);

		// Display count and pass time
		$displayobject->print_per_page_pass($data_array['count'], $displayobject->phrases['blogs'], $start_at);


		foreach ($data_array['data'] as $import_id => $data)
		{
			$blog 		= (phpversion() < '5' ? $ID_blog : clone($ID_blog));

			// Mandatory
			$blog->set_value('mandatory', 'firstblogtextid',		"0"); // Update function ?
			$blog->set_value('mandatory', 'userid',					$idcache->get_id('user', $data['postuserid']));
			$blog->set_value('mandatory', 'dateline',				$data['dateline']);
			#$blog->set_value('mandatory', 'options',				$data['options']);
			$blog->set_value('mandatory', 'title',					$data['title']);
			$blog->set_value('mandatory', 'importblogid',			$import_id);

			// Non mandatory
			#$blog->set_value('nonmandatory', 'lastblogtextid',		$data['lastblogtextid']);
			#$blog->set_value('nonmandatory', 'rating',				$data['rating']);
			$blog->set_value('nonmandatory', 'username',			$idcache->get_id('username', $data['postuserid']));
			$blog->set_value('nonmandatory', 'views',				$data['hits']);
			$blog->set_value('nonmandatory', 'state',				($data['open'] == 1 ? 'visible' : 'moderation'));
			$blog->set_value('nonmandatory', 'blogcategory',		$idcache->get_id('blogcategory', $data['forumid']));

			// Defaults
			#$blog->set_value('nonmandatory', 'attach',				$data['attach']);
			#$blog->set_value('nonmandatory', 'comments_deleted',	$data['comments_deleted']);
			#$blog->set_value('nonmandatory', 'comments_moderation',$data['comments_moderation']);
			#$blog->set_value('nonmandatory', 'comments_visible',	$data['comments_visible']);
			#$blog->set_value('nonmandatory', 'trackback_moderation',$data['trackback_moderation']);
			#$blog->set_value('nonmandatory', 'trackback_visible',	$data['trackback_visible']);
			#$blog->set_value('nonmandatory', 'pending',			$data['pending']);
			#$blog->set_value('nonmandatory', 'ratingnum',			$data['ratingnum']);
			#$blog->set_value('nonmandatory', 'ratingtotal',		$data['ratingtotal']);

			// Update in comments
			#$blog->set_value('nonmandatory', 'lastcomment',		$data['lastcomment']);
			#$blog->set_value('nonmandatory', 'lastcommenter',		$data['lastcommenter']);

			// Check if object is valid

			if($blog->is_valid())
			{
				if($blog->import_blog($Db_target, $t_db_type, $t_tb_prefix))
				{
					if(shortoutput)
					{
						$displayobject->display_now('.');
					}
					else
					{
						$displayobject->display_now('<br /><span class="isucc">' . $import_id . ' :: <b>' . $blog->how_complete() . '%</b></span> ' . $displayobject->phrases['blog'] . ' -> ' . $data['title']);
					}
					$sessionobject->add_session_var("{$class_num}_objects_done",intval($sessionobject->get_session_var("{$class_num}_objects_done")) + 1 );
				}
				else
				{
					$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
					$sessionobject->add_error($Db_target, 'warning', $class_num, $import_id, $displayobject->phrases['blog_not_imported'], $displayobject->phrases['blog_not_imported_rem']);
					$displayobject->display_now("<br />{$displayobject->phrases['failed']} :: {$displayobject->phrases['blog_not_imported']}");
				}// $blog->import_blog
			}
			else
			{
				$sessionobject->add_session_var("{$class_num}_objects_failed",intval($sessionobject->get_session_var("{$class_num}_objects_failed")) + 1 );
				$sessionobject->add_error($Db_target, 'invalid', $class_num, $import_id, $displayobject->phrases['invalid_object'] . ' ' . $blog->_failedon, $displayobject->phrases['invalid_object_rem']);
				$displayobject->display_now("<br />{$displayobject->phrases['invalid_object']}" . $blog->_failedon);
			}// is_valid
			unset($blog);
		}// End foreach

		// Check for page end
		if ($data_array['count'] == 0 OR $data_array['count'] < $per_page)
		{
			$sessionobject->timing($class_num, 'stop', $sessionobject->get_session_var('autosubmit'));
			$sessionobject->remove_session_var("{$class_num}_start");

			$displayobject->update_html($displayobject->module_finished($this->_modulestring,
				$sessionobject->return_stats($class_num, '_time_taken'),
				$sessionobject->return_stats($class_num, '_objects_done'),
				$sessionobject->return_stats($class_num, '_objects_failed')
			));

			$sessionobject->set_session_var($class_num , 'FINISHED');
			$sessionobject->set_session_var('module', '000');
			$sessionobject->set_session_var('autosubmit', '0');
		}

		$sessionobject->set_session_var('startat', $data_array['lastid']);
		$displayobject->update_html($displayobject->print_redirect('index.php',$sessionobject->get_session_var('pagespeed')));
	}// End resume
}//End Class
# Autogenerated on : August 31, 2007, 2:33 pm
# By ImpEx-generator 2.0
/*======================================================================*/
?>
