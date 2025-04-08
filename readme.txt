=== Edge Images ===
Contributors: jonoaldersonwp
Tags: images, optimization, cdn, cloudflare, performance
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 5.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Turbocharge your WordPress images by using an edge provider (like Cloudflare or Accelerated Domains) and optimizing your HTML markup.

== Description ==

Edge Images automatically uses your edge transformation service (e.g., Cloudflare, Accelerated Domains, Imgix, etc.) to apply performance optimizations to `<img>` markup.

⚠️ **Important**: This plugin requires a supported edge provider with image transformation features enabled (e.g., Cloudflare Pro, BunnyCDN, etc). See the Requirements section for details.

= 🚀 Why should I use Edge Images? =

* **Instant Performance Boost**: Automatically optimize and serve images in modern formats (WebP/AVIF) through your existing CDN
* **Zero Configuration**: Works out of the box with your existing images and themes
* **No Local Processing**: All transformations happen at the edge - no server load or storage overhead
* **Perfectly Sized Images**: Automatically generates the exact image dimensions needed for every device and viewport
* **Cost Effective**: No need for expensive image optimization services or additional storage

= 🎯 Perfect For =

* Sites with lots of images that need optimization
* Performance-focused developers and site owners
* Anyone using Cloudflare, BunnyCDN, or similar services
* Sites that want modern image formats without the complexity
* Developers tired of managing multiple image sizes

= 💡 How It Works =

WordPress typically creates multiple copies of each uploaded image in different sizes. This approach is inefficient and often results in:

* Images that are too large or small for their display size
* Unnecessary storage usage
* Missing sizes for modern responsive designs
* Lack of modern format support (WebP/AVIF)

Edge Images solves these problems by:

1. Intercepting image requests
2. Determining the optimal size and format needed
3. Using your CDN to transform the image on-demand
4. Caching the result for future requests

= ⚡️ Features =

**Core Features**

* Automatic WebP/AVIF conversion
* Intelligent responsive image handling
* Smart `srcset` generation
* Automatic image optimization
* Optional `<picture>` element wrapping
* Zero local processing
* Maintains original images

**Advanced Features**

* Fine-grained transformation control
* Multiple CDN provider support
* Developer-friendly filters
* Yoast SEO & Rank Math integrations
* Bricks integration
* Gutenberg compatibility

= 🔌 Supported Providers =

* **Cloudflare** (Pro plan or higher)
* **Accelerated Domains**
* **BunnyCDN**
* **Imgix**

= 🛠️ Technical Example =

**Your Code**

`
echo wp_get_attachment_image(1, [640,400], false, ['fit' => 'contain']);
`

**What WordPress Usually Outputs**

`
<img width="380" height="400" 
     src="/uploads/2024/11/1.jpg" 
     class="attachment-640x400 size-640x400 wp-image-123" 
     srcset="/uploads/2024/11/1.jpg 400w, /uploads/2024/11/1-285x300.jpg 285w" 
     sizes="(max-width: 640px) 100vw, 640px">
`

That's multiple different images files, none of which are the right size!

**What Edge Images Outputs**

`
<picture class="edge-images-container" style="--max-width: 640px;">
  <img 
       class="attachment-1140x600 size-640x400 wp-image-123 edge-images-processed"
       width="640" height="400" 
       src="/cdn-cgi/image/f=auto,fit=contain,w=640,h=400/uploads/2024/11/1.jpg" 
       srcset="/cdn-cgi/image/f=auto,w=320,h=188/uploads/2024/11/1.jpg 320w,
               /cdn-cgi/image/f=auto,w=640,h=400/uploads/2024/11/1.jpg 640w,
               /cdn-cgi/image/f=auto,w=1280,h=800/uploads/2024/11/1.jpg 1280w"
       sizes="(max-width: 640px) 100vw, 640px">
</picture>
`

That's a range of perfectly sized options for different devices and viewports, automatically optimized images in modern formats, served from your CDN, futureproofed for supporting next-generation capabilities, and with no storage overheads.

= 🎨 Customization =

**Transform Parameters**
Control every aspect of image transformation with attributes like:
* `width`/`height`: Exact dimensions
* `fit`: Resizing behavior (contain, cover, crop)
* `quality`: Compression level
* `format`: Output format (auto, webp, avif)

**Filtering**
Disable transformations globally or selectively:

`
// Disable all transformations
add_filter('edge_images_disable', '__return_true');

// Disable for specific images
add_filter('edge_images_disable_transform', function($should_disable, $html) {
    if (strpos($html, 'example.jpg') !== false) {
        return true;
    }
    return $should_disable;
}, 10, 2);

// Override max width for constrained content
add_filter('edge_images_max_width', function($max_width) {
    // Example: Use a different max width for single posts
    if (is_single()) {
        return 800;
    }
    return $max_width;
});

// Customize srcset width multipliers
add_filter('edge_images_width_multipliers', function($multipliers) {
    // Add more granular steps between sizes
    return [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2];
});
`

= 🔧 Requirements =

**Essential**

* A supported edge provider with image transformation features enabled:
  * Cloudflare Pro plan or higher with Image Resizing enabled
  * Accelerated Domains with Image Resizing enabled
  * BunnyCDN with Image Processing enabled
  * Imgix with a configured source

**Technical**

* PHP 7.4 or higher
* WordPress 5.9 or higher

= ✅ Getting Started =

1. Install and activate the plugin
2. Go to Settings > Edge Images
3. Select your CDN provider
4. That's it! Your images will now be automatically optimized

= 🤝 Integrations =

**Yoast SEO**
Automatically optimizes images in:

* Meta tags (og:image, etc.)
* Schema.org output
* XML sitemaps

