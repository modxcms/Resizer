<?php
/**
 * Validator for Resizer extra
 *
 * Copyright 2013 by Jason Grant
 * Created on 08-16-2013
 *
 * Resizer is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Resizer is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Resizer; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 * @package resizer
 * @subpackage build
 */

/* @var $object xPDOObject */
/* @var $modx modX */
/* @var array $options */

if ($object->xpdo) {
	$modx =& $object->xpdo;
	switch ($options[xPDOTransport::PACKAGE_ACTION]) {
		case xPDOTransport::ACTION_UPGRADE:
			// move an existing non-default jpeg quality setting into the new global options
			$graphicsLib = $modx->getOption('resizer.graphics_library', null, false);
			if ($graphicsLib == 1) {
				$setting = $modx->getObject('modSystemSetting', 'resizer.graphics_library');
				$setting->set('value', 2);
				$setting->save();
			}
		case xPDOTransport::ACTION_INSTALL:
			/* return false if conditions are not met */
			$modx->log(xPDO::LOG_LEVEL_INFO, '[Resizer]');
			if (version_compare(PHP_VERSION, '5.3.2', '>=')) {
				$phpver = true;
				$modx->log(xPDO::LOG_LEVEL_INFO, 'PHP version: ' . PHP_VERSION . ' [<b>OK</b>]');
			}
			else {
				$phpver = false;
				$modx->log(xPDO::LOG_LEVEL_INFO, 'PHP version: ' . PHP_VERSION);
				$modx->log(xPDO::LOG_LEVEL_ERROR, 'Resizer requires PHP 5.3.2 or higher');
			}

			$graphicsSuccess = false;
			$modx->log(xPDO::LOG_LEVEL_INFO,'Availabe graphics libraries:');

			if (class_exists('Gmagick', false)) {
				$magick = new Gmagick();
				$version = $magick->getversion();
				$modx->log(xPDO::LOG_LEVEL_INFO, "* {$version['versionString']}");
				$graphicsSuccess = true;
			}

			if (class_exists('Imagick', false)) {
				$magick = new \Imagick();
				$v = $magick->getVersion();
				$modx->log(xPDO::LOG_LEVEL_INFO, "* {$v['versionString']}");
				list($version, $year, $month, $day, $q, $website) = sscanf($v['versionString'], 'ImageMagick %s %04d-%02d-%02d %s %s');
				$version = explode('-', $version);
				$version = $version[0];
				if (version_compare('6.2.9', $version) > 0) {
					$modx->log(xPDO::LOG_LEVEL_ERROR, '- ImageMagick 6.2.9 or higher required. Disabling Imagick support.');
					$setting = $modx->getObject('modSystemSetting', 'resizer.graphics_library');
					$setting->set('value', 0);
					$setting->save();
				}
				else {
					$graphicsSuccess = true;
					if (version_compare('6.5.7', $version) > 0) {
						$modx->log(xPDO::LOG_LEVEL_ERROR, '- ImageMagick < 6.5.7: CMYK to RGB conversions not supported');
					}
					if (version_compare('6.5.4', $version) == 0) {
						$modx->log(xPDO::LOG_LEVEL_ERROR, '- ImageMagick 6.5.4: buggy rotation. Affects rotated watermarks.');
					}
				}
			}

			if (function_exists('gd_info'))  {
				$version = gd_info();
				$modx->log(xPDO::LOG_LEVEL_INFO, "* GD: {$version['GD Version']}");
				if (version_compare(GD_VERSION, '2.0.1', '<')) {
					$modx->log(xPDO::LOG_LEVEL_ERROR, '-- GD 2.0.1 or higher required');
				}
				else {
					$graphicsSuccess = true;
				}
			}
			if (!$graphicsSuccess) {
				$modx->log(xPDO::LOG_LEVEL_ERROR, 'Resizer requires one of the following PHP extensions: Imagick, Gmagick, GD.');
			}
			return $phpver && $graphicsSuccess;
			break;

		case xPDOTransport::ACTION_UNINSTALL:
			break;
	}
}

return true;