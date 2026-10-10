# Edge Images

Turbocharge your WordPress images by using an edge provider (like Cloudflare or Accelerated Domains) and optimizing your HTML markup.

Edge Images uses your edge transformation service (Cloudflare, Accelerated Domains, Bunny CDN, Imgix, or Imgproxy) to optimize the `<img>` markup already in your posts, templates, and media library.

⚠️ **Important**: You need a supported edge provider with image transformation enabled (Cloudflare Image Resizing, Bunny CDN image processing, Imgix, Imgproxy, or Accelerated Domains). See Requirements below.

## 🚀 Why should I use Edge Images?

* **Instant performance boost**: Optimize and serve images in modern formats (WebP or AVIF, when your provider supports `format=auto`) through the CDN you already use
* **Works with what you have**: Existing images, existing themes, and the block editor. Select a provider and the markup is rewritten for you
* **No local processing by default**: Transformations happen at the edge. Your server does not resize images, and it stores extra files only if you opt in
* **Right-sized images**: `srcset` offers 1x, 1.5x, and 2x of the width the layout actually uses, and it never asks for a file larger than the original
* **Cost effective**: Use the image transformation on your existing edge provider, instead of a separate optimization service or a stack of generated thumbnails

## 🎯 Perfect for

* Sites with lots of images that need optimization
* Performance-focused developers and site owners
* Anyone using Cloudflare, Accelerated Domains, Bunny CDN, Imgix, or Imgproxy
* Sites that want modern image formats without a local conversion pipeline
* Developers tired of managing multiple image sizes

## 💡 How it works

WordPress typically creates multiple copies of each uploaded image in different sizes. That often means:

* Images that are too large or too small for their display size
* Unnecessary storage usage
* Missing sizes for the layout you actually shipped
* No modern format support (WebP/AVIF) unless you built a pipeline for it

Edge Images takes a different route:

1. It rewrites image markup on the front end and in the block-editor canvas: attachments, content, excerpts, widgets, and local avatars
2. It decides the layout width from the requested size, the width and height attributes, an inline `width:Npx` on the image or its figure, or the gallery column count
3. It points `src`, `srcset`, and `sizes` at your edge provider, built from the full-size file
4. The provider transforms the image on demand and caches the result

Picture wrapping and local copies stay off until you turn them on.

## ⚡️ Features

### Core features

* Automatic WebP/AVIF conversion when your provider honors `format=auto`
* Layout-aware `srcset` at 1x, 1.5x, and 2x
* Rewrites `wp_get_attachment_image`, content, excerpts, widgets, and local avatars
* Optional `<picture>` wrapping, off by default, so existing layouts stay put
* Original images stay on your server
* Edge transformation by default, with no extra files until you ask for them

### Advanced features

* Transform arguments on an image: width, height, fit, quality, format, gravity, sharpen
* Cloudflare, Accelerated Domains, Bunny CDN, Imgix, and Imgproxy
* Developer-friendly filters and a small PHP helper
* Yoast SEO and Rank Math, with a toggle for social, schema, and sitemap images
* Block editor support
* A settings status panel (inactive, needs attention, or active) and a settings link on the Plugins screen
* Optional local copies of the transformed variants

## 🔌 Supported providers

* **Cloudflare** with Image Resizing enabled. Select it and you are done. The transform domain is optional and defaults to the site URL.
* **Accelerated Domains** with image transformation enabled. Selection is enough.
* **Bunny CDN** with image processing enabled. Zone subdomain only, without `.b-cdn.net`.
* **Imgix** with a configured source. Source subdomain only, without `.imgix.net`.
* **Imgproxy**. The full base URL of your instance.

Choose **None** to stop rewriting without deactivating the plugin.

## 🛠️ See the difference

**Your code**

```php
echo wp_get_attachment_image( 123, [ 640, 400 ] );
```

**What WordPress usually outputs**

