<?php
/**
 * Resizer
 * Copyright 2013-2014 Jason Grant
 * Please see the GitHub page for documentation or to report bugs:
 * https://github.com/oo12/Resizer
 *
 * Resizer is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option) any
 * later version.
 *
 * Resizer is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Resizer; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 **/

require __DIR__ . '/vendor/autoload.php';

use Reductionist\Reductionist;


class Resizer extends Reductionist
{

	public function __construct(modX &$modx, $graphicsLib = null) {
		if ($graphicsLib === null) {
			$graphicsLib = $modx->getOption('resizer.graphics_library', null, 2);
		}
		parent::__construct($graphicsLib);
		$this->debugmessages = str_replace('Reductionist', 'Resizer', $this->debugmessages);
		// Add some common MODX search paths for watermark images and fonts
		self::$assetpaths[] = $modx->getOption('assets_path');
		self::$assetpaths[] = $modx->getOption('base_path');
		self::$assetpaths[] = MODX_CORE_PATH;
		self::$assetpaths[] = MODX_CORE_PATH . 'model/phpthumb/fonts/';
		self::$assetpaths[] = MODX_CORE_PATH . 'model/phpthumb/images/';
	}


}
