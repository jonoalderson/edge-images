# Edge Images

Edge Images automatically uses your edge transformation service (e.g., Cloudflare, Accelerated Domains, Imgix, etc.) to apply performance optimizations to `<img>` markup.

⚠️ **Important**: This plugin requires a supported edge provider with image transformation features enabled (e.g., Cloudflare Pro, BunnyCDN, etc). See the Requirements section for details.

## 🚀 Why should I use Edge Images?

* **Instant Performance Boost**: Automatically optimize and serve images in modern formats (WebP/AVIF) through your existing CDN
* **Zero Configuration**: Works out of the box with your existing images and themes
* **No Local Processing**: All transformations happen at the edge - no server load or storage overhead
* **Perfectly Sized Images**: Automatically generates the exact image dimensions needed for every device and viewport
* **Cost Effective**: No need for expensive image optimization services or additional storage

## 🎯 Perfect For

* Sites with lots of images that need optimization
* Performance-focused developers and site owners
* Anyone using Cloudflare, BunnyCDN, or similar services
* Sites that want modern image formats without the complexity
* Developers tired of managing multiple image sizes

## 💡 How It Works

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

## ⚡️ Features

### Core Features

* Automatic WebP/AVIF conversion
* Intelligent responsive image handling
* Smart `srcset` generation
* Automatic image optimization
* Optional `<picture>` element wrapping
* Zero local processing
* Maintains original images

### Advanced Features

* Fine-grained transformation control
* Multiple CDN provider support
* Developer-friendly filters
* Yoast SEO integration
* Gutenberg compatibility

## 🛠️ Technical Example

**Your Code**
```php
echo wp_get_attachment_image(1, [640,400], false, ['fit' => 'contain']);
```

**What WordPress Usually Outputs**
```html
<img width="380" height="400" 
     src="/uploads/2024/11/1.jpg" 
     class="attachment-640x400 size-640x400 wp-image-123" 
     srcset="/uploads/2024/11/1.jpg 400w, /uploads/2024/11/1-285x300.jpg 285w" 
     sizes="(max-width: 640px) 100vw, 640px">
```

That's multiple different images files, none of which are the right size!

**What Edge Images Outputs**
```html
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
```

That's a range of perfectly sized options for different devices and viewports, automatically optimized images in modern formats, served from your CDN, futureproofed for supporting next-generation capabilities, and with no storage overheads.

## 🎨 Customization

### Transform Parameters
Control every aspect of image transformation with attributes like:
* `width`/`height`: Exact dimensions
* `fit`: Resizing behavior (contain, cover, crop)
* `quality`: Compression level
* `format`: Output format (auto, webp, avif)

### Filtering
Disable transformations globally or selectively:

```php
// Disable all transformations
add_filter('edge_images_disable', '__return_true');

// Disable for specific images
add_filter('edge_images_disable_transform', function($should_disable, $html) {
    if (strpos($html, 'example.jpg') !== false) {
        return true;
    }
    return $should_disable;
}, 10, 2);
```

## 🔧 Requirements

### Essential
* A supported edge provider with image transformation features enabled:
  * Cloudflare Pro plan or higher with Image Resizing enabled
  * Accelerated Domains with Image Resizing enabled
  * BunnyCDN with Image Processing enabled
  * Imgix with a configured source

### Technical
* PHP 7.4 or higher
* WordPress 5.9 or higher

## ✅ Getting Started

1. Install and activate the plugin
2. Go to Settings > Edge Images
3. Select your CDN provider
4. That's it! Your images will now be automatically optimized

## 🤝 Integrations

### Yoast SEO
Automatically optimizes images in:
* Meta tags (og:image, etc.)
* Schema.org output
* XML sitemaps

## 🔒 Privacy

Edge Images processes images through third-party edge providers. Here's what you need to know about privacy:

### Data Processing
* Images are processed through your chosen edge provider (Cloudflare, Accelerated Domains, etc.)
* No personal data is collected or stored by the plugin itself
* Image URLs are passed to the edge provider for transformation
* Original images remain on your server; only public URLs are processed

### Edge Provider Privacy
Different providers have different privacy implications:
* Cloudflare: Images are processed according to [Cloudflare's Privacy Policy](https://www.cloudflare.com/privacypolicy/)
* Accelerated Domains: Images are processed according to [Accelerated Domains' Privacy Policy](https://accelerateddomains.com/privacy/)
* BunnyCDN: Images are processed according to [BunnyCDN's Privacy Policy](https://bunny.net/privacy/)

### Data Storage
* The plugin stores your selected settings in your WordPress database
* No user data is collected or stored
* No analytics or tracking is performed
* Cache files may be created in your uploads directory for optimization

### GDPR Compliance
* The plugin is GDPR-compliant as it does not collect, store, or process personal data
* Users should review their chosen edge provider's privacy policy and data processing terms
* Site owners should update their privacy policy to reflect their use of third-party image processing services

## Development

* [Report Issues](https://github.com/jonoalderson/edge-images/issues)
