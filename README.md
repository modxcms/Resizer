Resizer v0.3.0-pl
==========

A lightweight, modern image resizer for MODX. Built on [Imagine](https://github.com/avalanche123/Imagine), Resizer supports the Gmagick, Imagick and GD extensions and is considerably faster than phpThumb for image sizing and cropping operations. Available from the MODX [Extras Repo](http://modx.com/extras/package/resizer).

Requirements
-----------

* PHP 5.3.2 or higher
* One or more of the following PHP extensions: Gmagick, Imagick, GD2

Usage
-----

Resizer is a PHP class and can only be called from a snippet.  If you’re not developing your own snippet you’ll probably want to install a higher-level package which supports Resizer, like [pThumb](https://github.com/oo12/phpThumbOf).

### pThumb

Change the ```phpthumbof.use_resizer``` system setting to Yes to enable Resizer globally, or use ```[[phpthumbof? &useResizer=`1` ...]]``` for a particular instance.  See the Options section below for supported options.  Other than that, use it just like you always have.

### Snippet Developers

Sample code:

    $modx->loadClass('Resizer', MODX_CORE_PATH . 'components/resizer/model/', true, true);
    $resizer = new Resizer($modx);  // pass in the modX object
    $resizer->debug = TRUE;  // (optional) Enable debugging messages.
    $resizer->processImage(
    	'/full/path/input-image.png',  // input image file. Path can be absolute or relative to MODX_BASE_PATH
    	'relative/path/output-image.jpg',  // output image file. Extension determines image format
        array('w' => 600, 'scale' => 1.5)  // or 'w=600&scale=1.5' instead of an array
    );
    // (optional) Write debug message array to the MODX error log
    $modx->log(modX::LOG_LEVEL_ERROR, 'Resizer debug output' . substr(print_r($resizer->debugmessages, TRUE), 7, -2);



Options
--------

Resizer only supports a subset of [phpThumb options](http://phpthumb.sourceforge.net/demo/docs/phpthumb.readme.txt), the most useful and commonly used ones.  Many of phpThumb’s options are arguably better handled now or in the near future with CSS transforms and filters anyway, so I haven’t implemented these.  But if there’s one you’ve just _got_ to have, open an issue :-)

### Supported phpThumb Options

<table>
	<tr><th>Option</th><th>Description</th><th>Value/Unit</th></tr>
	<tr><td><b>w</b></td><td>max width</td><td>pixels</td></tr>
	<tr><td><b>h</b></td><td>max height</td><td>pixels</td></tr>
	<tr><td><b>wl</b></td><td>max width: landscape orientation. If an image is wider than it is tall, a value for <b>wl</b> will override one for <b>w</b>. This and the following 5 options are broken in phpThumb but work in Resizer.</td><td>pixels</td></tr>
	<tr><td><b>hl</b></td><td>max height: landscape orientation</td><td>pixels</td></tr>
	<tr><td><b>wp</b></td><td>max width: portrait orientation</td><td>pixels</td></tr>
	<tr><td><b>hp</b></td><td>max height: portrait orientation</td><td>pixels</td></tr>
	<tr><td><b>ws</b></td><td>max width: square</td><td>pixels</td></tr>
	<tr><td><b>hs</b></td><td>max height: square</td><td>pixels</td></tr>
	<tr><td><b>sw</b></td><td>width — source rectangle. sw and sh crop an image without resizing it. They may be used together with w, h and their variants. For this and the following three options, values 1 or greater are interpreted as pixels and values between 0 and 1 as percentages, similar to phpThumb. Default: 100%</td><td>pixels (or % if &lt; 1)</td></tr>
	<tr><td><b>sh</b></td><td>height — source rectangle. Default: 100%</td><td>pixels (or % if &lt; 1)</td></tr>
	<tr><td><b>sx</b></td><td>left side of source rectangle. sx and sy control the starting point for the crop box. Resizer will adjust this value if needed to keep the right side from going past the opposite edge of the image. Default: center crop</td><td>pixels (or % if &lt; 1)</td></tr>
	<tr><td><b>sy</b></td><td>top side of source rectangle. Default: center crop</td><td>pixels (or % if &lt; 1)</td></tr>
	<tr><td><b>zc</b></td><td>Zoom Crop. Sizes an image to fill the given box (both a width and a height must be specified) and crops off any extra.  The value indicates the portion of the image you’d like to retain: top left, center, bottom right, etc. (You can also use <b>1</b> for center.) Unlike with phpThumb, all these options work with GD as well.</td><td><b>tl</b>, <b>t</b>, <b>tr</b><br><b>l</b>, <b>c</b>, <b>r</b><br><b>bl</b>, <b>b</b>, <b>br</b></td></tr>
	<tr><td><b>aoe</b></td><td>Allow Output Enlargement. Turning this on will allow the output image to be interpolated up if the requested size exceeds the resolution of the input image.</td><td><b>1</b> or <b>0</b> (default: <b>0</b>)</td></tr>
	<tr><td><b>q</b></td><td>JPEG quality</td><td>integer (default: <b>75</b>)</td></tr>
</table>

*Output file type* — Resizer doesn’t explicitly support phpThumb’s <b>f</b> option, but instead infers the proper image type from the output filename’s extension. Some wrappers like pThumb handle the <b>f</b> option in the usual way.<br>  Supported formats: jpg (or jpeg), png, gif, wbmp, xbm.

### New Options

<table>
	<tr><th>Option</th><th>Description</th><th>Value/Unit</th></tr>
	<tr><td><b>scale</b></td><td>Convenient when creating retina images. Any dimensions given will be multiplied by this number internally. If <b>aoe</b> is off—the default—and the input image doesn’t have sufficient resolution, <b>scale</b> will be adjusted downward so you get as much output resolution as possible without scaling the image up.</td><td>number &gt; 1</td></tr>
	<tr><td><b>qmax</b></td><td>An upper limit jpeg quality. If the requested size exceeds the input image resolution and <b>aoe</b> is off, Resizer can increase the jpeg quality in an attempt to compensate. Quality begins at q (input resolution = output resolution) and reaches qmax when the input resolution is 1/2 of the requested output resolution.</td><td>1–100 (should be greater than q)</td></tr>
	<tr><td><b>strip</b></td><td>Convert the image to sRGB, then strip the color profile and EXIF info.  An embedded profile and EXIF info can add 10KB or more to an image.</td><td><b>1</b> (on)</td></tr>
</table>


Settings
--------

Resizer provides one system setting, ```resizer.graphics_library```.  Normally you’ll leave this set at **2** (auto).  In that case Resizer will check the following extensions in this order and pick the first one it finds: Gmagick, Imagick, GD. Setting it to **1** will restrict it to Imagick or GD, **0** to GD only.


An Example with Retina Images
-----------------------

**scale** and **qmax** are designed to help when working with high-dpi (“Retina”) images.  As an example, say you have several images on a page which should each be 600px x 400px but you’d like to increase the images’ natural size to look good on a retina display.  You want to use the trick of a large—2x, say—image size with a low quality setting in order to get sharper images without making them too big.  However, the client is suppling the images and they may not all be a minimum of 1200px x 800px. In fact some may be quite a bit smaller.  There's no point in scaling these up on the server, but reducing the jpeg quality will make them look worse than they do already.  Here’s where the new options come in.

You might use these options: **width**:600, **height**:400, **zc**:1, **scale**:2, **q**:55, **qmax**:75.

**scale** makes it easy to adjust the natural size.  If you later decide 1.6x will be better than 2x, just do a search and replace.  If you have multiple images and especially if they’re different sizes, this is much easier than figuring out what size each should be on the page, then multiplying each width and height by the new scale and editing all the **w** and **h** options.  **scale** will adjust downward if there’s not enough input resolution.  So if the original image is only 1000px x 700px **scale** will slide down to 1.67 and the output image will be 1000px x 667px.

**qmax** lets the jpeg quality slide up as if the input image isn’t big enough.  If it’s only a little too small then **q** will increase only a little. Say the client uploads two images: one 1100px x 750px and one 300px x 200px.  The first one has almost all the resolution we need; it’ll come out 1100 x 733 with **q** set to 61.  The second image is way too small: 0.06 MP (megapixels) when we need 0.96 MP.  Since it’s less than 1/2 the resolution we’re looking for, its **q** is set to 75 (our **qmax** setting).