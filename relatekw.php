<?php

/*
	[Discuz!] (C)2001-2007 Comsenz Inc.
	This is NOT a freeware, use is subject to license terms

	$RCSfile: relatekw.php,v $
	$Revision: 1.6 $
	$Date: 2007/07/19 13:35:49 $
*/

error_reporting(0);
set_magic_quotes_runtime(0);

define('DISCUZ_ROOT', './');
define('IN_DISCUZ', TRUE);
define('NOROBOT', TRUE);

$inajax = intval($_GET['inajax']);
$subjectenc = rawurlencode(strip_tags($_GET['subjectenc']));
$messageenc = rawurlencode(strip_tags(preg_replace("/\[.+?\]/U", '', $_GET['messageenc'])));

require_once './config.inc.php';
$data = @implode('', file("http://search.qihoo.com/sint/related_kw.html?title=$subjectenc&content=$messageenc&ics=$charset&ocs=$charset"));

if($data) {
	require_once DISCUZ_ROOT.'./include/global.func.php';
	if(PHP_VERSION > '5' && $charset != 'utf-8') {
		require_once DISCUZ_ROOT.'./include/chinese.class.php';
		$chs = new Chinese('utf-8', $charset);
	}

	$parser = xml_parser_create();
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $data, $values, $index);
	xml_parser_free($parser);

	$kws = array();

	foreach($values as $valuearray) {
		if($valuearray['tag'] == 'kw' || $valuearray['tag'] == 'ekw') {
			$kws[] = !empty($chs) ? $chs->convert(trim($valuearray['value'])) : trim($valuearray['value']);
		}
	}

	$return = '';
	if($kws) {
		foreach($kws as $kw) {
			$kw = htmlspecialchars($kw);
			$return .= $kw.' ';
		}
		$return = htmlspecialchars($return);
	}

	include template('relatekw');
}

?>