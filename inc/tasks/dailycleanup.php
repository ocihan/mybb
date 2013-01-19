<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id$
 */

function task_dailycleanup($task)
{
	global $mybb, $db, $cache, $lang, $plugins;
	
	require_once MYBB_ROOT."inc/functions_user.php";

	$time = array(
		'sessionstime' => TIME_NOW-60*60*24,
		'threadreadcut' => TIME_NOW-(((int)$mybb->settings['threadreadcut'])*60*60*24),
		'privatemessages' => TIME_NOW-(60*60*24*7)
	);

	if(is_object($plugins))
	{
		$args = array(
			'task' => &$task,
			'time' => &$time
		);
		$plugins->run_hooks('task_dailycleanup_start', $args);
	}

	// Clear out sessions older than 24h
	$db->delete_query("sessions", "uid='0' AND time < '".(int)$time['sessionstime']."'");

	// Delete old read topics
	if($mybb->settings['threadreadcut'] > 0)
	{
		$db->delete_query("threadsread", "dateline < '".(int)$time['threadreadcut']."'");
		$db->delete_query("forumsread", "dateline < '".(int)$time['threadreadcut']."'");
	}
	
	// Check PMs moved to trash over a week ago & delete them
	$query = $db->simple_select("privatemessages", "pmid, uid, folder", "deletetime<='".(int)$time['privatemessages']."' AND folder='4'");
	while($pm = $db->fetch_array($query))
	{
		$user_update[$pm['uid']] = 1;
		$pm_update[] = $pm['pmid'];
	}

	if(is_object($plugins))
	{
		$args = array(
			'user_update' => &$user_update,
			'pm_update' => &$pm_update
		);
		$plugins->run_hooks('task_dailycleanup_end', $args);
	}

	if(!empty($pm_update))
	{
		$db->delete_query("privatemessages", "pmid IN(".implode(',', $pm_update).")");
	}
	
	if(!empty($user_update))
	{
		foreach($user_update as $uid)
		{
			update_pm_count($uid);
		}
	}
	
	$cache->update_most_replied_threads();
	$cache->update_most_viewed_threads();
	$cache->update_birthdays();
	$cache->update_forumsdisplay();
	
	add_task_log($task, $lang->task_dailycleanup_ran);
}
?>