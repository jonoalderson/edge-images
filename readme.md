# Edge Images

Route WordPress images through an edge provider (Cloudflare, Accelerated Domains, Bunny, Imgix, or Imgproxy) and rewrite markup for responsive delivery.

**Requires** PHP 8.0+, WordPress 6.4+, and a supported edge provider with image transformation enabled.

## How it works

A request is rewritten only when transformations are allowed: the `edge_images_disable` filter is off, the request is on the front end or in the block-editor canvas iframe, and a provider is selected and configured.

Layout width is decided before any URL is built:

1. Attachment images use the requested size, or the `width` and `height` attributes.
2. Content images use an inline `width:Npx` on the image or its figure when that differs from the attribute width. Height is scaled to keep the aspect ratio.
3. Gallery images use the `columns-N` class. Layout width is the image width divided by the column count.

`src`, `srcset`, and `sizes` are then rewritten with the WordPress HTML API. Core `srcset` is replaced. Candidates are 1x, 1.5x, and 2x of the layout width, and they stop at the original file width so nothing is upscaled. When `sizes` is missing, or the layout width came from an inline style, a single image gets `sizes="(max-width: {width}px) 100vw, {width}px"` (prefixed with `auto, ` when the image is lazy-loaded). A multi-column gallery gets `sizes="(max-width: 599px) 50vw, {100/columns}vw"`.

Picture wrapping is off until you enable it. While it is off, images keep their original tags and the layout stylesheet is not loaded. While it is on, bare images, including those from `wp_get_attachment_image`, are wrapped in `<picture class="edge-images-container">`. A content image that already sits in a `<figure>` is not wrapped; that figure gets `edge-images-figure` instead, and the containment rules stay on `<picture>` only. Gallery images stay unwrapped either way.

The edge URL is built from the full-size file. Defaults are `fit=cover`, `format=auto`, and quality `85`. SVGs and existing AVIF files keep their original URLs. Local avatars (files on this site) are rewritten at the avatar size with `fit=cover`. They are not picture-wrapped, and they are not localized. Remote avatars such as Gravatar are left alone.

Yoast SEO and Rank Math social, schema, and sitemap images go through the same URL helper at 1200×675 with `fit=cover`. Those URLs stay on the edge even when localization is on.

### Optional local copies

Localization is off until you enable **Store transformed images locally under Uploads**.

While it is on, the first view of an attachment still uses the edge URL and adds a job to `edge_images_localize_queue`. A cron task (`edge_images_process_localize_queue`) runs about once a minute and fetches up to three jobs. Each job saves the 1x, 1.5x, and 2x files under uploads and records them in the `_edge_images_sizes` attachment meta. The next request serves those local files. Editing or deleting the attachment deletes the files and the meta.

Local copies use origin storage and an extra fetch. On Cloudflare they might still be worth it: Image Resizing is billed on unique transformations per month, and a stored variant is only requested once instead of counting again each month. Leave this off when another provider already caches the transformed images cheaply.

### Optional picture wrapping

**Wrap bare images in a picture element** is off by default, because the extra element and its stylesheet can change existing layouts.

While it is on, the stylesheet in `assets/css/images.min.css` is loaded. `max-width`, `content-visibility`, and `contain-intrinsic-size` apply only to `picture.edge-images-container`. A figure that already wraps the image gets `edge-images-figure` and only the image display rules, so captions and full-width alignments keep the figure's own box. A linked image with no figure becomes `<a><picture><img></picture></a>`.

## Features

* Markup rewrite for `wp_get_attachment_image`, content, excerpts, widgets, and local avatars
* Layout width from the requested size, attributes, block-editor inline styles, or gallery columns
* DPR `srcset` at 1x, 1.5x, and 2x, clamped to the original file
* Optional picture wrapping, off by default. Existing figures are marked instead of wrapped
* Providers: Cloudflare, Accelerated Domains, Bunny CDN, Imgix, Imgproxy
* Yoast SEO and Rank Math social, schema, and sitemap URLs at 1200×675, with a toggle per surface
* Settings status panel, admin notices, and a Plugins-screen settings link
* Optional cron-based local copies of transformed variants

