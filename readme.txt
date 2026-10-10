=== Edge Images ===
Contributors: jonoaldersonwp
Tags: images, optimization, cdn, cloudflare, performance
Tested up to: 6.8
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 6.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Route WordPress images through Cloudflare, Accelerated Domains, Bunny, Imgix, or Imgproxy, and rewrite markup for responsive delivery.

== Description ==

A request is rewritten only when transformations are allowed: the disable filter is off, the request is on the front end or in the block-editor canvas iframe, and a provider is selected and configured.

Layout width is decided before any URL is built. Attachment images use the requested size, or the width and height attributes. Content images use an inline `width:Npx` on the image or its figure when that differs from the attribute width, and the height is scaled to keep the aspect ratio. Gallery images use the `columns-N` class: layout width is the image width divided by the column count.

`src`, `srcset`, and `sizes` are then rewritten with the WordPress HTML API. Core srcset is replaced. Candidates are 1x, 1.5x, and 2x of the layout width, and they stop at the original file width. When `sizes` is missing, or the layout width came from an inline style, a single image uses `sizes="(max-width: {width}px) 100vw, {width}px"` (prefixed with `auto, ` when the image is lazy-loaded). A multi-column gallery uses `sizes="(max-width: 599px) 50vw, {100/columns}vw"`.

Picture wrapping is off until you enable it. While it is off, images keep their original tags and the layout stylesheet is not loaded. While it is on, bare images, including those from `wp_get_attachment_image`, are wrapped in `<picture class="edge-images-container">`. A content image that already sits in a `<figure>` is not wrapped; that figure gets `edge-images-figure` instead, and the containment rules stay on `<picture>` only. Gallery images stay unwrapped either way.

The edge URL is built from the full-size file. Defaults are `fit=cover`, `format=auto`, and quality 85. SVGs and existing AVIF files keep their original URLs. Local avatars are rewritten at the avatar size with `fit=cover`. They are not picture-wrapped, and they are not localized. Remote avatars such as Gravatar are left alone.

Yoast SEO and Rank Math social, schema, and sitemap images use the same URL helper at 1200×675 with `fit=cover`. Those URLs stay on the edge even when localization is on.

Optional localization is off until you enable it. The first view of an attachment still uses the edge URL and queues a job. A cron task runs about once a minute and fetches up to three jobs, saving the 1x, 1.5x, and 2x files under uploads. The next request serves those local files. Editing or deleting the attachment removes them.

Local copies use origin storage and an extra fetch. On Cloudflare they might still be worth it: Image Resizing is billed on unique transformations per month, and a stored variant is only requested once instead of counting again each month. Leave this off when another provider already caches the transformed images cheaply.

**Wrap bare images in a picture element** is off by default, because the extra element and its stylesheet can change existing layouts. If Picture Element Wrapping was already saved in an earlier version, that choice is kept. While it is on, `max-width`, `content-visibility`, and `contain-intrinsic-size` apply only to `picture.edge-images-container`. A figure that already wraps the image gets `edge-images-figure` and only the image display rules. A linked image with no figure is wrapped inside the link.

⚠️ **Important**: You need a supported edge provider with image transformation enabled.

= Features =

* Markup rewrite for `wp_get_attachment_image`, content, excerpts, widgets, and local avatars
* Layout width from the requested size, attributes, block-editor inline styles, or gallery columns
* DPR `srcset` at 1x, 1.5x, and 2x, clamped to the original file
* Optional picture wrapping, off by default. Existing figures are marked instead of wrapped
* Providers: Cloudflare, Accelerated Domains, Bunny CDN, Imgix, Imgproxy
* Yoast SEO and Rank Math social, schema, and sitemap URLs at 1200×675, with a toggle per surface
* Settings status panel, admin notices, and a Plugins-screen settings link
* Optional cron-based local copies of transformed variants

= Requirements =

* PHP 8.0+
* WordPress 6.4+
* A supported edge provider with image transformation enabled

= Providers =

* **Cloudflare** or **Accelerated Domains**: select the provider. Image transformation must be enabled on the zone or service. The transform domain is optional and defaults to the site URL.
* **Bunny CDN**: zone subdomain only, without `.b-cdn.net`. Image processing must be enabled.
* **Imgix**: source subdomain only, without `.imgix.net`.
* **Imgproxy**: full base URL of your Imgproxy instance.

= Getting Started =

1. Install and activate the plugin
2. Go to Settings → Edge Images
3. Select your provider and fill in the subdomain or base URL when that provider asks for one
4. Confirm the status panel reads Active
5. Optionally enable “Store transformed images locally under Uploads”

Choose None to stop rewriting without deactivating the plugin.

When Yoast SEO or Rank Math is active, the settings screen lists it as detected and enables the social, schema, and sitemap checkboxes. A missing plugin stays listed and its checkboxes stay inactive.

= Public API =

* `Edge_Images\convert_src( $src, $size, $args = [] )` resolves a registered size or a width/height pair and returns `Transformer::url()`
* `Edge_Images\Transformer::url( $src, $width, $height, $args = [], $dpr = 1.0 )`
* Filters: `edge_images_disable`, `edge_images_disable_transform`, `edge_images_default_quality`, `edge_images_provider`, `edge_images_domain`
* Removed in 6.0.0: `edge_images_max_width`, `edge_images_width_multipliers`

