<?php

namespace steroids\file\previews;

use Exception;
use steroids\file\models\ResizeParameters;

class PreviewHelper
{
    public static function resizeImage($imageContent, $filePath, ResizeParameters $parameters)
    {
        $bool = true;
        $src = imagecreatefromstring($imageContent);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Auto-rotate
        if ($extension === 'jpg' || $extension === 'jpeg') {
            try {
                $exif = exif_read_data($filePath);
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

        $bool && $extension === 'png' ?
            imagepng($dst, $filePath) :
            imagejpeg($dst, $filePath, $parameters->previewQuality);

        // Clean
        if ($src) {
            imagedestroy($src);
        }
        if ($dst) {
            imagedestroy($dst);
        }
    }
}