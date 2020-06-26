<?php

namespace steroids\file\previews;

class ImageCropResize extends FilePreview
{
    public function run()
    {
        list($originalWidth, $originalHeight) = getimagesize($this->filePath);

        // Defaults offset - center
        $minOriginalSize = min($originalWidth, $originalHeight);
        if ($this->width > $this->height) {
            $cropWidth = $minOriginalSize;
            $cropHeight = (int)floor($minOriginalSize * ($this->height / $this->width));
        } else {
            $cropWidth = $minOriginalSize;
            $cropHeight = (int)floor($minOriginalSize * ($this->width / $this->height));
        }

        // Crop
        $cropProcessor = new ImageCrop([
            'filePath' => $this->filePath,
            'width' => $cropWidth,
            'height' => $cropHeight,
            'previewQuality' => $this->previewQuality,
            'offsetX' => round(($originalWidth - $cropWidth) / 2),
            'offsetY' => round(($originalHeight - $cropHeight) / 2),
        ]);
        $cropProcessor->run();

        // Resize
        $fitProcessor = new ImageResize([
            'filePath' => $this->filePath,
            'width' => $this->width,
            'height' => $this->height,
            'previewQuality' => $this->previewQuality,
        ]);
        $fitProcessor->run();
        $this->width = $fitProcessor->width;
        $this->height = $fitProcessor->height;
    }
}