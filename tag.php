<?php

/*
	[Discuz!] (C)2001-2007 Comsenz Inc.
	This is NOT a freeware, use is subject to license terms

	$RCSfile: tag.php,v $
	$Revision: 1.3 $
	$Date: 2007/07/12 17:19:12 $
*/

require_once './include/common.inc.php';

if(!$tagstatus) {
	showmessage('undefined_action', NULL, 'HALTED');
}

if(!empty($name)) {

	if(!preg_match('/^([\x7f-\xff_-]|\w)+$/', $name) || strlen($name) > 20) {
		showmessage('undefined_action', NULL, 'HALTED');
	}

	require_once DISCUZ_ROOT.'./include/misc.func.php';
	require_once DISCUZ_ROOT.'./forumdata/cache/cache_forums.php';

	$tpp = $inajax ? 10 : $tpp;
	$page = max(1, intval($page));
	$start_limit = ($page - 1) * $tpp;

	$tag = $db->fetch_array($db->query("SELECT * FROM {$tablepre}tags WHERE tagname='$name'"));
	if($tag['closed']) {
		showmessage('tag_closed');
	}
	$count = $db->result($db->query("SELECT count(*) FROM {$tablepre}threadtags WHERE tagname='$name'"), 0);
	$query = $db->query("SELECT t.*,tt.tid as tagtid FROM {$tablepre}threadtags tt LEFT JOIN {$tablepre}threads t USING(tid) WHERE tt.tagname='$name' ORDER BY lastpost DESC LIMIT $start_limit, $tpp");
	$cleantid = $threadlist = array();
	while($tagthread = $db->fetch_array($query)) {
		if($tagthread['tid']) {
			$threadlist[] = procthread($tagthread);
		} else {
			$cleantid[] = $tagthread['tagtid'];
		}
	}
	if($cleantid) {
		$db->query("DELETE FROM {$tablepre}threadtags WHERE tagname='$name' AND tid IN (".implodeids($cleantid).")", 'UNBUFFERED');
		$cleancount = count($cleantid);
		if($count > $cleancount) {
			$db->query("UPDATE {$tablepre}tags SET total=total-'$cleancount' WHERE tagname='$name'", 'UNBUFFERED');
		} else {
			$db->query("DELETE FROM {$tablepre}tags WHERE tagname='$name'", 'UNBUFFERED');
		}
	}
	$tagnameenc = rawurlencode($name);
	$seotitle = ' - '.$name;
	$multipage = multi($count, $tpp, $page, "tag.php?name=$tagnameenc");

	include template('tag_threads');

} else {

	$query = $db->query("SELECT tagname,total FROM {$tablepre}tags WHERE closed=0 ORDER BY total DESC LIMIT $viewthreadtags");
	$hottaglist = array();
	while($tagrow = $db->fetch_array($query)) {
		$tagrow['tagnameenc'] = rawurlencode($tagrow['tagname']);
		$hottaglist[] = $tagrow;
	}

	$count = $db->result($db->query("SELECT count(*) FROM {$tablepre}tags WHERE closed=0"), 0);
	$randlimit = mt_rand(0, $count <= $viewthreadtags ? 0 : $count - $viewthreadtags);

	$query = $db->query("SELECT tagname,total FROM {$tablepre}tags WHERE closed=0 LIMIT $randlimit, $viewthreadtags");
	$randtaglist = array();
	while($tagrow = $db->fetch_array($query)) {
		$tagrow['tagnameenc'] = rawurlencode($tagrow['tagname']);
		$randtaglist[] = $tagrow;
	}
	shuffle($randtaglist);

	include template('tag');

}

?>