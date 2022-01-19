<?php
// User Activity Promotion v1.3 Main Plugin File

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

if(defined("IN_ADMINCP"))
{
    $plugins->add_hook("admin_config_plugins_deactivate_commit", 'useractivity_promotion_delete_plugin');
    $plugins->add_hook('admin_formcontainer_output_row', 'useractivity_promotion_formcontainer_output_row');
    $plugins->add_hook('admin_user_group_promotions_edit_commit', 'useractivity_promotion_commit');
    $plugins->add_hook('admin_user_group_promotions_add_commit', 'useractivity_promotion_commit');
}
else
{
    $plugins->add_hook('task_promotions', 'useractivity_promotion_task');
}

function useractivity_promotion_info()
{
    global $mybb, $db, $lang;
    $lang->load('user_useractivity');
    $codename = str_replace('.php', '', basename(__FILE__));

    $info = array(
        "name"          => $db->escape_string($lang->useractivity_promotion),
        "description"   => $db->escape_string($lang->useractivity_promotion_desc),
        "website"       => "https://github.com/SvePu/User-Activity-Promotion",
        "author"        => "SvePu",
        "authorsite"    => "https://community.mybb.com/user-91011.html",
        "version"       => "1.3",
        "codename"      => "useractivitypromotion",
        "compatibility" => "18*"
    );

    $installed_func = "{$codename}_is_installed";

    if(function_exists($installed_func) && $installed_func() != true)
    {
        $info['description'] = "<span class=\"float_right\"><a href=\"index.php?module=config-plugins&amp;action=deactivate&amp;plugin={$codename}&amp;delete=1&amp;my_post_key={$mybb->post_code}\"><img src=\"./styles/default/images/icons/delete.png\" title=\"".$db->escape_string($lang->delete_useractivity_promotion_link)."\" alt=\"settings_icon\" width=\"16\" height=\"16\" /></a></span>" .$info['description'];
    }

    return $info;
}

function useractivity_promotion_install()
{
    global $mybb, $db;

    if(!$db->field_exists("useractivity", "promotions"))
    {
        $db->add_column("promotions", "useractivity", "int NOT NULL default '0' AFTER `warningstype`");
    }
    if(!$db->field_exists("useractivitytype", "promotions"))
    {
        $db->add_column("promotions", "useractivitytype", "varchar(20) NOT NULL DEFAULT 'days' AFTER `useractivity`");
    }
}

function useractivity_promotion_is_installed()
{
    global $db;
    if($db->field_exists("useractivity", "promotions"))
    {
        return true;
    }
    return false;
}

function useractivity_promotion_uninstall()
{
    global $mybb, $db;

    if($mybb->request_method != 'post')
    {
        global $page, $lang;
        $lang->load('user_useractivity');
        $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=useractivity_promotion', $lang->useractivity_promotion_uninstall_message, $lang->useractivity_promotion_uninstall);
    }

    if(!isset($mybb->input['no']))
    {
        if($db->field_exists("useractivity", "promotions"))
        {
            $db->drop_column("promotions", "useractivity");
        }
        if($db->field_exists("useractivitytype", "promotions"))
        {
            $db->drop_column("promotions", "useractivitytype");
        }
    }
}

function useractivity_promotion_activate()
{
    //No activating actions
}

function useractivity_promotion_deactivate()
{
    global $db;
    if($db->field_exists("useractivity", "promotions"))
    {
        $query = $db->simple_select("promotions", "pid", "useractivity != '0'");
        while($pid = $db->fetch_array($query))
        {
            $db->delete_query("promotions", "pid='{$pid['pid']}'");
        }
    }
}

