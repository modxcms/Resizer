<?php
namespace Reductionist\Gmagick;

use Reductionist\Image\RAbstractImage;
use Imagine\Gmagick\Image;
use Imagine\Image\BoxInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\PointInterface;


class RImage extends RAbstractImage
{
	public function __construct($resource, $palette, $metadata, $size = null) {
		parent::__construct($resource, $palette, $metadata, $size);
		if (!is_string($resource)) {  // Gmagick object
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

		$magick = $this->image->getGmagick();
		$magick->scaleimage($width, $height);
		$magick->stripimage();
		$this->size = array($width, $height);

		return $this;
	}


	public function crop(PointInterface $start, BoxInterface $size) {
		if ($this->image === null) { $this->load(); }

		$width = $size->getWidth();
		$height = $size->getHeight();

		try {
			$this->image->getGmagick()->cropimage($width, $height, $start->getX(), $start->getY());
		}
		catch (\GmagickException $e) {
			throw new \Imagine\Exception\RuntimeException("Gmagick: Crop operation failed. {$e->getMessage()}", $e->getCode(), $e);
		}
		$this->size = array($width, $height);

		return $this;
	}


	protected function load($size = null) {
		try {
			$magick = new \Gmagick();
			if ($this->format === IMG_JPG && $size !== null) {
				$magick->setsize($size[0], $size[1]);
			}
			$magick->readimage($this->filename);
		}
		catch (\Exception $e) {
			throw new \Imagine\Exception\RuntimeException("Gmagick: Unable to open image {$this->filename}. {$e->getMessage()}", $e->getCode(), $e);
		}
		if ($this->format === IMG_JPG && $size !== null) {
			$newWidth = $magick->getimagewidth();
			if ($newWidth !== $this->size[0]) {
				$this->size = $this->prescalesize = array($newWidth, $magick->getimageheight());
			}
		}
		$cs = $magick->getimagecolorspace();
		$this->image = new Image($magick, RImagine::createPalette($cs), $this->metadata);

		if ($cs === \Gmagick::COLORSPACE_CMYK) {  // convert CMYK > RGB
			try {
				$this->image->usePalette(new RGB());
			}
			catch (\Exception $e) {
				$this->image->getGmagick()->stripimage();  // make sure all profiles are removed
			}
		}
	}

}
