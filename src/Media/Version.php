<?php

declare(strict_types=1);

namespace Sloth\Media;

use Corcel\Model\Attachment;
use Sloth\Model\SlothMediaVersion;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image as SpatieImage;

class Version
{
    protected ?SlothMediaVersion $mediaVersion = null;

    public function __construct(string $url)
    {
        $this->mediaVersion = SlothMediaVersion::where('guid', 'like', '%' . $url)->first();
        if (!$this->mediaVersion) {
            return;
        }

        $original = Attachment::find($this->mediaVersion->parent_id);
        if (!$original) {
            return;
        }

        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        $realpath = realpath($uploadDir . DIRECTORY_SEPARATOR . $original->meta->_wp_attached_file);

        if (!$realpath) {
            return;
        }

        $options = $this->mediaVersion->options;
        if (empty($options)) {
            return;
        }

        $piRealpath = pathinfo($realpath);
        $piDest = pathinfo($url);
        $savedPath = $piRealpath['dirname'] . DIRECTORY_SEPARATOR . $piDest['basename'];

        if (file_exists($savedPath)) {
            $this->serveFile($savedPath);
        }

        $img = SpatieImage::load($realpath);

        if (($options['crop'] ?? false) === true) {
            $options['crop'] = [
                $options['width'],
                $options['height'],
            ];
            unset($options['width'], $options['height']);
        }
        unset($options['upscale']);

        foreach ($options as $k => $option) {
            if (is_callable([$img, $k])) {
                if (!is_array($option)) {
                    $option = [$option];
                }
                call_user_func_array([$img, $k], $option);
            }
        }

        $img->save($savedPath);

        $this->serveFile($savedPath);
    }

    protected function serveFile(string $path): void
    {
        $content = file_get_contents($path);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
