<?php
// User Activity Promotion v1.0 Main Plugin File

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

if(defined("IN_ADMINCP"))
{
	$plugins->add_hook('admin_formcontainer_output_row', 'useractivity_promotion_formcontainer_output_row');
	$plugins->add_hook('admin_user_group_promotions_edit_commit', 'useractivity_promotion_commit');
	$plugins->add_hook('admin_user_group_promotions_add_commit', 'useractivity_promotion_commit');
}
$plugins->add_hook('task_promotions', 'useractivity_promotion_task');


function useractivity_promotion_info()
{
	global $mybb, $db, $lang;
	$lang->load('config_useractivity');
	
	return array(
		"name"	=> $db->escape_string($lang->useractivity_info_title),
		"description"	=> $db->escape_string($lang->useractivity_info_desc),
		"website"	=> "https://github.com/SvePu/User-Activity-Promotion",
		"author"	=> "SvePu",
		"authorsite"	=> "https://community.mybb.com/user-91011.html",
		"version"	=> "1.0",
		"codename"	=> "useractivitypromotion",
		"compatibility"	=> "18*"
	);
}


function useractivity_promotion_activate()
{
	global $mybb, $db;

	if(!$db->field_exists("useractivity", "promotions"))
	{
		$db->add_column("promotions", "useractivity", "int NOT NULL default '0'");
	}
	if(!$db->field_exists("useractivitytype", "promotions"))
	{
		$db->add_column("promotions", "useractivitytype", "varchar(20) NOT NULL DEFAULT 'days'");
	}
}


function useractivity_promotion_deactivate()
{
	global $mybb, $db;
	
	if($db->field_exists("useractivity", "promotions"))
	{
		$db->drop_column("promotions", "useractivity");
	}
	if($db->field_exists("useractivitytype", "promotions"))
	{
		$db->drop_column("promotions", "useractivitytype");
	}
}

function useractivity_promotion_formcontainer_output_row(&$args)
{
	global $run_module, $form_container, $mybb, $db, $lang, $form, $options, $options_type, $promotion;

	if(!($run_module == 'user' && !empty($form_container->_title) && $mybb->get_input('module') == 'user-group_promotions' && in_array($mybb->get_input('action'), array('add', 'edit'))))
	{
		return;
	}
	
	$lang->load('config_useractivity');
	

	if($args['label_for'] == 'requirements')
	{
		$options['useractivity'] = $lang->setting_useractivity_promotion;
		$args['content'] = $form->generate_select_box('requirements[]', $options, $mybb->input['requirements'], array('id' => 'requirements', 'multiple' => true, 'size' => 5));
	}

	if($args['label_for'] == 'timeregistered')
	{	
		$mybb->input['useractivitytype'] = 'days';
		
		if($mybb->get_input('pid', 1) && !isset($mybb->input['useractivity']))
		{
			$useractivity = $promotion['useractivity'];
			$useractivitytype = $promotion['useractivitytype'];
		}
		else
		{
			$useractivity = $mybb->get_input('useractivity');
			$useractivitytype = $mybb->get_input('useractivitytype');
		}
		

		$form_container->output_row($lang->setting_useractivity_promotion, $lang->setting_useractivity_promotion_desc, $form->generate_numeric_field('useractivity', (int)$useractivity, array('id' => 'useractivity'))." ".$form->generate_select_box("useractivitytype", $options, $useractivitytype, array('id' => 'useractivitytype')), 'useractivity');
	}
}

function useractivity_promotion_commit()
{
	global $db, $mybb, $pid, $update_promotion;

	is_array($update_promotion) or $update_promotion = array();

	$update_promotion['useractivity'] = $mybb->get_input('useractivity', 1);
	$update_promotion['useractivitytype'] = $db->escape_string($mybb->get_input('useractivitytype'));

	if($mybb->get_input('action') == 'add')
	{
		$db->update_query('promotions', $update_promotion, "pid='{$pid}'");
	}
}

function useractivity_promotion_task(&$args)
{
	if(in_array('useractivity', explode(',', $args['promotion']['requirements'])) && (int)$args['promotion']['useractivity'] >= 0 && !empty($args['promotion']['useractivitytype']))
	{
		switch($args['promotion']['useractivitytype'])
		{
			case "hours":
				$useractivity = $args['promotion']['useractivity']*60*60;
				break;
			case "days":
				$useractivity = $args['promotion']['useractivity']*60*60*24;
				break;
			case "weeks":
				$useractivity = $args['promotion']['useractivity']*60*60*24*7;
				break;
			case "months":
				$useractivity = $args['promotion']['useractivity']*60*60*24*30;
				break;
			case "years":
				$useractivity = $args['promotion']['useractivity']*60*60*24*365;
				break;
			default:
				$useractivity = $args['promotion']['useractivity']*60*60*24;
		}
		$args['sql_where'] .= "{$args['and']}lastpost <= '".(TIME_NOW-$useractivity)."'";
		$args['and'] = ' AND ';
	}
}
