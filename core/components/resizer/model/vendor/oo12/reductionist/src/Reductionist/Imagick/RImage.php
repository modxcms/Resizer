<?php
namespace Reductionist\Imagick;

use Reductionist\Image\RAbstractImage;
use Imagine\Imagick\Image;
use Imagine\Image\BoxInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\PointInterface;


class RImage extends RAbstractImage
{
	public function __construct($resource, $palette, $metadata, $size = null) {
		parent::__construct($resource, $palette, $metadata, $size);
		if (!is_string($resource)) {  // Imagick object
			$this->image = new Image($resource, $palette, $metadata);
			if (is_array($size)) { $this->size = $size; }
			else {
				$size = $this->image->getSize();
				$this->size = array($size->getWidth(), $size->getHeight());
			}
		}
	}


	public function resize(BoxInterface $size, $filter = null) {
		$width = $size->getWidth();
		$height = $size->getHeight();
		if ($this->image === null) { $this->load(array($width, $height)); }

		$this->image->getImagick()->thumbnailImage($width, $height);
		$this->size = array($width, $height);

		return $this;
	}


	public function crop(PointInterface $start, BoxInterface $size) {
		if ($this->image === null) { $this->load(); }

		$width = $size->getWidth();
		$height = $size->getHeight();

		$magick = $this->image->getImagick();
		try {
			$magick->cropImage($width, $height, $start->getX(), $start->getY());
			$magick->setImagePage(0, 0, 0, 0);  // Reset canvas for gif format
		}
		catch (\ImagickException $e) {
			throw new \Imagine\Exception\RuntimeException('Imagick: Crop operation failed', $e->getCode(), $e);
		}
		$this->size = array($width, $height);

		return $this;
	}


	public function rotate($angle, \Imagine\Image\Palette\Color\ColorInterface $background = null) {
		if ($this->image === null) { $this->load(); }

		$color = RImagine::getColor($background);

		try {
			$pixel = new \ImagickPixel($color['color']);
			$pixel->setColorValue(\Imagick::COLOR_OPACITY, $color['alpha']);

			$magick = $this->image->getImagick();
			$magick->rotateimage($pixel, $angle);
			$pixel->clear();
			$pixel->destroy();
			$magick->setImagePage(0, 0, 0, 0);  // reset canvas position
		}
		catch (\ImagickException $e) {
			throw new \Imagine\Exception\RuntimeException('Imagick: Rotate operation failed. ' . $e->getMessage(), $e->getCode(), $e);
		}

		$this->size = array($magick->getImageWidth(), $magick->getImageHeight());

		return $this;
	}


	public function fade($opacity) {
		if ($this->image === null) { $this->load(); }

		$this->image->getImagick()->setImageOpacity($opacity);
	}


	protected function load($size = null) {
		try {
			$magick = new \Imagick();
			if ($this->format === IMG_JPG && $size !== null) {
				$magick->setOption('jpeg:size', $size[0] . 'x' . $size[1]);  // some versions of Imagick only respond to this...
				$magick->setSize($size[0], $size[1]);  // ...and others to this
			}
			$magick->readImage($this->filename);
		}
		catch (\Exception $e) {
			throw new \Imagine\Exception\RuntimeException("Imagick: Unable to open image {$this->filename}. {$e->getMessage()}", $e->getCode(), $e);
		}
		if ($this->format === IMG_JPG && $size !== null) {
			$newWidth = $magick->getImageWidth();
			if ($newWidth !== $this->size[0]) {
				$this->size = $this->prescalesize = array($newWidth, $magick->getImageHeight());
			}
		}
		$cs = $magick->getImageColorspace();
		$this->image = new Image($magick, RImagine::createPalette($cs), $this->metadata);

		if ($cs === \Imagick::COLORSPACE_CMYK) {  // convert CMYK > RGB
			try {
				$this->image->usePalette(new RGB());
			}
			catch (\Exception $e) {
				$this->image->getImagick()->stripimage();  // make sure all profiles are removed
			}
		}
	}

}
