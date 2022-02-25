<?php
header("Content-type: text/css");

$css = array(
	"style.css",
	"input.css",
	"navbar.css",
	"star-rating.css",
	"table-sort.css",
	"editor.css",
	"search.css",
	"font-awesome.css",
);

$css_content = '';

foreach ($css as $css_file) {
    $css_content .= file_get_contents($css_file);
}

$css_content = preg_replace(
	array(
		'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
		'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
		'#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
		'#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
		'#(background-position):0(?=[;\}])#si',
		'#(?<=[\s:,\-])0+\.(\d+)#s',
		'#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
		'#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
		'#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
		'#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
		'#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
		),
		array(
		'$1',
		'$1$2$3$4$5$6$7',
		'$1',
		':0',
		'$1:0 0',
		'.$1',
		'$1$3',
		'$1$2$4$5',
		'$1$2$3',
		'$1:0',
		'$1$2'
	),
	$css_content);

if(!ob_start("ob_gzhandler")) ob_start();
echo $css_content;