= 🔒 Privacy =
Edge Images processes images through third-party edge providers. Here's what you need to know about privacy:

**Data Processing**

* Images are processed through your chosen edge provider (Cloudflare, Accelerated Domains, etc.)
* No personal data is collected or stored by the plugin itself
* Image URLs are passed to the edge provider for transformation
* Original images remain on your server; only public URLs are processed

**Edge Provider Privacy**
Different providers have different privacy implications:

* Cloudflare: Images are processed according to [Cloudflare's Privacy Policy](https://www.cloudflare.com/privacypolicy/)
* Accelerated Domains: Images are processed according to [Accelerated Domains' Privacy Policy](https://accelerateddomains.com/privacy/)
* BunnyCDN: Images are processed according to [BunnyCDN's Privacy Policy](https://bunny.net/privacy/)

**Data Storage**

* The plugin stores your selected settings in your WordPress database
* No user data is collected or stored
* No analytics or tracking is performed
* Cache files may be created in your uploads directory for optimization

**GDPR Compliance**

* The plugin is GDPR-compliant as it does not collect, store, or process personal data
* Users should review their chosen edge provider's privacy policy and data processing terms
* Site owners should update their privacy policy to reflect their use of third-party image processing services

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/edge-images/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Edge Images to configure your provider

== Frequently Asked Questions ==

= Which edge providers are supported? =

Currently supported providers are:

* Cloudflare (Pro plan or higher required)
* Accelerated Domains
* BunnyCDN
* Imgix

= Do I need to generate different image sizes? =

No! The plugin handles image resizing on-the-fly through your edge provider.

= Will this work with my existing images? =

Yes, the plugin works with your existing media library images.

= Does this work with Gutenberg? =

Yes, the plugin fully supports the WordPress block editor.

= How can I report security bugs? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team help validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/wordpress/plugin/edge-images/vdp)

== Development ==

* [GitHub Repository](https://github.com/jonoalderson/edge-images)
* [Report Issues](https://github.com/jonoalderson/edge-images/issues) 

== Changelog ==

= 5.5.1 ( 08/04/2025 ) =
* FEATURE: Tested up to WP 6.8.
* MISC: Tidied up some code.

= 5.5 ( 10/03/2025) =
* BUGFIX: Prevented a layout-breaking bug for images in content, wrapped in picture els.

= 5.4.2 ( 23/02/2025 ) =
* BUGFIX: Prevent local transformations from outputting upscaled srcset values.

= 5.4 ( 23/02/2025 ) =
* FEATURE: Added support for transforming image URLs locally (performance warnings apply if you're using a lot of images and not using a CDN to cache them).

= 5.3.5 ( 13/02/2025 ) =
* BUGFIX: Tweaked the Bricks integration to improve SVG handling.
* FEATURE: Tweaked classes to make it clear when an image has been skipped.

= 5.3.0 ( 13/02/2025 ) =
* FEATURE: Add Rank Math integration
* BUGFIX: Fix Bricks integration

= 5.2.19 ( 12/02/2025 ) =
* FEATURE: Add caching to social images in the Yoast SEO integration, and tweak how small images are handled.

= 5.2.18 ( 12/02/2025 ) =
* FEATURE: Added an integration for Bricks (which disables transformations for SVGs).
* FEATURE: Added a filter for controlling the default quality of transformed images.

= 5.2.17 ( 07/02/2025 ) =
* BUGFIX: Fixed a caching bug when updating the plugin.

= 5.2.15 ( 07/02/2025 ) =
* BUGFIX: Fixed a fatal error where an attachment ID was not provided.

= 5.2.14 ( 05/02/2025 ) =
* BUGFIX: Prevented intentionally empty alt attributes from being removed.

= 5.2.13 ( 04/02/2025 ) =
* BUGFIX: Big improvements to consistency of srcset transformation on wp_get_attachment_image_srcset and similar.

= 5.2.12 ( 04/02/2025 ) =
* BUGFIX: Fixed a src regreggion bug introduced in 5.2.10.

= 5.2.11 ( 04/02/2025 ) =
* BUGFIX: Fixed a srcset transformation bug introduced in 5.2.10.

= 5.2.10 ( 04/02/2025 ) =
* BUGFIX: Fixed the transformation when wp_get_attachment_url, wp_get_attachment_image_srcset or wp_get_attachment_image_sizes were used directly.
* BUGFIX: Ensured that the cache is cleared when the transformation domain is changed.

= 5.2.9 ( 30/01/2025 ) =
* BUGFIX: Don't try to transform AVIF images in srcset attributes.
* BUGFIX: Correctly apply custom rewrite domains to src attributes in some edge cases.

= 5.2.7 ( 16/01/2025 ) =
* FEATURE: Added an admin setting for customizing the domain used for transformed images.

= 5.2.6 ( 10/01/2025 ) =
* FEATURE: Added a filter for customizing the width multipliers used for generating srcset variants (and disabled the 2.5x multiplier by default).
* FEATURE: Moved CSS to inline styles to avoid extra HTTP requests.

= 5.2.5 ( 09/01/2025 ) =
* BUGFIX: Fixed the XML sitemap integration.

= 5.2.4 ( 09/01/2025 ) =
* BUGFIX: Prevent fatal errors when attachment posts were updated.
* FEATURE: Disabled 'gravity' settings by default.
* FEATURE: Added some front-end CSS to unbreak the admin bar avatar.
* FEATURE: Disabled the htaccess cache feature on non-Apache systems.

= 5.2.3 =
* Removed some redundant error logging.
* Tweaked CSS to ensure correct positioning of SVGs inside picture elements.
* Added filtering capabilities.
* Overhauled the readme file.