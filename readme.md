# Edge Images
A WordPress plugin which automatically uses an edge transformation service (e.g., [Cloudflare](https://www.cloudflare.com/) or [Accelerated Domains](https://accelerateddomains.com/)) to apply performance optimizations to `<img>` markup.

## Features
- Automatic image optimization through edge providers
- Smart `srcset` generation for responsive images
- Support for multiple CDN/edge providers
- Automatic WebP/AVIF conversion (provider dependent)
- Yoast SEO integration for meta images
- No local image processing required
- No additional server load
- Maintains original images

## Version Compatibility
- WordPress: 5.6 or higher
- PHP: 7.4 or higher
- Gutenberg: Full support for image blocks
- Classic Editor: Full support

## Credits
- Author: Jono Alderson

## Description
Edge Images filters image blocks and template functions (such as `wp_get_attachment_image()` and `get_the_post_thumbnail()`), and:
  - Alters `src` and `srcset` attributes to use the edge provider's URL format.
  - Generates comprehensive `srcset` values, optimal `sizes` attributes, and applies general image optimizations.
  - Wraps the `<img>` in a `<picture>` elem (_optional_).

### What problem does this solve?
WordPress ships with a concept of "image sizes", each of which has a _height_, _width_ and _crop_ option. It provides some defaults like 'large', 'medium' and 'thumbnail', and provides ways for developers to customize or extend these options. When a user adds images to content, or includes them in templates, they must select the most optimal size from the options available.

This is often imprecise. Images are often loaded at 'roughly the right size', then shunk or stretched by the browser; by varying degrees of inaccuracy based on the user's context (such as viewport size, screen density, or content preferences). This is inefficient, and 'expensive' from a performance perspective.

WordPress attempts to mitigate this by generating `srcset` and `sizes` values in image markup. However, the accuracy of this is limited by the availability and size of pre-generated images - which is rarely sufficient.

This plugin solves these problems by providing suitable 'interstitial' `srcset` values, generated on-demand via an edge provider, without the need to pre-generate a large number of images.

## Requirements
- PHP 7.0+
- Domain must be served through a supported edge provider, with image resizing features available and enabled.
- Supported edge providers are:
  - [Cloudflare](https://www.cloudflare.com/), with the 'Image resizing' feature enabled (note that this requires a _Pro_ account or higher).
  - [Accelerated Domains](https://accelerateddomains.com/), with the 'Image resizing' feature enabled.
  - [BunnyCDN](https://bunny.net/), with the 'Image resizing' feature enabled.

## Examples

**PHP**
```php
echo wp_get_attachment_image(1, [640,400], false, ['fit' => 'contain', 'gravity' => 'left']);
```

**HTML output: Before**
```html
<img width="380" height="400" src="http://edge-images-plugin.local/wp-content/uploads/2024/11/1.jpg" class="attachment-640x400 size-640x400" alt="" fit="contain" gravity="left" decoding="async" loading="lazy" srcset="http://edge-images-plugin.local/wp-content/uploads/2024/11/1.jpg 400w, http://edge-images-plugin.local/wp-content/uploads/2024/11/1-285x300.jpg 285w" sizes="auto, (max-width: 380px) 100vw, 380px">
```

**HTML output: After**
```html
<picture class="edge-images-container contain" style="aspect-ratio: 8/5; --max-width: 640px;"><img width="640" height="400" src="http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=400&amp;q=85&amp;width=640" class="attachment-640x400 size-640x400 edge-images-img edge-images-processed" alt="" decoding="async" loading="lazy" srcset="http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=188&amp;q=85&amp;width=300 300w, http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=200&amp;q=85&amp;width=320 320w, http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=400&amp;q=85&amp;width=640 640w, http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=600&amp;q=85&amp;width=960 960w, http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=800&amp;q=85&amp;width=1280 1280w, http://edge-images-plugin.local/acd-cgi/img/v1/wp-content/uploads/2024/11/1.jpg?dpr=1&amp;f=auto&amp;fit=contain&amp;gravity=left&amp;height=1000&amp;q=85&amp;width=1600 1600w" sizes="(max-width: 640px) 100vw, 640px" data-attachment-id="11"></picture>
```

## Customization
The plugin automatically converts WordPress' native image sizes, any sizes registered via `add_image_size()`, and array values.

## Integrations
The plugin automatically integrates with the following systems and plugins.

### Yoast SEO
Automatically transforms images in:
- Meta tags (e.g., `og:image` and similar)
- Schema.org JSON-LD output (currently for the 'primary image of page' property only)
- XML sitemaps

Supports the following filters:
- `edge_images_yoast_disable` (`bool`): Disables the Yoast SEO integration. Defaults to `false`.
- `edge_images_yoast_disable_schema_images` (`bool`): Disables filtering images output in Yoast SEO schema. Defaults to `false`.
- `edge_images_yoast_disable_xml_sitemap_images` (`bool`): Disables filtering images output in Yoast SEO XML sitemaps. Defaults to `false`.
- `edge_images_yoast_disable_social_images` (`bool`): Disables filtering images output in Yoast social images (`og:image` and `twitter:image`). Defaults to `false`.
- `edge_images_yoast_social_image_args`: (`array`): Alters the args passed to the social image.

## Supported Attributes
When using `wp_get_attachment_image()` or similar functions, you can pass the following attributes to control image transformation:

### Core Parameters
- `width` or `w`: Width of the image in pixels
- `height` or `h`: Height of the image in pixels
- `fit`: Resizing behavior. Supported values:
  - `contain`: Resize to fit within dimensions while maintaining aspect ratio
  - `cover`: Resize to cover dimensions while maintaining aspect ratio
  - `crop`: Crop to exact dimensions
  - `scale-down`: Scale down to fit within dimensions
  - `pad`: Pad to exact dimensions
- `gravity` or `g`: Crop/focus position. Supported values:
  - `auto`: Automatic focus detection
  - `center`: Center of image
  - `north`: Top edge
  - `south`: Bottom edge
  - `east`: Right edge
  - `west`: Left edge
  - `left`: Left side
  - `right`: Right side
- `quality` or `q`: JPEG/WebP quality (1-100). Defaults to 85
- `format` or `f`: Output format. Supported values:
  - `auto`: Automatically select best format (default)
  - `webp`: Force WebP format
  - `jpeg`: Force JPEG format
  - `png`: Force PNG format
  - `avif`: Force AVIF format

### Advanced Parameters
- `dpr`: Device Pixel Ratio multiplier (1-3)
- `metadata`: Metadata handling. Supported values:
  - `keep`: Preserve all metadata
  - `copyright`: Keep only copyright info
  - `none`: Strip all metadata
- `blur`: Apply Gaussian blur (1-250)
- `sharpen`: Apply sharpening (1-100)
- `brightness`: Adjust brightness (-100 to 100)
- `contrast`: Adjust contrast (-100 to 100)
- `gamma`: Adjust gamma correction (1-100)

### Example Usage
```php
echo wp_get_attachment_image(
    $attachment_id,
    [800, 600],
    false,
    [
        'fit' => 'contain',
        'gravity' => 'center',
        'quality' => 90,
        'sharpen' => 10
    ]
);
```

This will output an 800x600 image that:
- Fits within the dimensions while maintaining aspect ratio
- Is centered in the frame
- Uses 90% JPEG quality
- Has slight sharpening applied

All parameters are optional and will use sensible defaults if not specified.

## Installation
1. Upload the plugin files to `/wp-content/plugins/edge-images/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Edge Images to configure your provider

## Provider-Specific Configuration

### Cloudflare
1. Ensure your domain is on a Cloudflare Pro plan or higher
2. Enable "Image Resizing" in your Cloudflare dashboard
3. Select "Cloudflare" in the Edge Images settings

### Accelerated Domains
1. Enable image optimization in your Accelerated Domains dashboard
2. Select "Accelerated Domains" in the Edge Images settings

### Bunny CDN
1. Enable image processing in your Bunny CDN dashboard
2. Select "Bunny CDN" in the Edge Images settings

### Imgix
1. Create an Imgix source for your domain
2. Enter your Imgix subdomain in the Edge Images settings
3. Select "Imgix" in the Edge Images settings

## Filters

### General Configuration
- `edge_images_disable` (`bool`): Disable all image transformation. Defaults to `false`.
- `edge_images_provider` (`string`): Override the selected provider. Accepts 'cloudflare', 'accelerated_domains', 'bunny', or 'imgix'.
- `edge_images_domain` (`string`): Override the domain used for image URLs. Defaults to site URL.
- `edge_images_disable_picture_wrap` (`bool`): Disable wrapping images in `<picture>` elements. Defaults to `false`.

### Image Processing
- `edge_images_max_width` (`int`): Maximum width for generated images. Defaults to 2400.
- `edge_images_min_width` (`int`): Minimum width for generated images. Defaults to 300.
- `edge_images_quality` (`int`): Default JPEG/WebP quality. Defaults to 85.

### Performance
- `edge_images_preloads` (`array`): Array of image IDs and sizes to preload.

## Troubleshooting

### Images Not Transforming
1. Check that your chosen provider is properly configured
2. Verify that image resizing is enabled in your provider's dashboard
3. Check the browser console for any JavaScript errors
4. Try disabling other image optimization plugins

### Performance Issues
1. Consider enabling browser caching for transformed images
2. Use the `edge_images_preloads` filter for critical images
3. Adjust quality settings for better compression

### Common Issues
- **404 Errors**: Ensure your provider's image transformation service is properly enabled
- **Broken Images**: Check that image paths are correctly formatted
- **Missing Transformations**: Verify that your provider supports the requested transformations