function useractivity_promotion_formcontainer_output_row(&$args)
{
    global $run_module, $form_container, $mybb, $db, $lang, $form, $options, $options_type, $promotion;

    if(!($run_module == 'user' && !empty($form_container->_title) && $mybb->get_input('module') == 'user-group_promotions' && in_array($mybb->get_input('action'), array('add', 'edit'))))
    {
        return;
    }

    $lang->load('user_useractivity');

    if($args['label_for'] == 'requirements')
    {
        $options['useractivity'] = $lang->user_useractivity_promotion;
        $args['content'] = $form->generate_select_box('requirements[]', $options, $mybb->input['requirements'], array('id' => 'requirements', 'multiple' => true, 'size' => 5));
    }

    if($args['label_for'] == 'timeregistered')
    {
        $mybb->input['useractivity'] = '0';
        $mybb->input['useractivitytype'] = 'days';

        if($mybb->get_input('pid', MyBB::INPUT_INT) && !isset($mybb->input['useractivity']))
        {
            $mybb->input['useractivity'] = $promotion['useractivity'];
            $mybb->input['useractivitytype'] = $promotion['useractivitytype'];
        }

        $form_container->output_row($lang->user_useractivity_promotion, $lang->user_useractivity_promotion_desc, $form->generate_numeric_field('useractivity', $mybb->input['useractivity'], array('id' => 'useractivity', 'min' => 0))." ".$form->generate_select_box("useractivitytype", $options, $mybb->input['useractivitytype'], array('id' => 'useractivitytype')), 'useractivity');
    }
}

function useractivity_promotion_commit()
{
    global $db, $mybb, $pid, $update_promotion;

    is_array($update_promotion) or $update_promotion = array();

    $update_promotion['useractivity'] = $mybb->get_input('useractivity', MyBB::INPUT_INT);
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
        $args['sql_where'] .= "{$args['and']}lastpost <= '".(TIME_NOW-$useractivity)."' OR (postnum < 1 AND lastactive <= '".(TIME_NOW-$useractivity)."')";
        $args['and'] = ' AND ';
    }
}

function useractivity_promotion_delete_plugin()
{
    global $mybb;
    if (!$mybb->get_input('delete'))
    {
        return;
    }

    if($mybb->get_input('delete') == 1)
    {
        global $lang;
        $lang->load('user_useractivity');
        $codename = str_replace('.php', '', basename(__FILE__));

        $installed_func = "{$codename}_is_installed";

        if(function_exists($installed_func) && $installed_func() != false)
        {
            flash_message($lang->useractivity_promotion_still_installed, 'error');
            admin_redirect('index.php?module=config-plugins');
            exit;
        }

        if($mybb->request_method != 'post')
        {
            global $page;
            $page->output_confirm_action("index.php?module=config-plugins&amp;action=deactivate&amp;plugin={$codename}&amp;delete=1&amp;my_post_key={$mybb->post_code}", $lang->useractivity_promotion_delete_confirm_message, $lang->useractivity_promotion_delete_confirm);
        }

        if(!isset($mybb->input['no']))
        {
            global $message;

            if(($handle = @fopen(MYBB_ROOT . "inc/plugins/pluginstree/" . $codename . ".csv", "r")) !== FALSE)
            {
                while(($pluginfiles = fgetcsv($handle, 1000, ",")) !== FALSE)
                {
                    foreach($pluginfiles as $file)
                    {
                        $filepath = MYBB_ROOT.$file;

                        if(@file_exists($filepath))
                        {
                            if(is_file($filepath))
                            {
                                @unlink($filepath);
                            }
                            elseif(is_dir($filepath))
                            {
                                $dirfiles = array_diff(@scandir($filepath), array('.','..'));
                                if(empty($dirfiles))
                                {
                                    @rmdir($filepath);
                                }
                            }
                            else
                            {
                                continue;
                            }
                        }
                    }
                }
                @fclose($handle);
                @unlink(MYBB_ROOT . "inc/plugins/pluginstree/" . $codename . ".csv");

                $message = $lang->useractivity_promotion_delete_message;
            }
            else
            {
                flash_message($lang->useractivity_promotion_undelete_message, 'error');
                admin_redirect('index.php?module=config-plugins');
                exit;
            }
        }
        else
        {
            admin_redirect('index.php?module=config-plugins');
            exit;
        }
    }
}
