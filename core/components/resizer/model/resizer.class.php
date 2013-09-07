<?php
/**
 * Resizer
 * Copyright 2013 Jason Grant
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

function resizerLoader($class) {
	@include_once MODX_CORE_PATH . 'components/resizer/model/' . str_replace('\\', '/', $class) . '.php';
}
spl_autoload_register('\resizerLoader');


use Imagine\Image\Box;

class Resizer {

private $modx;
private $imagine;
private $srgb;
private $basePathPlusUrl;

/*
 * Makes a token effort to look for a file
 * $src   string   path+filename to look for
 *
 * Returns path+filename on success, FALSE if it can't find the file
 */
private function findFile($src) {
	$file = rawurldecode($src);
	$file = MODX_BASE_PATH . ltrim($file, '/');
	$file = str_replace($this->basePathPlusUrl, MODX_BASE_PATH, $file);  // if MODX is in a subdir, keep this subdir name from occuring twice
	if (file_exists($file)) {
		return $file;
	}
	return FALSE;
}


public $debugmessages = array('Resizer v0.2.0-pl');
public $debug = FALSE;  //enable generation of debugging messages

/*
 * @param  modX $modx
 * @param  int  $graphicsLib  (optional) specify a preferred graphics library
 *							  2: Auto/Gmagick, 1: Imagick, 0: GD
 */
public function __construct(modX &$modx, $graphicsLib = TRUE) {
	$this->modx =& $modx;
	if ($graphicsLib === TRUE) {  // if a preference isn't specified, get it from system settings
		$graphicsLib = $this->modx->getOption('resizer.graphics_library', NULL, 2);
	}
	// Decide which graphics library to use and create the appropriate Imagine object
	if (class_exists('Gmagick', FALSE) && $graphicsLib > 1) {
		$this->debugmessages[] = 'Using Gmagick';
		set_time_limit(0);
		$this->imagine = new \Imagine\Gmagick\Imagine();
	}
	elseif (class_exists('Imagick', FALSE) && $graphicsLib) {
		$this->debugmessages[] = 'Using Imagick';
		set_time_limit(0);  // execution time accounting seems strange on some systems. Maybe because of multi-threading?
		$this->imagine = new \Imagine\Imagick\Imagine();
	}
	else {  // good ol' GD
		$this->debugmessages[] = 'Using GD';
		$this->imagine = new \Imagine\Gd\Imagine();
	}
	$this->basePathPlusUrl = MODX_BASE_PATH . ltrim(MODX_BASE_URL, '/');  // used to weed out duplicate subdirs
}


/*
 * Cut off all image-specific debug messages. Useful when you're reusing the same
 * Resizer object to process multiple images
 */
public function resetDebug() {
	$this->debugmessages = array_slice($this->debugmessages, 0, 2);
}


/*
 * Read image from $input, process it according to $options and write it to $output
 *
 * @param  string  $input    filename of input image
 * @param  string  $output   filename to write output image to. Image format determined by $output's extension.
 * @param  array   $options  options array or string, phpThumb style
 *
 * Returns TRUE/FALSE or success/failure
 */
