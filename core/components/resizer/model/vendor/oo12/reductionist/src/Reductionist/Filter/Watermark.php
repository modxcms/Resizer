<?php
namespace Reductionist\Filter;

use Reductionist\Reductionist;
use Imagine\Filter\ImagineAware;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;

/*
 *  Watermark filters. wmt: text, wmi: image
 */

class Watermark extends ImagineAware {
	protected $opt;
	protected $debug;
	protected $debugmessages;

	static protected $rgb;

	public function __construct($options, &$debuglog = array(false)) {
		$this->opt = $options;
		$this->debug = $debuglog[0];
		$this->debugmessages =& $debuglog;
		if (empty(self::$rgb)) { self::$rgb = new RGB(); }
	}


	public function apply(ImageInterface $image) {
		$imagine = $this->getImagine();
		$class = get_class($image);
		$isRImage = strpos($class, 'RImage');
		$isGmagick = strpos($class, 'Gmagick');
		/* Unfortunately Gmagick doesn't support opacity, so that's out for text
		   and watermark images. Also watermark images need opacity in order to
		   be rotated */

		if ($this->opt[0] === 'wmt') {
/* Text Watermark */
			$p = array(  // Initialize parameters
				'text' => empty($this->opt[1]) ? '' : $this->opt[1],
				'fontsize' => empty($this->opt[2]) ? 12 : (int) $this->opt[2],  // in points
				'alignment' => empty($this->opt[3]) ? 'C' : $this->opt[3],  // TL, T, TR, C, etc.
				'color' => empty($this->opt[4]) ? '000' : $this->opt[4],  // color (hex)
				'opacity' => empty($this->opt[6]) ? 100 : (int) $this->opt[6],  // opacity
				'margin' => empty($this->opt[7]) ? 3 : (float) $this->opt[7],  // margin from the edge / bg box padding ( < 1 : percent, >= 1 : pixels)
				'angle' => empty($this->opt[8]) ? 0 : (float) $this->opt[8],
				'bgcolor' => empty($this->opt[9]) ? null : $this->opt[9],  // default is no bg box
				'bgopacity' => empty($this->opt[10]) ? 100 : (int) $this->opt[10]
			);
			if (empty($this->opt[5]) || null === $p['fontfile'] = Reductionist::findFile($this->opt[5])) {  // font file
				$p['fontfile'] = realpath(__DIR__ . '/../resources/FiraSansOT-Medium.otf');  // default to included Fira Sans
			}

			if ($this->debug) { $this->debugmessages[] = 'Filter :: Text Watermark' . Reductionist::formatDebugArray($p); }

			if (empty($p['text'])) {  // no text, so quit
				return $image;
			}

			$alpha = $isGmagick ? 0 : 100 - $p['opacity'];
			try {
				// Set up font and bounding box
				$font = $imagine->font($p['fontfile'], $p['fontsize'], self::$rgb->color($p['color'], $alpha));
				$wmBox = $font->box($p['text'], $p['bgcolor'] === null ? $p['angle'] : 0);
				$wmWidth = $wmBox->getWidth();
				$wmHeight = $wmBox->getHeight();

				$imgSize = $image->getSize();
				$imgWidth = $imgSize->getWidth();
				$imgHeight = $imgSize->getHeight();

				 // Calculate bg box padding, or text margin
				if ($p['margin'] < 1) {  // as a percent of image dimensions
					$paddingX = round($imgWidth * $p['margin']);
					$paddingY = round($imgHeight * $p['margin']);
				}
				else {  // as an explicit pixel value
					$paddingX = $paddingY = (int) $p['margin'];
				}
				$wmbgWidth = $wmWidth + (2 * $paddingX);  // bg box dimensions
				$wmbgHeight = $wmHeight + (2 * $paddingY);

				// Check box size and reduce margin as needed to fit text into image area
				if ($wmbgWidth > $imgWidth) {
					$paddingX = max(round(($imgWidth - $wmWidth) / 2), 0);
					$wmbgWidth = $wmWidth + (2 * $paddingX);
					if ($this->debug) { $this->debugmessages[] = ":: Text watermark overflow: horizontal margin reduced to {$paddingX}px"; }
				}
				if ($wmbgHeight > $imgHeight) {
					$paddingY = max(round(($imgHeight - $wmHeight) / 2), 0);
					$wmbgHeight = $wmHeight + (2 * $paddingY);
					if ($this->debug) { $this->debugmessages[] = ":: Text watermark overflow: vertical margin reduced to {$paddingY}px"; }
				}

				$doRotate = !$isGmagick && $p['angle'] % 360;
				if ($doRotate && $p['bgcolor'] === null && $isRImage) {  // if text will be rotated, use a transparent bg for better positioning
					$p['bgcolor'] = array(255, 255, 255);
					$p['bgopacity'] = 0;
				}
				if ($p['bgcolor']) {  // if we have a bg color, add a bg box
					$wmbg = $imagine->create(  // create watermark background
						new Box($wmbgWidth, $wmbgHeight),
						self::$rgb->color($p['bgcolor'], 100 - $p['bgopacity'])
					);
					$wmbg->draw()->text($p['text'], $font, new Point($paddingX, $paddingY), 0);  // add text
					if ($doRotate) {
						$wmbg->rotate($p['angle'], self::$rgb->color(array(255, 255, 255), 100));
						$wmbgSize = $wmbg->getSize();
						$wmbgWidth = $wmbgSize->getWidth();
						$wmbgHeight = $wmbgSize->getHeight();
					}
					if ($wmbgWidth > $imgWidth || $wmbgHeight > $imgHeight) {  // if the box overflows the image...
						$wmbgWidth = $wmbgWidth > $imgWidth ? $imgWidth : $wmbgWidth;
						$wmbgHeight = $wmbgHeight > $imgHeight ? $imgHeight : $wmbgHeight;
						$wmbg->crop(new Point(0, 0), new Box(  // ...crop it to fit
							$wmbgWidth,
							$wmbgHeight
						));
					}
					$wmbgStartPoint = Reductionist::startPoint(  // Calculate top left coordinates for bg box
						$p['alignment'],
						array($imgWidth, $imgHeight),
						array($wmbgWidth, $wmbgHeight)
					);
					if ($this->debug) {
						$this->debugmessages[] = ":: Text watermark with background: {$wmbgWidth}x$wmbgHeight px @ $wmbgStartPoint";
					}
					$image->paste($isRImage ? $wmbg->getImage() : $wmbg, $wmbgStartPoint);  // add to image
				}
				else {  // otherwise simply add text
					$wmbgStartPoint = Reductionist::startPoint(  // Calculate top left coordinates for bg box
						$p['alignment'],
						array($imgWidth, $imgHeight),
						array($wmbgWidth, $wmbgHeight)
					);
					$wmStartPoint = new Point($wmbgStartPoint->getX() + $paddingX, $wmbgStartPoint->getY() + $paddingY);
					if ($this->debug) { $this->debugmessages[] = ":: Text watermark: $wmBox @ $wmStartPoint"; }
					$image->draw()->text($p['text'], $font, $wmStartPoint, $p['angle']);  // add to Image
				}
			}
			catch(\Exception $e) {
				$this->debugmessages[] = '*** Text Watermark Error: ' . $e->getMessage();
				return $image;
			}

		}

		elseif ($this->opt[0] === 'wmi') {
/* Image Watermark */
			$file = empty($this->opt[1]) ? null : Reductionist::findFile($this->opt[1]);
			$p = array(  // Initialize parameters
				'file' => $file,
				'alignment' => empty($this->opt[2]) ? 'C' : $this->opt[2],  // TL, T, TR, C, etc.
				'opacity' => empty($this->opt[3]) ? 100 : (int) $this->opt[3],  // opacity
				'x' => empty($this->opt[4]) ? 0 : (float) $this->opt[4],  // margin X
				'y' => empty($this->opt[5]) ? 0 : (float) $this->opt[5],  // margin Y
				'angle' => empty($this->opt[6]) ? 0 : (float) $this->opt[6]
			);

			if ($this->debug) { $this->debugmessages[] = 'Filter :: Image Watermark' . Reductionist::formatDebugArray($p); }
			if ($file === null) {
				$this->debugmessages[] = '*** Image Watermark Error: ' . (empty($this->opt[1]) ? 'no image specified' : "{$this->opt[1]} not found");
				return $image;
			}

			try {
				$imgSize = $image->getSize();
				$imgWidth = $imgSize->getWidth();
				$imgHeight = $imgSize->getHeight();

				$wm = $imagine->open($p['file']);
				$wmSize = $wm->getSize();
				$wmWidth = $wmSize->getWidth();
				$wmHeight = $wmSize->getHeight();

				$doRotate = !$isGmagick && $p['angle'] % 360;
				if ($doRotate) {  // calculate bounding box for rotated image
					$rads = deg2rad($p['angle']);
					$sin = sin($rads);
					$cos = cos($rads);
					$rotWidth = ceil(abs($wmWidth * $cos) + abs($wmHeight * $sin));
					$rotHeight = ceil(abs($wmWidth * $sin) + abs($wmHeight * $cos));
					$imgSize = new Box((int) ($imgWidth * $wmWidth / $rotWidth) - 1, (int) ($imgHeight * $wmHeight / $rotHeight) - 1);
					$wmWidth = $rotWidth;
					$wmHeight = $rotHeight;
				}

				if ($wmWidth > $imgWidth || $wmHeight > $imgHeight) {  // scale watermark down if it's bigger than the image
					$wm->thumbnail($imgSize);
					$wmSize = $wm->getSize();
					if ($this->debug) {
						if (!empty($wm->prescalesize)) {
							$this->debugmessages[] = ":: Image watermark prescale: " . $wm->getPrescaleSize();
						}
						$this->debugmessages[] = ":: Image watermark size reduced to $wmSize";
					}
				}

				if ($isRImage && !$isGmagick && $p['opacity'] < 100) {  // for Imagick we can easily reduce transparency
					try {
						$wm->fade($p['opacity'] / 100);
					}
					catch (\Exception $e) {  // maybe. fallback for ImageMagick < 6.3.1
						$a = round((100 - $p['opacity']) * 2.55);  // calculate alpha (0:opaque - 255:transparent)
						$mask = $imagine->create($wmSize, self::$rgb->color(array($a, $a, $a)));
						$wm->applyMask($mask->getImage());
					}
				}

				// Rotation
				if ($doRotate) {
					$wm->rotate($p['angle'], self::$rgb->color(array(255, 255, 255), 100));
					$wmSize = $wm->getSize();
					if ($wmSize->getWidth() > $imgWidth || $wmSize->getHeight() > $imgHeight) {  // one more check. Shouldn't be necessary, but it sometimes is
						$wm = $wm->thumbnail(new Box($imgWidth, $imgHeight));
						$wmSize = $wm->getSize();
						if ($this->debug) { $this->debugmessages[] = ":: Image watermark size reduced to $wmSize"; }
					}
				}

				$wmWidth = $wmSize->getWidth();
				$wmHeight = $wmSize->getHeight();

				// Margin
				if ($p['x']) {
					if ($p['x'] < 1) { $p['x'] = round($imgWidth * $p['x']); }
					if (2 * $p['x'] + $wmWidth - $imgWidth > 0) {  // reduce if necessary
						$p['x'] = (int) (($imgWidth - $wmWidth) / 2);
						if ($this->debug) { $this->debugmessages[] = ":: Image watermark X margin reduced to {$p['x']} px"; }
					}
				}
				if ($p['y']) {
					if ($p['y'] < 1) { $p['y'] = round($imgHeight * $p['y']); }
					if (2 * $p['y'] + $wmHeight - $imgHeight > 0) {
						$p['y'] = (int) (($imgHeight - $wmHeight) / 2);
						if ($this->debug) { $this->debugmessages[] = ":: Image watermark Y margin reduced to {$p['y']} px"; }
					}
				}

				$wmStartPoint = Reductionist::startPoint(  // Calculate top left coordinates
					$p['alignment'],
					array($imgWidth, $imgHeight),
					array($wmWidth + 2 * $p['x'], $wmHeight + 2 * $p['y'])
				);
				if ($p['x'] || $p['y']) {  // adjust paste point for margins
					$wmStartPoint = new Point($wmStartPoint->getX() + $p['x'], $wmStartPoint->getY() + $p['y']);
					if ($this->debug) { $this->debugmessages[] = ":: Image watemark margins: X {$p['x']} px, Y {$p['y']} px"; }
				}

				if ($this->debug) {
					$this->debugmessages[] = ":: Image watermark: $wmSize @ $wmStartPoint";

				}

				if ($isRImage) {
					$image->paste($wm->getImage(), $wmStartPoint);
				}
				elseif ($p['opacity'] >= 100) {  // GD
					$image->paste($wm, $wmStartPoint);
				}
				else {  // GD: paste with opacity
					$orig = $image->getGdResource();
					$img = imagecreatetruecolor($wmWidth, $wmHeight);
					imagecopy($img, $orig, 0, 0, $wmStartPoint->getX(), $wmStartPoint->getY(), $wmWidth, $wmHeight);
					imagecopy($img, $wm->getGdResource(), 0, 0, 0, 0, $wmWidth, $wmHeight);
					imagecopymerge($orig, $img, $wmStartPoint->getX(), $wmStartPoint->getY(), 0, 0, $wmWidth, $wmHeight, $p['opacity']);
					imagedestroy($img);
					unset($orig);
				}
			}
			catch(\Exception $e) {
				$this->debugmessages[] = '*** Image Watermark Error: ' . $e->getMessage();
				return $image;
			}
		}

		return $image;
	}

}
