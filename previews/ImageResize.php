<?php

namespace steroids\file\previews;

use steroids\file\models\ResizeParameters;

class ImageResize extends FilePreview
{
    /**
     * @var boolean
     */
    public bool $isFit = true;

    public function run()
    {
        $imageContent = file_get_contents($this->filePath);

        // New size
        list($originalWidth, $originalHeight) = getimagesizefromstring($imageContent);

        $scale = $this->isFit ?
            min($this->width / $originalWidth, $this->height / $originalHeight) :
            max($this->width / $originalWidth, $this->height / $originalHeight);

        $this->width = max(1, (int)floor($originalWidth * $scale));
        $this->height = max(1, (int)floor($originalHeight * $scale));

        // Check need resize
        if ($scale >= 1) {
            $this->width = $originalWidth;
            $this->height = $originalHeight;
            return;
        }

        $resizeParameters = new ResizeParameters([
            'width' => $this->width,
            'height' => $this->height,
            'offsetX' => 0,
            'offsetY' => 0,
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'previewQuality' => $this->previewQuality,
        ]);

        PreviewHelper::resizeImage($imageContent, $this->filePath, $resizeParameters);
    }
}