```html
<img width="380" height="400"
     src="/uploads/2024/11/1.jpg"
     class="attachment-640x400 size-640x400 wp-image-123"
     srcset="/uploads/2024/11/1.jpg 400w, /uploads/2024/11/1-285x300.jpg 285w"
     sizes="(max-width: 640px) 100vw, 640px">
```

Several different files, and none of them is the size the layout needs.

**What Edge Images outputs**

```html
<img width="640" height="400"
     class="attachment-640x400 size-640x400 wp-image-123"
     src="/cdn-cgi/image/dpr=1,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg"
     srcset="/cdn-cgi/image/dpr=1,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg 640w, /cdn-cgi/image/dpr=1.5,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg 960w, /cdn-cgi/image/dpr=2,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg 1280w"
     sizes="(max-width: 640px) 100vw, 640px">
```

That is the layout width at 1x, 1.5x, and 2x, in a modern format when your provider can negotiate one, served from the edge, from the one original file. Candidates stop at the original file width, so nothing is upscaled. Lazy-loaded images get `auto, ` in front of `sizes` when Edge Images writes that attribute. A multi-column gallery uses `sizes="(max-width: 599px) 50vw, {100/columns}vw"`.

The example above is Cloudflare. Bunny CDN, Imgix, Imgproxy, and Accelerated Domains get the same dimensions with their own URL shape.

### Optional picture wrapping

Turn on **Wrap bare images in a picture element** and a bare image, including one from `wp_get_attachment_image()`, is wrapped:

```html
<picture class="edge-images-container" style="--content-visibility: auto; --max-width: 640px; --width: 640px; --height: 400px">
  <img width="640" height="400"
       src="/cdn-cgi/image/dpr=1,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg"
       srcset="..."
       sizes="(max-width: 640px) 100vw, 640px">
</picture>
```

An image that already sits in a `<figure>` keeps that figure. The figure gets `edge-images-figure`, and `max-width`, `content-visibility`, and `contain-intrinsic-size` stay on `picture.edge-images-container`, so captions and full-width alignments keep the figure's own box. Galleries stay unwrapped. The layout stylesheet loads only while this option is on. If Picture Element Wrapping was already saved in an earlier version, that choice is kept.

### Optional local copies

**Store transformed images locally under Uploads** is off by default. The first view still uses the edge URL and queues a job. About once a minute, a cron task (`edge_images_process_localize_queue`) fetches up to three jobs and saves the 1x, 1.5x, and 2x files under uploads. The next view serves those files. Editing or deleting the attachment removes them. Social, schema, and sitemap images stay on the edge.

On Cloudflare, local copies might reduce cost. Image Resizing is billed on unique transformations per month, so a stored variant is only requested once instead of counting again each month. Leave this off when your provider already caches transformed images cheaply.

## 🎨 Customization

### Transform parameters

Control the transformation with attributes on the image, or arguments to the URL helper. Short and long names both work:

* `width` / `w` and `height` / `h`: exact dimensions
* `fit`: scale-down, contain, cover, crop, or pad. The default is cover
* `quality` / `q`: compression level. The default is 85
* `format` / `f`: auto, webp, avif, jpeg, png, or gif
* `gravity` / `g` and `sharpen`

```php
echo wp_get_attachment_image( 123, [ 640, 400 ], false, [ 'fit' => 'contain', 'quality' => 75 ] );
```

Picture attributes can be passed as `$attr['picture']`. They apply only while picture wrapping is enabled.

### Filtering

```php
// Disable all transformations.
add_filter( 'edge_images_disable', '__return_true' );

// Skip one image.
add_filter( 'edge_images_disable_transform', function( $disable, $html ) {
	if ( str_contains( $html, 'example.jpg' ) ) {
		return true;
	}
	return $disable;
}, 10, 2 );

// Default quality.
add_filter( 'edge_images_default_quality', function() {
	return 75;
} );
```

