<?php
/**
 * systemSettings transport file for Resizer extra
 *
 * Copyright 2013 by Jason Grant
 * Created on 08-16-2013
 *
 * @package resizer
 * @subpackage build
 */

if (! function_exists('stripPhpTags')) {
    function stripPhpTags($filename) {
        $o = file_get_contents($filename);
        $o = str_replace('<' . '?' . 'php', '', $o);
        $o = str_replace('?>', '', $o);
        $o = trim($o);
        return $o;
    }
}
/* @var $modx modX */
/* @var $sources array */
/* @var xPDOObject[] $systemSettings */


$systemSettings = array();

$systemSettings[0] = $modx->newObject('modSystemSetting');
$systemSettings[0]->fromArray(array (
  'key' => 'resizer.graphics_library',
  'value' => '2',
  'xtype' => 'textfield',
  'namespace' => 'resizer',
  'area' => 'resizer',
), '', true, true);
return $systemSettings;
