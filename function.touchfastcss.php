<?php
/**
 * $Id$
 *
 * touchFastCss Plugin
 *
 * Copyright (c) 2009 touchdesign, <www.touchdesign.de>
 *
 * @category Plugin
 * @author Christin Gruber <www.touchdesign.de>
 * @version 1.6
 * @copyright touchdesign 14.07.2009
 * @link http://www.touchdesign.de/
 * @link http://dev.cmsmadesimple.org/projects/touchfastcss
 * @license http://www.gnu.org/licenses/licenses.html#GPL GNU General Public License (GPL 2.0)
 * 
 * --
 *
 * Usage: 
 *
 * {touchfastcss replace_relpath=1 cleanup=1 ...}
 *
 * --
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 * Or read it online: http://www.gnu.org/licenses/licenses.html#GPL
 *
 */

function smarty_function_touchfastcss($params, &$smarty) {

  debug_to_log('touchfastcss');
  debug_to_log($params);
  
  $gCms = cmsms();

  // Declare only if not declared...
  if(!function_exists('touchIsMobile')) {
    function touchIsMobile() {
      $agents = array(
        'Windows CE', 'Pocket', 'Mobile',
        'Portable', 'Smartphone', 'SDA',
        'PDA', 'Handheld', 'Symbian',
        'WAP', 'Palm', 'Avantgo',
        'cHTML', 'BlackBerry', 'Opera Mini',
        'Nokia', 'Jasmine'
      );
      foreach($agents AS $agent) {
        if(isset($_SERVER["HTTP_USER_AGENT"]) 
          && strpos($_SERVER["HTTP_USER_AGENT"], $agent) !== false) {
          return true;
        }
      }
      return false;
    }
  }

  $config = &$gCms->config;
  $db =& $gCms->GetDb();
  $tpl_id = &$gCms->variables['pageinfo']->template_id;

  $defaults = array(
    'name' => null,
    'force_rewrite' => 0,
    'replace_relpath' => 1,
    'cleanup' => 1,
    'css_path' => null,
    'comments' => 0,
	'inline' => 0
  );
  $params = array_merge($defaults,$params);

  if(!empty($params['css_path'])) {
    $css_path = $params['css_path'];
  } else {
    $css_path = "tmp/touchfastcss";
  }

  $path = $config['root_path'] . "/" . $css_path;
  if(!is_dir($path)) {
    mkdir($path);
  }

  if(!empty($params['name'])) {
    $sql = "SELECT css_text, css_id, css_name, media_type, modified_date, UNIX_TIMESTAMP(modified_date) AS mtime 
      FROM ".$config['db_prefix']."css 
      WHERE css_name = " . $db->qstr($params['name']) . " ORDER BY modified_date DESC";
    $result = $db->SelectLimit($sql,1);
  } else {
    $sql = "SELECT c.css_text, c.css_id, c.css_name, c.media_type, c.modified_date, UNIX_TIMESTAMP(c.modified_date) AS mtime 
      FROM ".$config['db_prefix']."css AS c, ".$config['db_prefix']."css_assoc AS ac 
      WHERE ac.assoc_type='template' AND ac.assoc_to_id = ".$tpl_id." AND ac.assoc_css_id = c.css_id 
      ORDER BY ac.create_date DESC";
    $result = $db->Execute($sql);
  }

  $css = array();
  while($result && $row = $result->FetchRow()) {

    $media = $row['media_type'] ? $row['media_type'] : 'all';

    $css[$media]['file'] = $tpl_id . "-" . $media . "-" . "touchFastCss" . ".css";
    if(!empty($params['comments'])) {
      $css[$media]['headings'] = "/* @@@ Plugin ". "touchFastCss" ." @@@ cssId: " 
        . $row['css_id'] . ", cssName: " . $row['css_name'] . ", cssModified: " 
        . $row['modified_date'] . " */\n";
    } else {
      $css[$media]['headings'] = "";
    }
    $css[$media]['contents'] .= $row['css_text'];
    $css[$media]['refresh'] = $row['mtime'] > @filemtime($path . "/" . $css[$media]['file']) 
      ? 1 : (int)$css[$media]['refresh'];
  }

  $html = "";
  foreach($css AS $m => $c) {

    if(!empty($params['force_rewrite']) || !file_exists($path . "/" . $c['file']) 
      || !empty($c['refresh'])) {
      $c['contents'] = str_replace('[[root_url]]', $config['root_url'], $c['contents']);
      $c['contents'] = preg_replace('/([\[\/?)(\w+)([^\]]*\])/', '', $c['contents']);
      $c['contents'] = str_replace(array('{ ',' }'), array('{','}'), $c['contents']);
      if(!empty($params['replace_relpath'])) {
        $c['contents'] = preg_replace('#url\(("|\'|)([^/"\'http:\)][^"\'\)]*)("|\'|)\)#', 'url('.$config['root_url'].'/$2)', $c['contents']);
      }
      if(!empty($params['cleanup'])) {
        $c['contents'] = preg_replace(array('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '/\s+/'), array('',' '), $c['contents']);
      }
      file_put_contents($path . "/" . $c['file'], $c['headings'].$c['contents']);
    }

	if(!$params['inline']) {
      if(!empty($params['chk_mobile']) && touchIsMobile()) {
        if($m == 'handheld') {
          $html .= "<link rel='stylesheet' type='text/css' href='".$config['root_url'] 
            . "/" . $css_path . "/" . $c['file'] . "' media='".$m."' />\n";
        }
      }else{
        $html .= "<link rel='stylesheet' type='text/css' href='".$config['root_url'] 
          . "/" . $css_path . "/" . $c['file'] . "' media='".$m."' />\n";
      }
	} else {
	  $html .= file_get_contents($path . "/" . $c['file']);
	}
  }

  return "\n<!-- touchFastCss plugin -->\n" . ($params['inline'] ? '<style type="text/css">' : '' ) 
	. $html . ($params['inline'] ? '</style>' : '' ) . "\n<!-- touchFastCss plugin -->\n";
}

function smarty_cms_help_function_touchfastcss() {

  print "<h3>Usage</h3>";
  print "<ul>";
  print "  <li>{touchfastcss replace_relpath=1 ...}</li>";
  print "</ul>";

  print "<h3>Params</h3>";
  print "<ul>";
  print "  <li><em>(optional)</em> name - Query one template by template name</li>";
  print "  <li><em>(optional)</em> force_rewrite - Force rewrite css files</li>";
  print "  <li><em>(optional)</em> chk_mobile - Check user agent for mobile browser</li>";    
  print "  <li><em>(optional)</em> css_path - Path for cached css files, default CMSms tmp_dir/static_sytlesheets</li>";
  print "  <li><em>(optional)</em> replace_relpath - Replace all relative path with absolute url -> background: url(http://www.example.com/tmp/css/name.jpg)</li>";
  print "  <li><em>(optional)</em> cleanup - Replace comments and whitespaces (minify)</li>";
  print "  <li><em>(optional)</em> comments - Add css comments for each generated css file</li>";
  print "  <li><em>(optional)</em> inline - Write inline css</li>";
  print "</ul>";

  smarty_cms_about_function_touchfastcss();
}

function smarty_cms_about_function_touchfastcss() {

  print "<h3>About</h3>";
  print "<ul>";
  print "  <li>Copyright by <a href=\"http://www.touchdesign.de/\">touchdesign</a></li>";
  print "  <li>Author Christin Gruber</li>";
  print "  <li>License GPL 2.0</li>";
  print "  <li>Version 1.6</li>";
  print "</ul>";
}

?>