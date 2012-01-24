<?php
/**
 * Timeline extension for MediaWiki 1.18 using SIMILE Timeline
 * Copyright (C) 2012 Seong Jae Lee <seongjae@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * SIMILE Timeline, http://www.simile-widgets.org/timeline/ is included in this 
 * distribution. It is covered by BSD license.
 *
 * ColorBox, http://jacklmoore.com/colorbox/ is included in this distribution.
 * It is covered by its MIT license.
 */
	
/**
 * @file
 * @ingroup Extensions
 * @author Seong Jae Lee
 * 
 * This extension wraps SIMILE Timeline
 */

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die(-1);
}

$wgExtensionCredits['parserhook']['SimileTimeline'] = array(
	'path'		=> __FILE__,
	'name'		=> 'SimileTimeline',
	'description'	=> 'Renders a timeline using SIMILE Timeline library',
	'version'	=> '1.0',
	'author'	=> 'Seong Jae Lee'
);

$wgHooks['ParserFirstCallInit'][] = 'wfSimileTimelineParserInit';

function wfSimileTimelineParserInit(Parser &$parser) {
	$parser->setHook('timeline', 'wfSimileTimelineRender');
	return true;
}

$wgSimileTimelineCounter = 0;

function wfSimileTimelineRender($input, array $args, Parser $parser, PPFrame $frame) {

	global $wgSimileTimelineCounter;
	$wgSimileTimelineCounter = $wgSimileTimelineCounter + 1;

	if ($wgSimileTimelineCounter == 1) {
                global $wgScriptPath;
		$directory = $wgScriptPath.'/extensions/SimileTimeline/';
		$parser->getOutput()->addHeadItem("<script type='text/javascript' src='".$directory."timeline_2.3.0/timeline_js/timeline-api.js?bundle=true'></script>\n");
		$parser->getOutput()->addHeadItem("<style type='text/css'>.timeline{line-height:12px;font-size:11px;letter-spacing:-0.05em;color:black;}.timeline-event-icon{line-height:0;}</style>\n");
		$parser->getOutput()->addHeadItem("<script type='text/javascript' src='".$directory."colorbox_1.3.19/jquery.colorbox-min.js'></script>\n");
		$parser->getOutput()->addHeadItem("<link rel='stylesheet' href='".$directory."colorbox_1.3.19/colorbox.css' media='screen' />\n");
		$parser->getOutput()->addHeadItem("<script type='text/javascript'>$(document).ready(function(){ $(\".timeline_colorbox_button\").colorbox({inline:true, width:\"100%\"}); });</script>");
	}

	$xml = new SimpleXMLElement('<timeline>'.$input.'</timeline>');

	$out = "<script type='text/javascript'>
var timeline;
function LoadTimeline() {
	
	// If we don't add this, it gives us the following error:
	// 'Failed to load resource: the server responded with a status of 404 (Not Found)'
	SimileAjax.History.enabled = false;
	
	var theme = Timeline.ClassicTheme.create();
	theme.event.instant.icon = '".$directory."images/circle.png';
	theme.event.instant.iconWidth = 5;
	theme.event.instant.iconHeight = 6;
	theme.event.tape.height = 6;
	
";

	// event source
	$i = 0;
	foreach ($xml->eventsource as $eventsource) {
		$out = $out."\tvar eventSource".$i." = new Timeline.DefaultEventSource();\n";

		$jsonText = "{\n'dateTimeFormat':'iso8601',\n'events':[\n";
		foreach ($eventsource->data->event as $event) {
			$jsonText = $jsonText."\t{";
			foreach ($event->attributes() as $key => $value) {
				$value = str_replace("\"", "\\\"", $value);
				$key = str_replace("\"", "\\\"", $key);
				if (strcmp($value, 'true') != 0 && strcmp($value, 'false') != 0) {
					$value = "\"".$value."\"";
				}
				$jsonText = $jsonText."\"".$key."\":".$value.",";
			}
			if (count($event->attributes())) {
				$jsonText = substr($jsonText, 0, -1);
			}
			$jsonText = $jsonText."},\n";
		}
		if (count($eventsource->data->event)) {
			$jsonText = substr($jsonText, 0, -2);
		}
		$jsonText = $jsonText."]\n}";
		$out = $out."\teventSource".$i.".loadJSON(".$jsonText.", document.location.href);\n\n";

		$i = $i + 1;
	}

	// band information
	$out = $out."\tvar bandInfos = [\n";
	$i = 0;
	foreach ($xml->eventsource as $eventsource) {
		$out = $out."\t\tTimeline.createBandInfo({eventSource:eventSource".$i.",theme:theme,";
		foreach ($eventsource->attributes() as $key => $value) {
			$out = $out.$key.":".$value.",";
		}
		if (count($eventsource->attributes())) {
			$out = substr($out, 0, -1);
		}
		$out = $out."}),\n";
		$i = $i + 1;
	}
	if (count($xml->eventsource)) {
		$out = substr($out, 0, -2);
	}
	$out = $out."\t];\n\n";

	$i = 0;
	foreach ($xml->eventsource as $eventsource) {
		if ($i != 0)
			$out = $out."\tbandInfos[".$i."].syncWith = 0;\n";
		$i = $i + 1;
	}
	$out = $out."\n";

	$out = $out."\ttimeline = Timeline.create(document.getElementById('timeline".$wgSimileTimelineCounter."'), bandInfos, Timeline.HORIZONTAL);\n";
	$out = $out."}\n";
	$out = $out."function ResizeTimeline(){if(resizeTimerID==null){resizeTimerID=window.setTimeout(function(){resizeTimer=null;timeline.layout();},500);}}\n";
	$out = $out."$(document).ready(LoadTimeline);\n";
	$out = $out."$(document).resize(ResizeTimeline);\n";
	$out = $out."$(document).bind('cbox_complete', function(){ timeline.layout(); });\n";
	$out = $out."$(document).bind('cbox_closed', function(){ timeline.layout(); });\n";
	$out = $out."</script>\n";

	$parser->getOutput()->addHeadItem($out);
	
	// height
	$height = '300px';
	if (isset($args['height']) && $args['height']) {
		$height = $args['height'];
	}
	return "
<div class='thumbinner'>
<div class='thumbimage'>
<div id='timeline".$wgSimileTimelineCounter."' class='timeline' style='height:".$height."'></div>
</div>
<div class='thumbcaption'>
<div class='magnify'><a class='timeline_colorbox_button' href='#timeline".$wgSimileTimelineCounter."'><img src='".$directory."images/magnify-clip.png' width='15' height='11' /></a></div>
</div>
</div>
\n";
}

?>