## Getting started

1. Install and activate the plugin
2. Go to **Settings → Edge Images**
3. Select a provider:
   * **Cloudflare** or **Accelerated Domains**: selection is enough. The transform domain is optional and defaults to the site URL.
   * **Bunny CDN**: zone subdomain only, without `.b-cdn.net`
   * **Imgix**: source subdomain only, without `.imgix.net`
   * **Imgproxy**: full base URL of the Imgproxy instance
4. Check the status panel. It reads **Active**, **Inactive** (no provider), or **Needs attention** (provider selected but incomplete).
5. Optionally enable **Store transformed images locally under Uploads**

Choose **None** to stop rewriting without deactivating the plugin.

When Yoast SEO or Rank Math is active, the SEO integrations section lists it as detected and enables the social, schema, and sitemap checkboxes. A missing plugin stays listed and its checkboxes stay inactive.

## Public API

`Edge_Images\convert_src()` resolves a registered size or a `[width, height]` pair and returns `Transformer::url()`.

```php
Edge_Images\convert_src( $src, $size, $args = [] );
Edge_Images\Transformer::url( $src, $width, $height, $args = [], $dpr = 1.0 );
```

Filters that still run:

```php
add_filter( 'edge_images_disable', '__return_true' );
add_filter( 'edge_images_disable_transform', fn( $disable, $html ) => $disable, 10, 2 );
add_filter( 'edge_images_default_quality', fn() => 85 );
add_filter( 'edge_images_provider', fn( $provider ) => $provider );
add_filter( 'edge_images_domain', fn( $domain ) => $domain );
```

`edge_images_max_width` and `edge_images_width_multipliers` were removed in 6.0.0.

Transform arguments accepted on an image (short or long name) are `width`/`w`, `height`/`h`, `fit`, `format`/`f`, `quality`/`q`, `gravity`/`g`, and `sharpen`. Pass picture attributes through `wp_get_attachment_image()` as `$attr['picture']`. Those attributes are applied only while picture wrapping is enabled.

## Example output

With picture wrapping enabled, a bare image looks like this:

```html
<picture class="edge-images-container" style="--content-visibility: auto; --max-width: 640px; --width: 640px; --height: 400px">
  <img width="640" height="400"
       src="/cdn-cgi/image/dpr=1,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg"
       srcset="/cdn-cgi/image/dpr=1,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg 640w, /cdn-cgi/image/dpr=1.5,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg 960w, /cdn-cgi/image/dpr=2,f=auto,fit=cover,h=400,q=85,w=640/uploads/2024/11/1.jpg 1280w"
       sizes="(max-width: 640px) 100vw, 640px">
</picture>
```

## Privacy

The plugin stores settings in the WordPress options table. With localization enabled it also stores the queue option `edge_images_localize_queue`, writes transformed files under uploads, and stores their paths in attachment meta `_edge_images_sizes`. Uninstall removes the options and the cron hook. Localized files and that attachment meta are left in place.

Image URLs are sent to your chosen edge provider for transformation. The plugin does not collect personal data.

## Changelog

### 6.0.0

* BREAKING: Rebuild around markup and URL rewriting. The HTML API rewrites `wp_get_attachment_image`, content, excerpts, widgets, and local avatars. Layout width comes from the requested size, width and height attributes, an inline `width:Npx` on the image or its figure, or the gallery column count. `srcset` is 1x / 1.5x / 2x of that width, clamped so candidates never upscale past the original file.
* FEATURE: Picture wrapping is optional and off by default. While it is off, no picture element is added and the layout stylesheet is not loaded. While it is on, bare images are wrapped in `picture.edge-images-container`. An image that already sits in a figure gets the `edge-images-figure` class on that figure instead. Containment CSS stays on picture elements only.
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
