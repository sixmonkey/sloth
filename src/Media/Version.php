<?php

declare(strict_types=1);

namespace Sloth\Media;

use Corcel\Model\Attachment;
use Sloth\Model\SlothMediaVersion;
use Spatie\Image\Enums\CropPosition;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image as SpatieImage;

/**
 * Media version handler for image manipulation.
 *
 * @since 1.0.0
 */
class Version
{
    /**
     * Media version model.
     *
     * @since 1.0.0
     * @var SlothMediaVersion|null
     */
    protected ?SlothMediaVersion $mv = null;

    /**
     * Version constructor.
     *
     * @param string $url The media URL
     * @throws CouldNotLoadImage
     * @since 1.0.0
     *
     */
    public function __construct(string $url)
    {
        $this->mv = SlothMediaVersion::where('guid', 'like', '%' . $url)->first();
        if (!$this->mv) {
            return;
        }

        $original = Attachment::find($this->mv->parent_id);
        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        $realpath = realpath($uploadDir . DIRECTORY_SEPARATOR . $original->meta->_wp_attached_file);

        if (!$realpath) {
            return;
        }

        $options = $this->mv->options;

        $piRealpath = pathinfo($realpath);
        $piDest = pathinfo($url);

        $img = SpatieImage::load($realpath);

        if ($options['crop'] === true) {
            $options['crop'] = [
                CropPosition::Center,
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

        $img->save($piRealpath['dirname'] . DIRECTORY_SEPARATOR . $piDest['basename']);

        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }
}
