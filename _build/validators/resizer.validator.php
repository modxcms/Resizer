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
		case xPDOTransport::ACTION_INSTALL:
			/* return false if conditions are not met */
			$modx->log(xPDO::LOG_LEVEL_INFO, '[Resizer]');
			if (version_compare(PHP_VERSION, '5.3.2', '>=')) {
				// $phpver = true;
				$modx->log(xPDO::LOG_LEVEL_INFO, 'PHP version: ' . PHP_VERSION . ' [<b>OK</b>]');
			}
			else {
				// $phpver = false;
				$modx->log(xPDO::LOG_LEVEL_INFO, 'PHP version: ' . PHP_VERSION);
				$modx->log(xPDO::LOG_LEVEL_ERROR, 'Resizer requires PHP 5.3.2 or higher');
			}

			$success = FALSE;
			$modx->log(xPDO::LOG_LEVEL_INFO,'Availabe graphics libraries:');
			if (class_exists('Gmagick', FALSE)) {
				$magick = new Gmagick();
				$version = $magick->getversion();
				$modx->log(xPDO::LOG_LEVEL_INFO, "* {$version['versionString']}");
				$success = TRUE;
			}
			if (class_exists('Imagick', FALSE)) {
				$magick = new Imagick();  // instantiate an object since getVersion isn't a static...
				$version = $magick->getVersion();  // ...method in old versions of Imagick
				$modx->log(xPDO::LOG_LEVEL_INFO, "* {$version['versionString']}");
				$success = TRUE;
			}
			if (function_exists('gd_info'))  {
				$version = gd_info();
				$modx->log(xPDO::LOG_LEVEL_INFO, "* GD: {$version['GD Version']}");
				$success = TRUE;
			}
			if (!$success) {
				$modx->log(xPDO::LOG_LEVEL_ERROR,'Resizer requires one of the following PHP extensions: Gmagick, Imagick, GD.');
			}
			// $success = $success && $phpver;
			$success = TRUE;  // keep Resizer from preventing the install of a package which includes it

			/* [[+code]] */
			break;
		case xPDOTransport::ACTION_UPGRADE:
			/* return false if conditions are not met */
			/* [[+code]] */
			break;

		case xPDOTransport::ACTION_UNINSTALL:
			break;
	}
}

return true;