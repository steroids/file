<?php

namespace steroids\file\previews;

use Exception;
use steroids\file\structure\ResizeParameters;

class PreviewHelper
{
    public static function resizeImage($imageContent, $source, $previewExtension, ResizeParameters $parameters)
    {
        $bool = true;
        $src = imagecreatefromstring($imageContent);
        $extension = $previewExtension;

        // Auto-rotate
        if ($extension === 'jpg' || $extension === 'jpeg') {
            try {
                $exif = exif_read_data($source);
                rewind($source);
            } catch (Exception $e) {
            }

            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $src = imagerotate($src, 180, 0);
                        break;

                    case 6:
                        $src = imagerotate($src, -90, 0);
                        break;

                    case 8:
                        $src = imagerotate($src, 90, 0);
                        break;
                }
            }
        }

        // Create blank image
        $dst = ImageCreateTrueColor($parameters->width, $parameters->height);
        if ($extension === 'png') {
            $bool = $bool && imagesavealpha($dst, true) && imagealphablending($dst, false);
        }

        // Place, resize and save file
        $bool = $bool && imagecopyresampled($dst, $src,
                0, 0,
                $parameters->offsetX, $parameters->offsetY,
                $parameters->width, $parameters->height,
                $parameters->originalWidth, $parameters->originalHeight
            );

        if ($bool && $extension === 'png') {
            imagepng($dst, $source);
        } else {
            imageinterlace($dst, true);
            imagejpeg($dst, $source, $parameters->previewQuality);
        }

        // Clean
        if ($src) {
            imagedestroy($src);
        }
        if ($dst) {
            imagedestroy($dst);
        }

        rewind($source);
    }
}