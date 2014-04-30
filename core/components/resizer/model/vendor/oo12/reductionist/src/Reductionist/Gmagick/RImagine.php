<?php
namespace Reductionist\Gmagick;

use Imagine\Image\BoxInterface;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Metadata\MetadataBag;


class RImagine implements ImagineInterface
{
	static protected $rgb;
	static protected $emptyBag;


	public function __construct() {
		self::$rgb = new RGB();
		self::$emptyBag = new MetadataBag();
	}


	public function open($path) {
		return new RImage($path, self::$rgb, self::$emptyBag);
	}


	public function create(BoxInterface $size, ColorInterface $color = null) {
		$width  = $size->getWidth();
		$height = $size->getHeight();

		if ($color === null) {
			$palette = self::$rgb;
			$color = '#ffffff';
			$alpha = 0;
		}
		else {
			$palette = $color->getPalette();
			$alpha = $color->getAlpha() / 100;
		}

		try {
			$pixel = new \GmagickPixel((string) $color);
			$pixel->setcolorvalue(\Gmagick::COLOR_OPACITY, $alpha);  // does nothing as of Gmagick 1.1.7RC2.  Background will be fully opaque.

			$magick = new \Gmagick();
			$magick->newimage($width, $height, $pixel->getcolor(false));
			$magick->setimagecolorspace(\Gmagick::COLORSPACE_TRANSPARENT);
			$magick->setimagebackgroundcolor($pixel);

			return new RImage($magick, $palette, self::$emptyBag, array($width, $height));
		}
		catch (\Exception $e) {
			throw new \Imagine\Exception\RuntimeException("Gmagick: could not create empty image. {$e->getMessage()}", $e->getCode(), $e);
		}
	}


	static public function createPalette($cs) {
		if ($cs === \Gmagick::COLORSPACE_SRGB || $cs === \Gmagick::COLORSPACE_RGB)
			return self::$rgb;
		elseif ($cs === \Gmagick::COLORSPACE_CMYK)
			return new \Imagine\Image\Palette\CMYK();
		elseif ($cs === \Gmagick::COLORSPACE_GRAY)
			return new \Imagine\Image\Palette\Grayscale();
		else
			throw new \Imagine\Exception\RuntimeException('Gmagick: Only RGB, CMYK and Grayscale colorspaces are curently supported');
	}


	public function load($string) {
		try {
			$magick = new \Gmagick();
			$magick->readimageblob($string);
			$palette = self::createPalette($magick->getImageColorspace());
		}
		catch (\GmagickException $e) {
			throw new RuntimeException("Gmagick: Could not load image from string. {$e->getMessage()}", $e->getCode(), $e);
		}
		return new RImage($magick, $palette, self::$emptyBag);
	}


	public function read($resource) {
		if (!is_resource($resource)) {
			throw new InvalidArgumentException('Variable does not contain a stream resource');
		}

		$content = stream_get_contents($resource);

		if (false === $content) {
			throw new InvalidArgumentException('Gmagick: Couldn\'t read given resource');
		}

		return $this->load($content);
	}


	public function font($file, $size, ColorInterface $color) {
		$magick = new \Gmagick();
		$magick->newimage(1, 1, 'transparent');
		return new \Imagine\Gmagick\Font($magick, $file, $size, $color);
	}

}
