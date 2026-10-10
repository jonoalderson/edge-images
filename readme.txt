=== Edge Images ===
Contributors: jonoaldersonwp
Tags: images, optimization, cdn, cloudflare, performance
Tested up to: 6.8
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 6.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Route WordPress images through an edge provider (Cloudflare, Accelerated Domains, Bunny, Imgix, or Imgproxy) and rewrite markup for responsive delivery.

== Description ==

Edge Images rewrites attachment and content image markup so `src`, `srcset`, and `sizes` point at your edge transformation service. Layout width comes from the image attributes, block-editor inline styles, or gallery column count. Images are wrapped in `<picture class="edge-images-container">` (except gallery images).

Optional localization (off by default) can fetch the 1x / 1.5x / 2x edge variants and serve local copies once they exist.

⚠️ **Important**: You need a supported edge provider with image transformation enabled.

= Features =

* Markup rewrite for `wp_get_attachment_image`, content, excerpts, widgets, and local avatars
* DPR `srcset` at 1x, 1.5x, and 2x (no upscaling past the original file)
* Block-editor display widths from inline `style="width:Npx"`
* Gallery column-aware layout widths without picture wrapping
* Providers: Cloudflare, Accelerated Domains, Bunny CDN, Imgix, Imgproxy
* Yoast SEO and Rank Math social, schema, and sitemap URL rewriting
* Optional local caching of transformed variants

= Requirements =

* PHP 8.0+
* WordPress 6.4+
* A supported edge provider with image transformation enabled

= Getting Started =

1. Install and activate the plugin
2. Go to Settings → Edge Images
3. Select your provider and enter any required domain or subdomain
4. Optionally enable “Store transformed images locally”

= Public API =

* `Edge_Images\convert_src( $src, $size, $args = [] )`
* Filters: `edge_images_disable`, `edge_images_disable_transform`, `edge_images_default_quality`, `edge_images_provider`, `edge_images_domain`

= Privacy =

Images are processed by your chosen edge provider. The plugin stores settings in the WordPress database. With localization enabled, transformed files may be written under uploads. No personal data is collected by the plugin.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/edge-images/`
2. Activate the plugin through the Plugins screen
3. Configure your provider under Settings → Edge Images

== Frequently Asked Questions ==

= Which providers are supported? =

Cloudflare (Image Resizing), Accelerated Domains, Bunny CDN, Imgix, and Imgproxy.

= Do I need multiple local image sizes? =

No. The edge provider resizes on demand from the full-size file.

= What about galleries? =

Gallery images are rewritten with column-based layout widths and sizes. They are not wrapped in `<picture>` so core gallery layout keeps working.

= How do I report security bugs? =

Use the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/wordpress/plugin/edge-images/vdp).

== Changelog ==

= 6.0.0 =
* BREAKING: Rebuild around markup and URL rewriting. Dimension engine, feature registry, native provider, rewrites, HTML/htaccess caches, Relevanssi, Enable Media Replace, and Bricks integrations removed.
* BREAKING: Requires PHP 8.0 and WordPress 6.4.
* BREAKING: `srcset` is 1x / 1.5x / 2x of the layout width (no width-multiplier ladder). `edge_images_max_width` and `edge_images_width_multipliers` removed.
* BREAKING: Picture wrapping is always on for non-gallery images.
* FEATURE: Layout width from block-editor inline styles and gallery columns.
* FEATURE: Optional localization checkbox (off by default) to fetch and serve local DPR variants.
* FEATURE: Slim settings screen (provider, domain/subdomain, localization).
* FEATURE: Yoast and Rank Math integrations use the shared URL helper at 1200×675.

= 5.5.7 =
* BUGFIX: Prevent fatal errors when image_downsize has been filtered.
