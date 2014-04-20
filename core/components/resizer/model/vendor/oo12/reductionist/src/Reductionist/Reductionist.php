<?php
namespace Reductionist;
/**
 * Reductionist
 * Copyright 2014 Jason Grant
 * Please see the GitHub page for documentation or to report bugs:
 * https://github.com/oo12/Reductionist
 **/

use Imagine\Image\Box;

class Reductionist {

public $debugmessages = array('Reductionist v1.0.0');
public $debug = false;  //enable generation of debugging messages
public $defaultQuality = 80;
public $width;
public $height;

protected $imagine;
protected $gLib;
static protected $assetpaths;
static protected $palette;
static protected $topLeft;
static protected $maxsize;

/*
 * @param  int  $graphicsLib  (optional) specify a preferred graphics library
 *							  2: Auto/Imagick, 1: Gmagick, 0: GD
 */
public function __construct($graphicsLib = 2) {
	self::$assetpaths = array('/', __DIR__ . '/resources/');
	// Decide which graphics library to use and create the appropriate Imagine object
	if ($graphicsLib > 1 && class_exists('Imagick', false)) {
		$this->debugmessages[] = 'Using Imagick';
		self::$maxsize = null;
		set_time_limit(0);
		$this->imagine = new Imagick\RImagine();
		$this->gLib = 2;
	}
	elseif ($graphicsLib && class_exists('Gmagick', false)) {
		$this->debugmessages[] = 'Using Gmagick';
		self::$maxsize = null;
		set_time_limit(0);  // execution time accounting seems strange on some systems. Maybe because of multi-threading?
		$this->imagine = new Gmagick\RImagine();
		$this->gLib = 1;
	}
	else {  // good ol' GD
		$this->debugmessages[] = 'Using GD';
		$this->imagine = new \Imagine\Gd\Imagine();
		$this->gLib = 0;
		if (!isset(self::$maxsize)) {
			self::$maxsize = ini_get('memory_limit');
			$magnitude = strtoupper(substr(self::$maxsize, -1));
			if ($magnitude === 'G')  { self::$maxsize *= 1024; }
			elseif ($magnitude === 'K')  { self::$maxsize /= 1024; }
			self::$maxsize = (self::$maxsize - 18) * 209715;  // 20% of memory_limit, in bytes. -18MB for CMS, framework, PHP overhead
		}
	}
}


/*
 * Positions an image within a container
 * $opt				String	position parameter: c, tl, br, etc.
 * $containerDims	Array	Container width, height
 * $imageDims		Array	Image width, height
 *
 * Returns a Point with the coordinates of the top left corner
 */
static public function startPoint($opt, $containerDims, $imageDims) {
	$opt = strtolower($opt);
	if ($opt == 1 || $opt === 'c') {  // center is most common
		$x = (int) (($containerDims[0] - $imageDims[0]) / 2);
		$y = (int) (($containerDims[1] - $imageDims[1]) / 2);
	}
	elseif ($opt === 'tl') {
		$x = 0;
		$y = 0;
	}
	elseif ($opt === 't') {
		$x = (int) (($containerDims[0] - $imageDims[0]) / 2);
		$y = 0;
	}
	elseif ($opt === 'tr') {
		$x = $containerDims[0] - $imageDims[0];
		$y = 0;
	}
	elseif ($opt === 'l') {
		$x = 0;
		$y = (int) (($containerDims[1] - $imageDims[1]) / 2);
	}
	elseif ($opt === 'r')  {
		$x = $containerDims[0] - $imageDims[0];
		$y = (int) (($containerDims[1] - $imageDims[1]) / 2);
	}
	elseif ($opt === 'bl')  {
		$x = 0;
		$y = $containerDims[1] - $imageDims[1];
	}
	elseif ($opt === 'b')  {
		$x = (int) (($containerDims[0] - $imageDims[0]) / 2);
		$y = $containerDims[1] - $imageDims[1];
	}
	elseif ($opt === 'br')  {
		$x = $containerDims[0] - $imageDims[0];
		$y = $containerDims[1] - $imageDims[1];
	}
	elseif (preg_match('/[0-9]x[0-9]/', $opt)) {  // user-supplied coordinate string: X x Y
		$userCoords = explode('x', $opt);
		for ($i = 0; $i < 2; ++$i) {
			if ($imageDims[$i] + $userCoords[$i] > $containerDims[$i]) {  // make sure the box stays within the container
				$userCoords[$i] = $containerDims[$i] - $imageDims[$i];
			}
			else { $userCoords[$i] = (int) $userCoords[$i]; }
		}
		list($x, $y) = $userCoords;
	}
	else {  // otherwise same as center
		$x = (int) (($containerDims[0] - $imageDims[0]) / 2);
		$y = (int) (($containerDims[1] - $imageDims[1]) / 2);
	}
	if ($x < 0)  { $x = 0; }
	if ($y < 0)  { $y = 0; }
	return new \Imagine\Image\Point($x, $y);
}


static public function findFile($file) {
	foreach(self::$assetpaths as $path) {
		$search = $path . ltrim($file, '/');
		if (is_readable($search)) { return realpath($search); }
	}
	return null;
}


static public function formatDebugArray($log) {
	return substr(var_export($log, true), 7, -3);
}


/*
 * Cut off all image-specific debug messages. Useful when you're reusing the same
 * Reductionist object to process multiple images
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
 * Returns true/false or success/failure
 */
public function processImage($input, $output, $options = array()) {
	if ($this->debug)  { $startTime = microtime(true); }
	if (!is_readable($input)) {
		$this->debugmessages[] = 'File not ' . (file_exists($input) ? 'readable': 'found') . ": $input  *** Skipping ***";
		return false;
	}
	$this->width = $this->height = null;
	if (is_string($options))  { $options = parse_str($options); }  // convert an options string to an array if needed
	$inputParams = array('options' => $options);
	$outputIsJpg = strncasecmp('jp', pathinfo($output, PATHINFO_EXTENSION), 2) === 0;

	try {
/* initial dimensions */
			$image = $this->imagine->open($input);
			$size = $image->getSize();
			$origWidth = $inputParams['width'] = $size->getWidth();
			$origHeight = $inputParams['height'] = $size->getHeight();

		if (self::$maxsize && $origWidth * $origHeight > self::$maxsize) {  // if we're using GD we need to check the image will fit in memory
			$this->debugmessages[] = "GD: $input may exceed available memory  ** Skipping **";
			return false;
		}

/* source crop */
		if (isset($options['sw']) || isset($options['sh'])) {
			if (empty($options['sw']) || $options['sw'] > $origWidth) {
				$newWidth = $origWidth;
			}
			else {
				$newWidth = $options['sw'] < 1 ? round($origWidth * $options['sw']) : $options['sw'];  // sw < 1 is a %, >= 1 in px
			}
			if (empty($options['sh']) || $options['sh'] > $origHeight) {
				$newHeight = $origHeight;
			}
			else {
				$newHeight = $options['sh'] < 1 ? round($origHeight * $options['sh']) : $options['sh'];
			}
			if ($newWidth !== $origWidth || $newHeight !== $origHeight) {  // only if something will actually be cropped
				if (empty($options['sx'])) {
					$cropStartX = isset($options['sx']) ? $options['sx'] : (int) (($origWidth - $newWidth) / 2);  // 0 or center
				}
				else {
					$cropStartX = $options['sx'] < 1 ? round($origWidth * $options['sx']) : $options['sx'];
					if ($cropStartX + $newWidth > $origWidth)  { $cropStartX = $origWidth - $newWidth; }  // crop box can't go past the right edge
				}
				if (empty($options['sy'])) {
					$cropStartY = isset($options['sy']) ? $options['sy'] : (int) (($origHeight - $newHeight) / 2);
				}
				else {
					$cropStartY = $options['sy'] < 1 ? round($origHeight * $options['sy']) : $options['sy'];
					if ($cropStartY + $newHeight > $origHeight)  { $cropStartY = $origHeight - $newHeight; }
				}
				$scBox = new Box($newWidth, $newHeight);
				$origWidth = $newWidth;  // update input dimensions to the new cropped size
				$origHeight = $newHeight;
			}
		}
		$origAR = $origWidth / $origHeight;  // original image aspect ratio

		// use width/height if specified
		if (isset($options['w']))  { $width = $options['w']; }
		if (isset($options['h']))  { $height = $options['h']; }

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
		$bothDims = true;
		if (empty($width)) {
			if (empty($height))  {
				$height = $origHeight;
				$width = $origWidth;
			}
			else  { $width = $height * $origAR; }
			$bothDims = false;
		}
		if (empty($height)) {
			$height = $width / $origAR;
			$bothDims = false;
		}
		$newAR = $width / $height;

/* scale */
		if (empty($options['scale'])) {
			$requestedMP = $width * $height;  // we'll need this for quality scaling
		}
		else {
			$requestedMP = $width * $options['scale'] * $height * $options['scale'];
			if (empty($options['aoe'])) {  // if aoe is off, cap scale so image isn't enlarged
				$hScale = $origHeight / $height;
				$wScale = $origWidth / $width;
				$wRequested = $width * $options['scale'];
				$hRequested = $height * $options['scale'];
				$options['scale'] = ($hScale > 1 && $wScale > 1) ? min($hScale, $wScale, $options['scale']) : 1;
			}
			$options['w'] = $width *= $options['scale'];
			$options['h'] = $height *= $options['scale'];
		}

		if (empty($options['zc']) || !$bothDims) {
/* non-zc sizing */
			if ($newAR < $origAR) {  // Make sure AR doesn't change. Smaller dimension...
				if ($origWidth < $options['w'] && empty($options['aoe'])) {
					$options['w'] = $width = $origWidth;
					$options['h'] = $width / $newAR;
				}
				$height = $width / $origAR;
			}
			elseif ($newAR > $origAR) {  // ...limits larger
				if ($origHeight < $options['h'] && empty($options['aoe'])) {
					$options['h'] = $height = $origHeight;
					$options['w'] = $height * $newAR;
				}
				$width = $height * $origAR;
			}
			$width = round($width);  // clean up
			$height = round($height);
/* far */
			if (!empty($options['far']) && $bothDims) {
				$options['w'] = round($options['w']);
				$options['h'] = round($options['h']);
				if ($options['w'] > $width || $options['h'] > $height) {
					$farPoint = Reductionist::startPoint(
						$options['far'],
						array($options['w'], $options['h']),
						array($width, $height)
					);
					$farBox = new Box($options['w'], $options['h']);
					$this->width = $options['w'];
					$this->height = $options['h'];
				}
			}
		}
		else {
/* zc */
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

			$cropStart = Reductionist::startPoint(
				$options['zc'],
				array($newWidth, $newHeight),
				array($width, $height)
			);
			$cropBox = new Box($width, $height);
			$this->width = $width;
			$this->height = $height;
			$width = $newWidth;
			$height = $newHeight;
		}

		if (isset($scBox)) {
			$scale = max($width / $origWidth, $height / $origHeight);
			if ($scale <= 0.5 && $this->gLib && $image->getFormat() === IMG_JPG) {
				$scaleBox = new Box(round($inputParams['width'] * $scale), round($inputParams['height'] * $scale));
				$image->resize($scaleBox);
				$scStart = new \Imagine\Image\Point(round($cropStartX * $scale), round($cropStartY * $scale));
			}
			else {
				$scStart = new \Imagine\Image\Point($cropStartX, $cropStartY);
			}
			$image->crop($scStart, $scBox);
		}

/* resize, aoe */
		if ( $didScale = ($width < $origWidth && $height < $origHeight) || !empty($options['aoe']) ) {
			$imgBox = new Box($width, $height);
			$image->resize($imgBox);
		}

/* qmax */
		elseif (isset($options['qmax']) && empty($options['aoe']) && isset($options['q']) && $outputIsJpg) {
			// undersized input image. We'll increase q towards qmax depending on how much it's undersized
			$sizeRatio = $requestedMP / (isset($cropBox) ? $this->width * $this->height : $width * $height);
			if ($sizeRatio >= 3) {
				$options['q'] = $options['qmax'];
			}
			elseif ($sizeRatio > 1) {
				$options['q'] += round(($options['qmax'] - $options['q']) * ($sizeRatio - 1) / 2);
			}
		}

/* crop */
		if (isset($cropBox))  { $image->crop($cropStart, $cropBox); }

/* filters */
		if (!empty($options['fltr'])) {
			if (!is_array($options['fltr'])) {
				$options['fltr'] = array($options['fltr']);  // in case somebody did fltr= instead of fltr[]=
			}
			$transformation = new \Imagine\Filter\Transformation($this->imagine);
			$filterlog = array($this->debug);
			foreach($options['fltr'] as $fltr) {
				$filter = explode('|', $fltr);
				if ($filter[0] === 'usm') {  // right now only unsharp mask is implemented, sort of
					$image->effects()->sharpen();  // radius, amount and threshold are ignored!
				}
				elseif ($filter[0] === 'wmt' || $filter[0] === 'wmi') {
					$doApply = true;
					$transformation->add(new Filter\Watermark($filter, $filterlog));
				}
			}
		}

/* bg */
		if ( $hasBG = (isset($options['bg']) && !$outputIsJpg) || isset($farBox)) {
			if (self::$palette === null)  { self::$palette = new \Imagine\Image\Palette\RGB(); }
			if (self::$topLeft === null)  { self::$topLeft = new \Imagine\Image\Point(0, 0); }
			if (isset($options['bg']))  {
				$bgColor = explode('/', $options['bg']);
				$bgColor[1] = isset($bgColor[1]) ? $bgColor[1] : 100;
			}
			else  { $bgColor = array('ffffff', 100); }

			$backgroundColor = self::$palette->color($bgColor[0], 100 - $bgColor[1]);
			if (isset($cropBox))  { $bgBox = $cropBox; }
			elseif (isset($farBox))  { $bgBox = $farBox; }
			elseif (isset($imgBox))  { $bgBox = $imgBox; }
			else  { $bgBox = new Box($width, $height); }
			$image = $this->imagine
				->create($bgBox, self::$palette->color($bgColor[0], 100 - $bgColor[1]))
				->paste($this->gLib ? $image->getImage() : $image, isset($farPoint) ? $farPoint : self::$topLeft);
		}

		if (isset($transformation) && !empty($doApply)) {  // apply any filters
			try {
				$transformation->apply($image);
			}
			catch (\Exception $e) {
				$this->debugmessages[] = $e->getMessage();
			}
		}

/* debug info */
		if ($this->debug) {
			$debugTime = microtime(true);
			$this->debugmessages[] = 'Input options:' . self::formatDebugArray($inputParams['options']);  // print all options, stripping off array()
			$changed = array();  // note any options which may have been changed during processing
			foreach (array('w', 'h', 'scale', 'q') as $opt) {
				if (isset($inputParams['options'][$opt]) && $inputParams['options'][$opt] != $options[$opt])  { $changed[$opt] = $options[$opt]; }
			}
			if ($changed) {
				$this->debugmessages[] = 'Modified options:' . self::formatDebugArray($changed, true);
			}
			$this->debugmessages[] = "Original - w: {$inputParams['width']} | h: {$inputParams['height']} " . sprintf("(%2.2f MP)", $inputParams['width'] * $inputParams['height'] / 1e6);
			if (isset($image->prescalesize)) {
				$this->debugmessages[] = "JPEG prescale - w: {$image->prescalesize[0]} | h: {$image->prescalesize[1]} " . sprintf("(%2.2f MP)", $image->prescalesize[0] * $image->prescalesize[1] / 1e6);
			}
			if (isset($scBox)) {
				$this->debugmessages[] = "Source area - start: ($cropStartX, $cropStartY) | box: $scBox";
			}
			if (isset($wRequested)) {
				$this->debugmessages[] = "Requested - w: " . round($wRequested) . ' | h: ' . round($hRequested);
			}
			if (!isset($wRequested) || !$didScale) {
				$this->debugmessages[] = "New - w: $width | h: $height" . ($didScale ? '' : ' [Not scaled: same size or insufficient input resolution]');
			}
			if (isset($farPoint)) {
				$this->debugmessages[] = "FAR - start: $farPoint | box: {$options['w']}x{$options['h']} px";
			}
			if (isset($cropBox)) {
				$this->debugmessages[] = "ZC - start: $cropStart | box: $cropBox";
			}
			if ($hasBG) {
				$this->debugmessages[] = "Background color: {$bgColor[0]} | opacity: {$bgColor[1]}";
			}
			$debugTime = microtime(true) - $debugTime;
		}
		if (isset($filterlog[1])) {  // add any filter debug output
			unset($filterlog[0]);
			$this->debugmessages = array_merge($this->debugmessages, $filterlog);
		}

/* save */
		$outputOpts = array('quality' => empty($options['q']) ? $this->defaultQuality : (int) $options['q']);  // change 'q' to 'quality', or use default
		if (!empty($options['f'])) { $outputOpts['format'] = $options['f']; }
		$image->save($output, $outputOpts);
		if (!$this->width)  { $this->width = $width; }
		if (!$this->height)  { $this->height = $height; }
	}
/* error handler */
	catch(\Imagine\Exception\Exception $e) {
		$this->debugmessages[] = "Input file: $input";
		$this->debugmessages[] = 'Input options: ' . substr(var_export($inputParams['options'], true), 7, -3);
		$this->debugmessages[] = '*** Error *** ' . $e->getMessage();
		return false;
	}

/* debug info (timing) */
	if ($this->debug) {
		$this->debugmessages[] = "Wrote $output";
		$this->debugmessages[] = 'Execution time: ' . round((microtime(true) - $startTime - $debugTime) * 1e3) . ' ms';
	}
	return true;
}


}
