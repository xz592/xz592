<?php

/*
	[Discuz!] (C)2001-2007 Comsenz Inc.
	This is NOT a freeware, use is subject to license terms

	$RCSfile: relatethread.php,v $
	$Revision: 1.27 $
	$Date: 2007/07/20 12:55:02 $
*/

error_reporting(0);
set_magic_quotes_runtime(0);

define('DISCUZ_ROOT', './');
define('IN_DISCUZ', TRUE);
define('NOROBOT', TRUE);

require_once './forumdata/cache/cache_settings.php';

$relatedadstatus = $_DCACHE['settings']['insenz']['topicrelatedad'] || $_DCACHE['settings']['insenz']['traderelatedad'];
if(!$_DCACHE['settings']['qihoo_status'] && !$_DCACHE['settings']['qihoo_relatedthreads'] && !$relatedadstatus) {
	exit;
}

$_SERVER = empty($_SERVER) ? $HTTP_SERVER_VARS : $_SERVER;
$_GET = empty($_GET) ? $HTTP_GET_VARS : $_GET;

$site = $_SERVER['HTTP_HOST'];
$subjectenc = rawurlencode($_GET['subjectenc']);
$tags = explode(' ',trim($_GET['tagsenc']));
$tid = intval($_GET['tid']);

require_once './config.inc.php';
if($_GET['verifykey'] <> md5($_DCACHE['settings']['authkey'].$tid.$subjectenc.$charset.$site)) {
	exit();
}

$tshow = !$_DCACHE['settings']['qihoo_relate_position'] ? 'mid' : 'bot';
$intnum = intval($_DCACHE['settings']['qihoo_relate_bbsnum']);
$extnum = intval($_DCACHE['settings']['qihoo_relate_webnum']);
$exttype = $_DCACHE['settings']['qihoo_relate_type'];
$data = @implode('', file("http://search.qihoo.com/sint/related.html?title=$subjectenc&ics=$charset&ocs=$charset&site=$site&sort=pdate&tshow=$tshow&intnum=$intnum&extnum=$extnum&exttype=$exttype"));

if($data) {
	$qihoo_validity = $_DCACHE['settings']['qihoo_relate_validity'];
	$qihoo_relate_bbsnum = $_DCACHE['settings']['qihoo_relate_bbsnum'];
	$qihoo_relate_webnum = $_DCACHE['settings']['qihoo_relate_webnum'];
	$qihoo_relate_banurl = $_DCACHE['settings']['qihoo_relate_banurl'];
	$timestamp = time();
	$chs = '';

	if(PHP_VERSION > '5' && $charset != 'utf-8') {
		require_once DISCUZ_ROOT.'./include/chinese.class.php';
		$chs = new Chinese('utf-8', $charset);
	}

	$parser = xml_parser_create();
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $data, $values, $index);
	xml_parser_free($parser);

	$xmldata = array('chanl', 'fid', 'title', 'tid', 'author', 'pdate', 'rdate', 'rnum', 'vnum', 'insite');
	$relatedthreadlist = $keywords = array();
	foreach($index as $tag => $valuearray) {
		if(in_array($tag, $xmldata)) {
			foreach($valuearray as $key => $value) {
				if($values[$index['title'][$key]]['value']) {
					$relatedthreadlist[$key][$tag] = !empty($chs) ? $chs->convert(trim($values[$value]['value'])) : trim($values[$value]['value']);
					$relatedthreadlist[$key]['fid'] = !$values[$index['fid'][$key]]['value'] ? preg_replace("/(.+?)\/forum\-(\d+)\-(\d+)\.html/", "\\2", trim($values[$index['curl'][$key]]['value'])) : trim($values[$index['fid'][$key]]['value']);
					$relatedthreadlist[$key]['tid'] = !$values[$index['tid'][$key]]['value'] ? preg_replace("/(.+?)\/thread\-(\d+)\-(\d+)-(\d+)\.html/", "\\2", trim($values[$index['surl'][$key]]['value'])) : trim($values[$index['tid'][$key]]['value']);
				}
			}
		} elseif(in_array($tag, array('kw', 'ekw'))) {
			$type = $tag == 'kw' ? 'general' : 'trade';
			foreach($valuearray as $value) {
				$keywords[$type][] = !empty($chs) ? $chs->convert(trim($values[$value]['value'])) : trim($values[$value]['value']);
			}
		} elseif($tag == 'svalid') {
			$svalid = $values[$index['svalid'][0]]['value'];
		}
	}

	$generalnew = array();
	if($keywords['general']) {
		$searchkeywords = rawurlencode(implode(' ', $keywords['general']));
		foreach($keywords['general'] as $keyword) {
			if(!in_array($keyword, $tags)) {
				$generalnew[] = $keyword;
				$relatedkeywords .= '<a href="search.php?srchtype=qihoo&amp;srchtxt='.rawurlencode($keyword).'&amp;searchsubmit=yes" target="_blank"><strong><font color="red">'.$keyword.'</font></strong></a>&nbsp;';
			}
		}
	}
	$keywords['general'] = $generalnew;

	$threadlist = array();
	if($relatedthreadlist) {
		foreach($relatedthreadlist as $key => $relatedthread) {
			if($relatedthread['insite'] == 1) {
				$threadlist['bbsthread'][] = $relatedthread;
			} elseif($qihoo_relate_webnum) {
				if(($qihoo_relate_banurl && !preg_match($qihoo_relate_banurl, $relatedthread['tid'])) || !$qihoo_relate_banurl) {
					$threadlist['webthread'][] = $relatedthread;
				}
			}
		}
		$threadlist['bbsthread'] = $threadlist['bbsthread'] ? array_slice($threadlist['bbsthread'], 0, $qihoo_relate_bbsnum) : array();
		$threadlist['webthread'] = $threadlist['webthread'] ? array_slice($threadlist['webthread'], 0, $qihoo_relate_webnum) : array();
		$relatedthreadlist = array_merge($threadlist['bbsthread'], $threadlist['webthread']);
	}

	$keywords['general'] = $keywords['general'][0] ? implode("\t", $keywords['general']) : '';
	$keywords['trade'] = $keywords['trade'][0] ? implode("\t", $keywords['trade']) : '';
	$relatedthreads = $relatedthreadlist ? addslashes(serialize($relatedthreadlist)) : '';
	$expiration = $svalid > $qihoo_validity * 86400 ? $timestamp + $svalid : $timestamp + $qihoo_validity * 86400;

	require_once './include/db_'.$database.'.class.php';
	$db = new dbstuff;
	$db->connect($dbhost, $dbuser, $dbpw, $dbname, $pconnect);
	$db->select_db($dbname);
	unset($dbhost, $dbuser, $dbpw, $dbname, $pconnect);

	$db->query("REPLACE INTO {$tablepre}relatedthreads (tid, type, expiration, keywords, relatedthreads)
		VALUES ('$tid', 'general', '$expiration', '$keywords[general]', '$relatedthreads')", 'UNBUFFERED');
	if($relatedadstatus && $keywords['trade']) {
		$db->query("REPLACE INTO {$tablepre}relatedthreads (tid, type, expiration, keywords, relatedthreads)
			VALUES ('$tid', 'trade', '$expiration', '$keywords[trade]', '$relatedthreads')", 'UNBUFFERED');
	}
}

?>