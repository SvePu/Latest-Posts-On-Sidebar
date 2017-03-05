<?php
/**
* Latest-Posts-On-Sidebar main plugin file
*
**/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

if(my_strpos($_SERVER['PHP_SELF'], 'index.php'))
{
	global $templatelist;
	if(isset($templatelist)){$templatelist .= ',';}
	$templatelist .= 'index_sidebar_main, index_sidebar_postlist, index_sidebar_postlist_no_post';
}

/* Hooks */
$plugins->add_hook("index_end", "latestpostsonsidebar_find");

function latestpostsonsidebar_info()
{
	global $db, $lang;
	$lang->load("config_latestpostsonsidebar");

	return array(
		"name"		=>	$db->escape_string($lang->plugname),
		"description"	=>	$db->escape_string($lang->plugdesc),
		"website"	=>	"https://github.com/SvePu/Latest-Posts-On-Sidebar",
		"author"	=>	"SvePu",
		"authorsite"	=>	"https://github.com/SvePu",
		"version"	=>	"1.2",
		"codename"	=>	"latestpostsonsidebar",
		"compatibility"	=>	"18*"
		);
}

function latestpostsonsidebar_install()
{
	global $db, $lang;
	$lang->load("config_latestpostsonsidebar");
	$query_add = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query_add, "rows");

	$new_setting_group = array(
		"name" => "latestpostsonsidebar",
		"title" => $db->escape_string($lang->settings_name),
		"disporder" => $rows+1,
		"isdefault" => 0
		);

	$gid = $db->insert_query("settinggroups", $new_setting_group);

	$settings[] = array(
		"name" => "latestpostsonsidebar_threadcount",
		"title" => $db->escape_string($lang->num_posts_to_show),
		"optionscode" => "numeric",
		"disporder" => 1,
		"value" => 10,
		"gid" => $gid
		);

	$settings[] = array(
		"name" => "latestpostsonsidebar_titlelenght",
		"title" => $db->escape_string($lang->max_titlelenght),
		"description" => $db->escape_string($lang->max_titlelenght_desc),
		"optionscode" => "numeric",
		"disporder" => 2,
		"value" => 35,
		"gid" => $gid
		);

	$settings[] = array(
		"name" => "latestpostsonsidebar_forumskip",
		"title" => $db->escape_string($lang->forums_to_skip),
		"description" => $db->escape_string($lang->forums_to_skip_desc),
		"optionscode" => "forumselect",
		"disporder" => 3,
		"gid" => $gid
		);

	$settings[] = array(
		"name" => "latestpostsonsidebar_showtime",
		"title" => $db->escape_string($lang->latestposts_showtime),
		"description" => $db->escape_string($lang->latestposts_showtime_desc),
		"optionscode" => "yesno",
		"disporder" => 4,
		"value" => 1,
		"gid" => $gid
		);

	$settings[] = array(
		"name" => "latestpostsonsidebar_rightorleft",
		"title" => $db->escape_string($lang->rightorleft),
		"optionscode" => 'radio \n 1='.$db->escape_string($lang->latestposts_right).' \n 2='.$db->escape_string($lang->latestposts_left).'',
		"disporder" => 5,
		"value" => 1,
		"gid" => $gid
		);

	foreach($settings as $array => $content)
	{
		$db->insert_query("settings", $content);
	}
	rebuild_settings();

	$templates['index_sidebar_main'] = '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
	<thead>
		<tr>
			<td class="thead">
				<div><strong>{$lang->latest_posts_title}</strong></div>
			</td>
		</tr>
	</thead>
	<tbody>
		{$postslist}
	</tbody>
</table>
<br />';

$templates['index_sidebar_postlist'] = '<tr>
<td class="{$altbg}">
	<strong><a href="{$mybb->settings[\'bburl\']}/{$lastpostlink}" title="{$lastpostlink_title}">{$lastpost_subject}</a></strong>
	<br /><span class="smalltext">{$lang->latest_post_by}{$lastposterlink}{$latestposttime}</span>
</td>
</tr>';

$templates['index_sidebar_postlist_no_post'] = '<tr>
<td class="{$altbg}">{$lang->no_posts}</td>
</tr>';

foreach($templates as $title => $template)
{
	$new_template = array('title' => $db->escape_string($title), 'template' => $db->escape_string($template), 'sid' => '-1', 'version' => '1800', 'dateline' => TIME_NOW);
	$db->insert_query('templates', $new_template);
}
}

function latestpostsonsidebar_is_installed()
{
	global $db;
	$query = $db->simple_select("settinggroups", "*", "name='latestpostsonsidebar'");
	if($db->num_rows($query))
	{
		return TRUE;
	}
	return FALSE;
}

