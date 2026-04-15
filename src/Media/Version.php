<?php

declare(strict_types=1);

namespace Sloth\Media;

use Corcel\Model\Attachment;
use Sloth\Model\SlothMediaVersion;
use Spatie\Image\Image as SpatieImage;

/**
 * Image Version Generator
 *
 * This class handles on-the-fly generation of image versions based on
 * configuration stored in WordPress meta fields. It uses the Spatie Image
 * library to apply transformations like cropping, resizing, and format
 * conversion.
 *
 * ## How It Works
 *
 * 1. Looks up the version configuration by URL in SlothMediaVersion
 * 2. Loads the original image from the WordPress uploads directory
 * 3. Applies transformations defined in the version options
 * 4. Caches the result to avoid repeated processing
 * 5. Serves the processed image
 *
 * ## Version Options
 *
 * The version configuration (stored as JSON in SlothMediaVersion) supports
 * these Spatie Image methods:
 * - width, height: Resize dimensions
 * - crop: Crop to specific dimensions [width, height]
 * - format: Output format (jpeg, png, webp, etc.)
 * - quality: Compression quality (0-100)
 * - orientation: Auto-orientation based on EXIF data
 * - flip, flopp: Mirror horizontally/vertically
 *
 * ## URL Structure
 *
 * Versioned images are requested via URL with size info encoded:
 * /path/to/image-300x200.jpg
 *
 * @see https://github.com/spatie/image Spatie Image library
 */
class Version
{
    /**
     * The SlothMediaVersion model instance for this version.
     *
     * Contains the URL and transformation options for this image version.
     *
     * @var SlothMediaVersion|null
     */
    protected ?SlothMediaVersion $mediaVersion = null;

    /**
     * Create a new image version.
     *
     * Accepts the versioned image URL, looks up the original image,
     * applies transformations, and serves the result.
     *
     * The URL typically contains size information in the filename:
     * original-image-300x200.jpg
     *
     * @param string $url The versioned image URL to process
     *
     * @return void
     */
    public function __construct(string $url)
    {
        // Step 1: Look up version configuration by URL
        $this->mediaVersion = SlothMediaVersion::where('guid', 'like', '%' . $url)->first();

        // Exit early if version configuration not found
        if (!$this->mediaVersion) {
            return;
        }

        // Step 2: Find the original attachment
        $original = Attachment::find($this->mediaVersion->parent_id);

        // Exit early if original attachment not found
        if (!$original) {
            return;
        }

        // Step 3: Build path to original image file
        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        // Get the original file path from meta field
        $realpath = realpath($uploadDir . DIRECTORY_SEPARATOR . $original->meta->_wp_attached_file);

        // Exit early if file doesn't exist
        if (!$realpath) {
            return;
        }

        // Step 4: Load transformation options
        $options = $this->mediaVersion->options;

        // Exit early if no options configured
        if (empty($options)) {
            return;
        }

        // Step 5: Determine where to save the processed image
        // We preserve the URL structure in the saved filename for caching
        $piRealpath = pathinfo($realpath);
        $piDest = pathinfo($url);
        $savedPath = $piRealpath['dirname'] . DIRECTORY_SEPARATOR . $piDest['basename'];

        // Step 6: If already processed, serve cached version
        if (file_exists($savedPath)) {
            $this->serveFile($savedPath);
        }

        // Step 7: Load image with Spatie Image
        $img = SpatieImage::load($realpath);

        // Step 8: Normalize crop option
        //
        // Spatie's crop() expects [width, height] array, but we store
        // it as separate width/height fields with a boolean crop flag.
        // Convert to the array format Spatie expects.
        if (($options['crop'] ?? false) === true) {
            $options['crop'] = [
                $options['width'],
                $options['height'],
            ];
            // Remove separate width/height after merging into crop
            unset($options['width'], $options['height']);
        }

        // Step 9: Remove options that shouldn't be passed as methods
        // 'upscale' is a boolean that Spatie handles internally
        unset($options['upscale']);

        // Step 10: Apply all configured transformations
        foreach ($options as $method => $value) {
            // Only call methods that exist on the Spatie Image object
            if (is_callable([$img, $method])) {
                // Normalize to array format for call_user_func_array
                if (!is_array($value)) {
                    $value = [$value];
                }

                // Cast numeric string values to integers.
                //
                // This is necessary because WordPress meta fields store
                // all values as strings. Spatie Image expects integers
                // for dimensions (width, height, crop, etc.).
                //
                // Example: '300' -> 300
                $value = array_map(function ($v) {
                    return is_numeric($v) ? (int) $v : $v;
                }, $value);

                // Call the Spatie Image method with the processed arguments
                call_user_func_array([$img, $method], $value);
            }
        }

        // Step 11: Save the processed image
        $img->save($savedPath);

        // Step 12: Serve the processed image to the browser
        $this->serveFile($savedPath);
    }

    /**
     * Serve a file to the browser.
     *
     * Reads the file contents, sets appropriate headers, and outputs
     * the file. This method terminates script execution after sending
     * the file.
     *
     * @param string $path Absolute path to the file to serve
     *
     * @return void
     *
     * @uses header() To set Content-Type and Content-Length headers
     * @uses file_get_contents() To read file contents
     * @uses finfo_file() To determine MIME type
     */
    protected function serveFile(string $path): void
    {
        // Read the entire file into memory
        $content = file_get_contents($path);

        // Determine the MIME type using PHP's fileinfo extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        // Set headers for proper browser handling
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($content));

        // Output the file contents
        echo $content;

        // Terminate script - image processing is complete
        exit;
    }
}