Transform arguments on an image (short or long name): `width`/`w`, `height`/`h`, `fit`, `format`/`f`, `quality`/`q`, `gravity`/`g`, and `sharpen`. Picture attributes can be passed to `wp_get_attachment_image()` as `$attr['picture']`, and are applied only while picture wrapping is enabled.

= Privacy =

The plugin stores settings in the WordPress options table. With localization enabled it also stores the queue option `edge_images_localize_queue`, writes transformed files under uploads, and stores their paths in attachment meta `_edge_images_sizes`. Uninstall removes the options and the cron hook. Localized files and that attachment meta are left in place.

Image URLs are sent to your chosen edge provider for transformation. The plugin does not collect personal data.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/edge-images/`
2. Activate the plugin through the Plugins screen
3. Configure your provider under Settings → Edge Images

== Frequently Asked Questions ==

= Which providers are supported? =

Cloudflare (Image Resizing), Accelerated Domains, Bunny CDN, Imgix, and Imgproxy.

Cloudflare and Accelerated Domains need only a selection. Bunny, Imgix, and Imgproxy also need the subdomain or base URL on the settings screen. The status panel stays on “Needs attention” until that value is saved.

= Do I need multiple local image sizes? =

No. The edge provider resizes on demand from the full-size file. `srcset` offers 1x, 1.5x, and 2x of the layout width, and it never asks for a width larger than the original file.

= Does this wrap images in picture? =

Only after you enable “Wrap bare images in a picture element” under Settings → Edge Images. It is off by default. Bare images are then wrapped, and an image that already sits in a figure gets a class on that figure instead. Galleries are left alone. The layout stylesheet loads only while the option is on.

= What about galleries? =

Gallery images are rewritten with a column-based layout width and `sizes="(max-width: 599px) 50vw, {100/columns}vw"`. They are not wrapped in `<picture>`, so the core gallery layout keeps working.

= When do local copies appear? =

Only after you enable “Store transformed images locally under Uploads”. The first page view still uses edge URLs and queues the variants. A cron task running about once a minute fetches them in batches of three. Reload after that job has run and the markup points at the files under uploads. Social, schema, and sitemap images always stay on the edge.

On Cloudflare, local copies might reduce cost. Image Resizing is billed on unique transformations per month, so a stored variant is only requested once instead of counting again each month.

= What do Yoast and Rank Math change? =

When the matching plugin is active, and its checkbox is on, social, schema, and sitemap image URLs are rewritten at 1200×675. The settings screen shows each plugin as detected or not detected. Checkboxes for a plugin that is not installed stay inactive.

= Are SVGs and avatars transformed? =

SVGs and existing AVIF files keep their original URLs. Avatars that are files on this site are rewritten. Gravatar and other remote avatars are left alone. Avatars are not wrapped in `<picture>` and are not stored locally.

= How do I report security bugs? =

Use the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/wordpress/plugin/edge-images/vdp).

== Changelog ==

= 6.0.0 =
* BREAKING: Rebuild around markup and URL rewriting. The HTML API rewrites `wp_get_attachment_image`, content, excerpts, widgets, and local avatars. Layout width comes from the requested size, width and height attributes, an inline `width:Npx` on the image or its figure, or the gallery column count. `srcset` is 1x / 1.5x / 2x of that width, clamped so candidates never upscale past the original file.
* FEATURE: Picture wrapping is optional and off by default. While it is off, no picture element is added and the layout stylesheet is not loaded. While it is on, bare images are wrapped in `picture.edge-images-container`. An image that already sits in a figure gets the `edge-images-figure` class on that figure instead. Containment CSS stays on picture elements only. Upgrades keep a saved Picture Element Wrapping choice.
* BREAKING: Dimension engine, feature registry, native provider, rewrites, HTML and htaccess caches, Relevanssi, Enable Media Replace, and Bricks integrations removed.
* BREAKING: Requires PHP 8.0 and WordPress 6.4.
* BREAKING: `edge_images_max_width` and `edge_images_width_multipliers` removed. The width-multiplier `srcset` ladder is gone.
* FEATURE: Optional localization, off by default. The first view keeps the edge URL and queues a job. A one-minute cron writes the 1x / 1.5x / 2x files under uploads, three jobs at a time. Later requests serve those files. Editing or deleting the attachment removes them. On Cloudflare this might reduce Image Resizing cost, which is billed on unique transformations per month.
* FEATURE: Yoast SEO and Rank Math social, schema, and sitemap images use the shared URL helper at 1200×675. Those URLs stay on the edge even when localization is on. The settings screen detects each plugin and offers a toggle per surface. Toggles stay inactive until that plugin is active.
* FEATURE: Settings screen shows a status panel (inactive, needs attention, or active), warns elsewhere in admin when transformations cannot run, and adds a settings link on the Plugins screen.
* SVGs and existing AVIF files keep their original URLs. Local avatars are rewritten, and they are not picture-wrapped or localized.

= 5.5.7 =
* BUGFIX: Prevent fatal errors when image_downsize has been filtered.
