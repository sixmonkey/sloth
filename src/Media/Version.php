<?php

namespace Sloth\Media;

use Corcel\Model\Attachment;
use Sloth\Model\SlothMediaVersion;
use Spatie\Image\Image as SpatieImage;
use Spatie\Image\Manipulations;

class Version
{
    protected $mv;

    public function __construct($url)
    {
        $this->mv = SlothMediaVersion::where('guid', 'like', '%' . $url)->first();
        if (! $this->mv) {
            return;
        }

        $original    = Attachment::find($this->mv->parent_id);
        $upload_info = wp_upload_dir();
        $upload_dir  = realpath($upload_info['basedir']);

        $realpath = realpath($upload_dir . DIRECTORY_SEPARATOR . $original->meta->_wp_attached_file);

        if (! $realpath) {
            return;
        }

        $options = $this->mv->options;

        $pi_realpath = pathinfo($realpath);
        $pi_dest     = pathinfo($url);

        $img = SpatieImage::load($realpath);

        if ($options['crop'] === true) {
            $options['crop'] = [
                Manipulations::CROP_CENTER,
                $options['width'],
                $options['height'],
            ];
            unset($options['width'], $options['height']);
        }
        unset($options['upscale']);


        foreach ($options as $k => $option) {
            if (is_callable([$img, $k])) {
                if (! is_array($option)) {
                    $option = [$option];
                }
                call_user_func_array([$img, $k], $option);
            }
        }

        $img->save($pi_realpath['dirname'] . DIRECTORY_SEPARATOR . $pi_dest['basename']);

        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }
}
