Automatically converts image markup to use an edge transformer, and performance optimized layout + CSS logic.

Intercepts various flavors of WordPress' native `wp_get_attachment_image()` and similar, and:
- Uses named (or h/w array value) sizes as lookups for custom behaviour.
- Wraps the `<img>` in a `<picture>` elem.

Before:

After:

## Filters

### Enabling/disabling
- `edge_images_disable` (`bool`): Disable all image transformation mechanisms. Defaults to `false`.
- `edge_images_exclude` (`array`): An array of images to exclude from transformation.
- `edge_images_force_transform` (`bool`): Forcibly enable transformation, even if environmental settings would otherwise disable it (e.g., if a site is in a local environment). Defaults to `false`.

### General configuration
- `edge_images_provider` (`str`): The name of the edge provider to use. Defaults to `Cloudflare`.
- `edge_images_domain` (`str`): The fully qualified domain name (and protocol) to use to as the base for image transformation. Defaults to `get_site_url()`.
- `edge_images_content_width` (`int`): The default maximum content width for an image. Defaults to the theme's `$content_width` value, or falls back to `600`.

### Image quality settings
- `edge_images_quality_low` (`int`): The value to use for low quality images (from `1`-`100`). Defaults to `65`.
- `edge_images_quality_medium` (`int`): The value to use for low quality images (from `1`-`100`). Defaults to `75`.
- `edge_images_quality_high` (`int`): The value to use for low quality images (from `1`-`100`). Defaults to `85`.

### `srcset` generation settings
- `edge_images_step_value` (`int`): The number of pixels to increment in `srcset` variations. Defaults to `100`.
- `edge_images_min_width` (`int`): The minimum width to generate in an `srcset`. Defaults to `400`.
- `edge_images_max_width` (`int`): The maximum width to generate in an `srcset`. Defaults to `2400`.

### Specifying image sizes
The `edge_images_sizes` filter is used to define an associative array of named image sizes.
These accept the following properties.

#### Required
- `height` (`int`): The height in pixels of the image of the smallest/mobile/default size. Sets the `height` attribute on the `<img>` elem.
- `width` (`int`): The `width` in pixels of the image of the smallest/mobile/default size. Sets the `width` attribute on the `<img>` elem.

#### Optional
- `sizes` (`str`):  The `sizes` attribute to be used on the `<img>` elem.
- `srcset` (`arr`): An array of `width`/`height` arrays. Used to generate the `srcset` attribute (and stepped variations) on the `<img>` elem.
- `fit` (`str`): Sets the `fit` attribute on the `<img>` elem. Defaults to `cover`.
  - Options differ by edge providers (see https://developers.cloudflare.com/images/image-resizing/url-format).
- `layout` (`str`): Determines how `<img>` markup should be generated, based on whether the image is `responsive` or has `fixed` dimensions. Defaults to `responsive`.
- `loading` (`str`): Sets the `loading` attribute on the `<img>` elem. Defaults to `lazy`.
- `decoding` (`str`): Sets the `decoding` attribute on the `<img>` elem. Defaults to `async`.
- `class` (`str`): Extends the `class` value(s) on the `<img>` elem.
  - Always outputs `attachment-%size% size-%size% edge-images-img` (where `%size%` is the sanitized image size name).
- `picture-class` (`str`): Extends the `class` value(s) on the `<picture>` elem.
  - Always outputs `layout-%layout% picture-%size% edge-images-picture image-id-%id%` (where `%size%` is the sanitized image size name, `%layout%` is the `layout` value, and `%id%` is the attachment ID).

#### Examples:
A general use-case, which defines dimensions, sizes, and custom `srcset` values.
```
$sizes['example_size_1'] = array(
  'width'   => 173,
  'height'  => 229,
  'sizes'   => '(max-width: 768px) 256px, 173px',
  'srcset'  => array(
    array(
      'width'  => 256,
      'height' => 229,
    )
  )
);
```

A simple small image.
```
$sizes['small_logo'] = array(
  'width'  => 70,
  'height' => 20,
  'sizes'  => '70px'
);
```

A simple small image, requested with a size array (of `[32, 32]`) instead of a named size.
```
$sizes['32x32'] = array(
  'width'  => 32,
  'height' => 32,
  'sizes'  => '32px'
);
```

A more complex use-case, which changes layout considerably at different viewport ranges (and has complex `sizes` and `srcset` values to support this).
```
$sizes['card'] = array(
  'width'  => 195,
  'height' => 195,
  'sizes'  => '(max-width: 1120px) 25vw, (min-width: 1121px) and (max-width: 1440px) 150px, 195px',
  'srcset' => array(
    array(
      'width'  => 150,
      'height' => 150,
    ),
    array(
      'width'  => 125,
      'height' => 125,
    ),
    array(
      'width'  => 100,
      'height' => 100,
    ),
  ),
  'loading' => 'eager',
  'picture-class' => 'pineapple pizza'
);

```

## Misc notes
- SVG formats bypass `srcset` and generation, and have modified outputs.
