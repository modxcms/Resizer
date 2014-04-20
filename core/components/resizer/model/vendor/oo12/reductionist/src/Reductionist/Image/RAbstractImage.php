<?php
namespace Reductionist\Image;

use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;


abstract class RAbstractImage implements ImageInterface
{
	public $prescalesize;

	protected $filename;
	protected $image;
	protected $size;
	protected $format;
	protected $metadata;


	public function __construct($resource, $palette, $metadata, $size = null) {
		$this->metadata = $metadata;

		if (is_string($resource)) {  // file name
			$this->filename = $resource;
			if (false === $info = @getimagesize($resource)) {
				throw new \Imagine\Exception\RuntimeException("Unable to open image $resource.");
			}
			$this->size = array($info[0], $info[1]);
			$this->format = $info[2];
		}
	}


	public function getSize() {
		return new Box($this->size[0], $this->size[1]);
	}


	public function getPrescaleSize() {
		if (isset($this->prescalesize)) {
			return new Box($this->prescalesize[0], $this->prescalesize[1]);
		}
		else {
			return null;
		}
	}


	public function getImage() {
		if ($this->image === null) { $this->load(); }

		return $this->image;
	}


	public function getFormat() {
		return $this->format;
	}


	protected function updateSize() {
		$size = $this->image->getSize();
		$this->size = array($size->getWidth(), $size->getHeight());
	}



	/* from ImageInterface */

	public function get($format, array $options = array()) {
		return $this->image->get($format, $options);
	}

	public function __toString() {
		if ($this->image === null) { $this->load(); }

		return (string) $this->image;
	}

	public function draw() {
		if ($this->image === null) { $this->load(); }

		return $this->image->draw();
	}

	public function effects() {
		if ($this->image === null) { $this->load(); }

		return $this->image->effects();
	}

	public function mask() {
		if ($this->image === null) { $this->load(); }

		return $this->image->mask();
	}

	public function histogram() {
		if ($this->image === null) { $this->load(); }

		return $this->image->histogram;
	}

	public function getColorAt(\Imagine\Image\PointInterface $point) {
		if ($this->image === null) { $this->load(); }

		return $this->image->getColorAt($point);
	}

	public function layers() {
		if ($this->image === null) { $this->load(); }

		return $this->image->layers();
	}

	public function interlace($scheme) {
		if ($this->image === null) { $this->load(); }

		return $this->image->interlace();
	}

	public function palette() {
		if ($this->image === null) { $this->load(); }

		return $this->image->palette();
	}

	public function usePalette(\Imagine\Image\Palette\PaletteInterface $palette) {
		if ($this->image === null) { $this->load(); }

		$this->image->usePalette($palette);
		return $this;
	}

	public function profile(\Imagine\Image\ProfileInterface $profile) {
		if ($this->image === null) { $this->load(); }

		$this->image->profile($profile);
		return $this;
	}

	public function metadata() {
		return self::$emptyBag();
	}



	/* from ManipulatorInterface */

	public function copy() {
		if ($this->image === null) { $this->load(); }

		return $this->image->copy();
	}

	public function rotate($angle, \Imagine\Image\Palette\Color\ColorInterface $background = null) {
		if ($this->image === null) { $this->load(); }

		$this->image->rotate($angle, $background);
		$this->updateSize();
		return $this;
	}

	public function paste(ImageInterface $image, \Imagine\Image\PointInterface $start) {
		if ($this->image === null) { $this->load(); }

		$this->image->paste($image, $start);
		return $this;
	}

	public function save($path = null, array $options = array()) {
		if ($this->image === null) { $this->load(); }

		$this->image->save($path, $options);
		return $this;
	}

	public function show($format, array $options = array()) {
		if ($this->image === null) { $this->load(); }

		return $this->image->show($format, $options);
	}

	public function flipHorizontally() {
		if ($this->image === null) { $this->load(); }

		$this->image->flipHorizontally();
		return $this;
	}

	public function flipVertically() {
		if ($this->image === null) { $this->load(); }

		$this->image->flipVertically();
		return $this;
	}

	public function strip() {
		if ($this->image === null) { $this->load(); }

		$this->image->strip();
		return $this;
	}

	public function thumbnail(BoxInterface $size, $mode = 'inset', $filter = 'undefined') {
		if ($this->image === null) { $this->load(array($size->getWidth(), $size->getHeight())); }

		$this->image = $this->image->thumbnail($size, $mode, $filter);
		$this->updateSize();
		return $this;
	}

	public function applyMask(ImageInterface $mask) {
		if ($this->image === null) { $this->load(); }

		$this->image->applyMask($mask);
		return $this;
	}

	public function fill(\Imagine\Image\Fill\FillInterface $fill) {
		if ($this->image === null) { $this->load(); }

		$this->image->fill($fill);
		return $this;
	}
}