`edge_images_provider` and `edge_images_domain` are also available.

`edge_images_max_width` and `edge_images_width_multipliers` were removed in 6.0.0. `srcset` is 1x, 1.5x, and 2x of the layout width.

### From PHP

```php
Edge_Images\convert_src( $src, $size, $args = [] );
Edge_Images\Transformer::url( $src, $width, $height, $args = [], $dpr = 1.0 );
```

`convert_src()` resolves a registered size, or a width and height pair, and returns `Transformer::url()`.

## 🔧 Requirements

### Essential

* A supported edge provider with image transformation enabled:
  * Cloudflare with Image Resizing enabled
  * Accelerated Domains with image transformation enabled
  * Bunny CDN with image processing enabled
  * Imgix with a configured source
  * Imgproxy with a reachable base URL

### Technical

* PHP 8.0 or higher
* WordPress 6.4 or higher

## ✅ Getting started

1. Install and activate the plugin
2. Go to **Settings → Edge Images**
3. Select your provider. Cloudflare and Accelerated Domains only need that selection. Bunny CDN, Imgix, and Imgproxy also need the subdomain or base URL
4. Confirm the status panel reads **Active**

Your images are optimized on the next front-end view. Picture wrapping and local copies are there when you want them.

When Yoast SEO or Rank Math is active, the settings screen lists it as detected and enables the social, schema, and sitemap checkboxes. A missing plugin stays listed and its checkboxes stay inactive.

## 🤝 Integrations

### Yoast SEO and Rank Math

When the matching plugin is active and its checkbox is on, social images, schema images, and XML sitemap images are rewritten at 1200×675 with `fit=cover`. Those URLs stay on the edge even when local copies are on.

## 🔒 Privacy

Edge Images processes images through your chosen edge provider. Here is what that means.

### Data processing

* Image URLs are passed to your chosen provider for transformation
* The plugin does not collect personal data
* Original images remain on your server. Only public URLs are processed
* Imgproxy sends those URLs to the instance you configure, which may be your own server

### Edge provider privacy

