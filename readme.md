# Edge Images

Route WordPress images through an edge provider (Cloudflare, Accelerated Domains, Bunny, Imgix, or Imgproxy) and rewrite markup for responsive delivery.

**Requires** PHP 8.0+, WordPress 6.4+, and a supported edge provider with image transformation enabled.

## How it works

Edge Images rewrites attachment and content image markup so `src`, `srcset`, and `sizes` point at your edge transformation service. Layout width comes from the image attributes, block-editor inline styles (`style="width:400px"`), or gallery column count. Non-gallery images are wrapped in `<picture class="edge-images-container">`.

`srcset` uses 1x / 1.5x / 2x of that layout width, clamped so candidates never upscale past the original file.

Optional localization (off by default) fetches those edge variants into uploads and serves the local files once ready.

## Features

* Markup rewrite for `wp_get_attachment_image`, content, excerpts, widgets, and local avatars
* Block-editor display widths and gallery column-aware sizes
* Providers: Cloudflare, Accelerated Domains, Bunny CDN, Imgix, Imgproxy
* Yoast SEO and Rank Math social, schema, and sitemap URL rewriting (1200×675)
* Optional local caching of transformed variants

## Getting started

1. Install and activate the plugin
2. Go to **Settings → Edge Images**
3. Select your provider and enter any required domain or subdomain
4. Optionally enable **Store transformed images locally**

## Public API

```php
Edge_Images\convert_src( $src, $size, $args = [] );

// Filters
add_filter( 'edge_images_disable', '__return_true' );
add_filter( 'edge_images_disable_transform', fn( $disable, $html ) => $disable, 10, 2 );
add_filter( 'edge_images_default_quality', fn() => 85 );
add_filter( 'edge_images_provider', fn( $provider ) => $provider );
add_filter( 'edge_images_domain', fn( $domain ) => $domain );
```

## Example output

```html
<picture class="edge-images-container" style="--content-visibility: auto; --max-width: 640px; --width: 640px; --height: 400px">
  <img width="640" height="400"
       src="/cdn-cgi/image/dpr=1,f=auto,fit=contain,h=400,q=85,w=640/uploads/2024/11/1.jpg"
       srcset="…/dpr=1,…/1.jpg 640w, …/dpr=1.5,…/1.jpg 960w, …/dpr=2,…/1.jpg 1280w"
       sizes="(max-width: 640px) 100vw, 640px">
</picture>
```

## Breaking changes in 6.0.0

* Dimension engine, feature registry, native provider, rewrites, HTML/htaccess caches, Relevanssi, Enable Media Replace, and Bricks integrations removed
* `srcset` is 1x / 1.5x / 2x (not the old width-multiplier ladder)
* Picture wrapping is always on for non-gallery images
* `edge_images_max_width` and `edge_images_width_multipliers` removed
* PHP 8.0 and WordPress 6.4 minimum

## Development

* [GitHub repository](https://github.com/jonoalderson/edge-images)
* [Report issues](https://github.com/jonoalderson/edge-images/issues)
* CLI tests: `php tests/test-layout.php` and `php tests/test-provider-urls.php`

## Security

Report security bugs through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/wordpress/plugin/edge-images/vdp).