public function processImage($input, $output, $options = array()) {
	if ( !file_exists($input) && !($input = $this->findFile($input)) ) {
		$this->debugmessages[] = "No such file: $input  ** Skipping **";
		return FALSE;
	}
	if ($this->debug) {
		$optionsOriginal = is_string($options) ? parse_str($options) : $options;
		$startTime = microtime(TRUE);
	}
	if (is_string($options)) {  // convert an options string to an array if needed
		$options = parse_str($options);
	}
	$outputType = strtolower(pathinfo($output, PATHINFO_EXTENSION));  // extension determines image format
	try {
		$image = $this->imagine->open($input);

		if (isset($options['w']) || isset($options['h']) || isset($options['wp']) || isset($options['hp']) || isset($options['wl']) || isset($options['hl']) || isset($options['ws']) || isset($options['hs']) || (isset($options['sw']) && isset($options['sh']))) {  // see if we have any resizing to do
			$size = $image->getSize();
			$origWidth = $size->getWidth();
			$origHeight = $size->getHeight();

			// use width/height if specified
			if (isset($options['w']))  { $width = $options['w']; }
			if (isset($options['h']))  { $height = $options['h']; }

			$origAR = $origWidth / $origHeight;  // original image aspect ratio

			// override with any orientation-specific dimensions
			$aspect = round($origAR, 2);
			if ($aspect > 1) {  // landscape
				if (isset($options['wl']))  { $width = $options['wl']; }
				if (isset($options['hl']))  { $height = $options['hl']; }
			}
			elseif ($aspect < 1) {  // portrait
				if (isset($options['wp']))  { $width = $options['wp']; }
				if (isset($options['hp']))  { $height = $options['hp']; }
			}
			else {  // square
				if (isset($options['ws']))  { $width = $options['ws']; }
				if (isset($options['hs']))  { $height = $options['hs']; }
			}

			// fill in a missing dimension
			$bothDims = TRUE;
			if (!isset($width)) {
				$width = $height * $origAR;
				$bothDims = FALSE;
			}
			if (!isset($height)) {
				$height = $width / $origAR;
				$bothDims = FALSE;
			}

			/** Scale **/
			if (!empty($options['scale'])) {
				if (empty($options['aoe'])) {  // if aoe is off, cap scale so image isn't enlarged
					$hScale = $origHeight / $height;
					$wScale = $origWidth / $width;
					$wRequested = $width * $options['scale'];  // we'll need these for quality scaling
					$hRequested = $height * $options['scale'];
					$options['scale'] = ($hScale > 1 && $wScale > 1) ? min($hScale, $wScale, $options['scale']) : 1;
				}
				$width = $width * $options['scale'];
				$height = $height * $options['scale'];
			}

			$newAR = $width / $height;
			/** Zoom Crop **/
			if (empty($options['zc'])) {
				if ($newAR < $origAR)  { $height = $width / $origAR; }
				elseif ($newAR > $origAR)  { $width = $height * $origAR; }
				$width = round($width);
				$height = round($height);

				if (isset($options['sw']) && isset($options['sh'])) {  // handle non-zc cropping
					$newWidth = $options['sw'] < 1 ? round($width * $options['sw']) : $options['sw'];  // sw < 1 is a %, >= 1 in px
					$newHeight = $options['sh'] < 1 ? round($height * $options['sh']) : $options['sh'];
					if ($newWidth > $width)  { $newWidth = $width; }  // make sure new dims don't exceed the input image
					if ($newHeight > $height)  { $newHeight = $height; }

					if (isset($options['sx'])) {
						$cropStartX = $options['sx'] < 1 ? round($width * $options['sx']) : $options['sx'];
						if ($cropStartX + $newWidth > $width)  { $cropStartX = $width - $newWidth; }  // crop box can't go past the right edge
					}
					else {
						$cropStartX = (int) (($width - $newWidth) / 2);  // center
					}
					if (isset($options['sy'])) {
						$cropStartY = $options['sy'] < 1 ? round($height * $options['sy']) : $options['sy'];
						if ($cropStartX + $newWidth > $width)  { $cropStartX = $width - $newWidth; }
					}
					else {
						$cropStartY = (int) (($height - $newHeight) / 2);
					}
					$cropStart = new Imagine\Image\Point($cropStartX, $cropStartY);
					$cropBox = new Imagine\Image\Box($newWidth, $newHeight);
				}
			}
			elseif ($bothDims) {  // Zoom Crop.  Skip if we only got one dimension.
				if (empty($options['aoe'])) {
					// if the crop box is bigger than the original image, scale it down
					if ($width > $origWidth) {
						$height = $origWidth / $newAR;
						$width = $origWidth;
					}
					if ($height > $origHeight) {
						$width = $origHeight * $newAR;
						$height = $origHeight;
					}
				}

				// make sure final image will cover the crop box
				if ($height * $origAR > $width)  {  // needs horizontal cropping
					$newWidth = round($height * $origAR);
					$width = round($width);
					$newHeight = $height = round($height);
				}
				elseif ($width / $origAR > $height)  {  // needs vertical cropping
					$newHeight = round($width / $origAR);
					$height = round($height);
					$newWidth = $width = round($width);
				}
				else {  // no cropping needed, same AR
					$newWidth = $width = round($width);
					$newHeight = $height = round($height);
				}

				$options['zc'] = strtolower($options['zc']);
				if ($options['zc'] === 'tl') {
					$cropStartX = 0;
					$cropStartY = 0;
				}
				elseif ($options['zc'] === 't') {
					$cropStartX = (int) (($newWidth - $width) / 2);
					$cropStartY = 0;
				}
				elseif ($options['zc'] === 'tr') {
					$cropStartX = $newWidth - $width;
					$cropStartY = 0;
				}
				elseif ($options['zc'] === 'l') {
					$cropStartX = 0;
					$cropStartY = (int) (($newHeight - $height) / 2);
				}
				elseif ($options['zc'] === 'r')  {
					$cropStartX = $newWidth - $width;
					$cropStartY = (int) (($newHeight - $height) / 2);
				}
				elseif ($options['zc'] === 'bl')  { $cropStartY = $newHeight - $height; }
				elseif ($options['zc'] === 'b')  {
					$cropStartX = (int) (($newWidth - $width) / 2);
					$cropStartY = $newHeight - $height;
				}
				elseif ($options['zc'] === 'br')  {
					$cropStartX = $newWidth - $width;
					$cropStartY = $newHeight - $height;
				}
				else {
					$cropStartX = (int) (($newWidth - $width) / 2);
					$cropStartY = (int) (($newHeight - $height) / 2);
				}
				$cropStart = new Imagine\Image\Point($cropStartX, $cropStartY);
				$cropBox = new Imagine\Image\Box($width, $height);
				$width = $newWidth;
				$height = $newHeight;
			}

			if ( ($width < $origWidth && $height < $origHeight) || !empty($options['aoe']) ) {
				$image->scale(new Imagine\Image\Box($width, $height));
				$didScale = TRUE;
			}
			elseif (isset($options['qmax']) && ($outputType === 'jpg' || $outputType === 'jpeg') && empty($options['aoe']) && isset($options['q'])) {
				// undersized image. We'll increase q towards qmax depending on how much it's undersized
				$sizeRatio = $origWidth * $origHeight / (isset($wRequested) ? ($wRequested * $hRequested) : ($width * $height));
				if ($sizeRatio > 0.5) {  // if new image has more that 1/2 the resolution of the requested size
					$options['q'] += round(($options['qmax'] - $options['q']) * (1 - $sizeRatio) / 0.5);
				}
				else { $options['q'] = $options['qmax']; }  // otherwise qmax
			}

			if (isset($cropBox)) { $image->crop($cropStart, $cropBox); }

			if ($this->debug) {
				$this->debugmessages[] = 'Input options:' . substr(var_export($optionsOriginal, TRUE), 7, -3);  // print all options, stripping off array()
				$this->debugmessages[] = 'Output options:' . substr(var_export($options, TRUE), 7, -3);
				$this->debugmessages[] = "\nOriginal - w: $origWidth | h: $origHeight " . sprintf("(%2.2f MP)", $origWidth * $origHeight / 1e6) .
					(isset($wRequested) ? "\nRequested - w: " . round($wRequested) . ' | h: ' . round($hRequested) : '') .
					"\nNew - w: $width | h: $height" . (isset($didScale) ? '' : ' [Not scaled: insufficient input resolution]') .
					(isset($cropBox) ? "\nCrop Box - w: {$cropBox->getWidth()} | h: {$cropBox->getHeight()}\nCrop Start - x: $cropStartX | y: $cropStartY" : '');
			}
		}

		if (!empty($options['strip'])) {  // convert to sRGB, remove any ICC profile and metadata
			if (!isset($this->srgb))  { $this->srgb = new Imagine\Image\Palette\RGB(); }
			$image->usePalette($this->srgb);
			$image->strip();
		}

		$outputOpts = isset($options['q']) ? array('quality' => (int) $options['q']) : array();  // change 'q' to 'quality'
		$image->save($output, $outputOpts);
	}
	catch(Imagine\Exception\Exception $e) {
		$this->debugmessages[] = '*** Error *** ' . $e->getMessage();
		return FALSE;
	}

	if ($this->debug) {
		$this->debugmessages[] = "Wrote $output";
		$this->debugmessages[] = 'Execution time: ' . round((microtime(TRUE) - $startTime) * 1e3) . ' ms';
	}
	return TRUE;
}


}