* Cloudflare: [Cloudflare's privacy policy](https://www.cloudflare.com/privacypolicy/)
* Accelerated Domains: [Servebolt's privacy policy](https://servebolt.com/legal/privacy/)
* Bunny CDN: [Bunny's privacy policy](https://bunny.net/privacy/)
* Imgix: [Imgix's privacy policy](https://www.imgix.com/privacy)

### Data storage

* Settings are stored in the WordPress options table
* With local copies enabled, the plugin also stores the queue option `edge_images_localize_queue`, writes transformed files under uploads, and stores their paths in attachment meta `_edge_images_sizes`
* Uninstall removes the options and the cron hook. Localized files and that attachment meta are left in place
* No analytics or tracking is performed

### GDPR

* The plugin does not collect, store, or process personal data
* Review your provider's privacy policy and data-processing terms
* Update your own privacy policy to mention third-party image processing when you use a hosted provider

## Frequently asked questions

### Which edge providers are supported?

Cloudflare (Image Resizing), Accelerated Domains, Bunny CDN, Imgix, and Imgproxy.

Cloudflare and Accelerated Domains need only a selection. Bunny CDN, Imgix, and Imgproxy also need the subdomain or base URL on the settings screen. The status panel stays on "Needs attention" until that value is saved.

### Do I need to generate different image sizes?

No. The edge provider resizes on demand from the full-size file. `srcset` offers 1x, 1.5x, and 2x of the layout width, and it never asks for a width larger than the original file.

### Will this work with my existing images?

Yes. The plugin rewrites markup for images already in your media library.

### Does this work with the block editor?

Yes. Markup is rewritten on the front end and in the block-editor canvas.

### Does this wrap images in picture?

Only after you enable "Wrap bare images in a picture element" under Settings → Edge Images. It is off by default. Bare images are then wrapped, and an image that already sits in a figure gets a class on that figure instead. Galleries are left alone. The layout stylesheet loads only while the option is on. A saved Picture Element Wrapping choice from an earlier version is kept.

### What about galleries?

Gallery images are rewritten with a column-based layout width and `sizes="(max-width: 599px) 50vw, {100/columns}vw"`. They are left unwrapped, so the core gallery layout keeps working.

### Can I store copies locally?

Only after you enable "Store transformed images locally under Uploads". The first page view still uses edge URLs and queues the variants. A cron task running about once a minute fetches them in batches of three. Reload after that job has run and the markup points at the files under uploads. Social, schema, and sitemap images stay on the edge.

On Cloudflare, local copies might reduce cost. Image Resizing is billed on unique transformations per month, so a stored variant is only requested once instead of counting again each month.

### What do Yoast and Rank Math change?

When the matching plugin is active, and its checkbox is on, social, schema, and sitemap image URLs are rewritten at 1200×675. The settings screen shows each plugin as detected or not detected. Checkboxes for a plugin that is not installed stay inactive.

### Are SVGs and avatars transformed?

SVGs and existing AVIF files keep their original URLs. Avatars that are files on this site are rewritten. Gravatar and other remote avatars are left alone. Avatars are not wrapped in `<picture>` and are not stored locally.

## Changelog

### 6.0.1

* Tested up to WordPress 7.1.
* Readme describes the 6.0 behavior in the directory listing again, and the short description fits the 150 character limit.

### 6.0.0

* BREAKING: Rebuild around markup and URL rewriting. The HTML API rewrites `wp_get_attachment_image`, content, excerpts, widgets, and local avatars. Layout width comes from the requested size, width and height attributes, an inline `width:Npx` on the image or its figure, or the gallery column count. `srcset` is 1x / 1.5x / 2x of that width, clamped so candidates never upscale past the original file.
* FEATURE: Picture wrapping is optional and off by default. While it is off, no picture element is added and the layout stylesheet is not loaded. While it is on, bare images are wrapped in `picture.edge-images-container`. An image that already sits in a figure gets the `edge-images-figure` class on that figure instead. Containment CSS stays on picture elements only. Upgrades keep a saved Picture Element Wrapping choice.
* BREAKING: Dimension engine, feature registry, native provider, rewrites, HTML and htaccess caches, Relevanssi, Enable Media Replace, and Bricks integrations removed.
* BREAKING: Requires PHP 8.0 and WordPress 6.4.
* BREAKING: `edge_images_max_width` and `edge_images_width_multipliers` removed. The width-multiplier `srcset` ladder is gone.
* FEATURE: Optional localization, off by default. The first view keeps the edge URL and queues a job. A one-minute cron writes the 1x / 1.5x / 2x files under uploads, three jobs at a time. Later requests serve those files. Editing or deleting the attachment removes them. On Cloudflare this might reduce Image Resizing cost, which is billed on unique transformations per month.
* FEATURE: Yoast SEO and Rank Math social, schema, and sitemap images use the shared URL helper at 1200×675. Those URLs stay on the edge even when localization is on. The settings screen detects each plugin and offers a toggle per surface. Toggles stay inactive until that plugin is active.
* FEATURE: Settings screen shows a status panel (inactive, needs attention, or active), warns elsewhere in admin when transformations cannot run, and adds a settings link on the Plugins screen.
* SVGs and existing AVIF files keep their original URLs. Local avatars are rewritten, and they are not picture-wrapped or localized.

### 5.5.7

* BUGFIX: Prevent fatal errors when image_downsize has been filtered.

## Development

* [GitHub repository](https://github.com/jonoalderson/edge-images)
* [Report issues](https://github.com/jonoalderson/edge-images/issues)
* CLI tests: `php tests/run-all.php` (see [tests/README.md](tests/README.md))

## Security

Report security bugs through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/wordpress/plugin/edge-images/vdp).