function latestpostsonsidebar_activate()
{
	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
	find_replace_templatesets('index', "#" . preg_quote('{$forums}') . "#i", '<div style="float:{$left};width: 80%;">{$forums}</div>
		<div style="float:{$right};width:19%;">{$sidebar}</div>');
}

function latestpostsonsidebar_deactivate()
{
	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
	find_replace_templatesets('index', "#" . preg_quote('<div style="float:{$left};width: 80%;">{$forums}</div>
		<div style="float:{$right};width:19%;">{$sidebar}</div>') . "#i", '{$forums}');
}

function latestpostsonsidebar_uninstall()
{
	global $db;
	$db->delete_query("templates", "title LIKE 'index_sidebar_%'");

	$query = $db->simple_select("settinggroups", "gid", "name='latestpostsonsidebar'");
	$gid = $db->fetch_field($query, "gid");
	if(!$gid)
	{
		return;
	}
	$db->delete_query("settinggroups", "name='latestpostsonsidebar'");
	$db->delete_query("settings", "gid=$gid");
	rebuild_settings();
}

function latestpostsonsidebar_find()
{
	global $mybb, $db, $templates, $theme, $postslist, $sidebar, $right, $left, $lang;
	$lang->load("latestpostsonsidebar");

	$altbg = alt_trow();
	$postslist = '';
	$tunviewwhere = $unviewwhere = $excludeforums = $fidpermissions = '';

	$unviewable = get_unviewable_forums(true);
	if($unviewable)
	{
		$unviewwhere = " AND fid NOT IN ($unviewable)";
		$tunviewwhere = " AND t.fid NOT IN ($unviewable)";
	}
	$inactive = get_inactive_forums();
	if($inactive)
	{
		$unviewwhere .= " AND fid NOT IN ($inactive)";
		$tunviewwhere .= " AND t.fid NOT IN ($inactive)";
	}

	if(!empty($mybb->settings['latestpostsonsidebar_forumskip']))
	{
		$excludeforums = "AND t.fid NOT IN ({$mybb->settings['latestpostsonsidebar_forumskip']})";
	}

	$permissions = forum_permissions();
	for($i = 0; $i <= sizeof($permissions); $i++)
	{
		if(isset($permissions[$i]['fid']) && ( $permissions[$i]['canview'] == 0 || $permissions[$i]['canviewthreads'] == 0 ))
		{
			$fidpermissions .= " AND t.fid <> ".$permissions[$i]['fid'];
		}
	}

	if($mybb->settings['latestpostsonsidebar_threadcount'] <= 0)
	{
		$mybb->settings['latestpostsonsidebar_threadcount'] = 10;
	}

	$query = $db->query("
		SELECT p.pid, p.tid, p.uid, p.username, p.subject, p.dateline, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		WHERE 1=1 {$excludeforums}{$tunviewwhere}{$fidpermissions} AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
		ORDER BY p.dateline DESC
		LIMIT 0, ".$mybb->settings['latestpostsonsidebar_threadcount']
		);

	if($db->num_rows($query) > 0 && $mybb->settings['latestpostsonsidebar_forumskip'] != '-1')
	{
		while($post = $db->fetch_array($query))
		{
			require_once MYBB_ROOT."inc/class_parser.php";
			$parser = new postParser;

			$lastpostlink = "showthread.php?tid=".$post['tid']."&pid=".$post['pid']."#pid".$post['pid'];
			$lastpostlink_title = $lastpost_subject = htmlspecialchars_uni($parser->parse_badwords($post['subject']));
			if($mybb->settings['latestpostsonsidebar_titlelenght'] > 0 && my_strlen($post['subject']) > $mybb->settings['latestpostsonsidebar_titlelenght'])
			{
				$lastpost_subject = htmlspecialchars_uni(my_substr($post['subject'], 0, $mybb->settings['latestpostsonsidebar_titlelenght']-3, 0)."...");
			}
			$lang->latest_post_by = $db->escape_string($lang->latest_post_by);
			if($post['uid'] != 0)
			{
				$lastposterlink = build_profile_link(format_name(htmlspecialchars_uni($post['username']), $post['usergroup'], $post['displaygroup']), $post['uid']);
			}
			else
			{
				$lastposterlink = htmlspecialchars_uni($post['uid']);
			}

			$lastposttimeago = my_date('relative', $post['dateline']);
			$latestposttime =  '';
			if($mybb->settings['latestpostsonsidebar_showtime'] == 1)
			{
				$latestposttime = '<br />'.$lang->sprintf($db->escape_string($lang->latestposttime), $lastposttimeago);
			}

			eval("\$postslist .= \"".$templates->get("index_sidebar_postlist")."\";");
			$altbg = alt_trow();
		}
	}
	else
	{
		$lang->no_posts = $db->escape_string($lang->no_posts);
		eval("\$postslist = \"".$templates->get("index_sidebar_postlist_no_post")."\";");
	}

	if($mybb->settings['latestpostsonsidebar_rightorleft'] == 1)
	{
		$right = "right";
		$left = "left";
	}
	else
	{
		$right = "left";
		$left = "right";
	}
	eval("\$sidebar = \"".$templates->get("index_sidebar_main")."\";");
}
