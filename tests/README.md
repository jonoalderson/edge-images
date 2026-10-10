# Edge Images test suite

Run everything:

```bash
php tests/run-all.php
```

Or individually:

| Suite | Covers |
| --- | --- |
| `test-layout.php` | Style width, gallery columns, DPR clamp, sizes |
| `test-provider-urls.php` | Cloudflare + ACD URL shape |
| `test-providers-all.php` | All five providers + `is_configured` |
| `test-transformer.php` | Attachment/content/gallery/SVG/avatar/SEO HTML, picture toggle |
| `test-localizer.php` | Queue → fetch → local src/srcset → delete |
| `test-smoke.php` | Manual checklist as HTML assertions |

These are CLI tests with WordPress stubs and a vendored `WP_HTML_Tag_Processor`. They do **not** hit a live edge CDN.

## Live provider smoke (manual)

On a site with Image Resizing enabled:

1. Settings → Edge Images: provider + domain, localize off, picture off
2. Resized image block → view-source: edge URLs at editor width, no picture element
3. Enable picture wrapping. The same block's figure has `edge-images-figure` and is not wrapped in picture. A featured image from `wp_get_attachment_image` is wrapped in `picture.edge-images-container`
4. Gallery block → column sizes, no picture, no figure class, layout intact
5. Yoast or Rank Math social/schema/sitemap image URL at 1200×675
6. Enable localize, reload twice → local `-edge` files under uploads
