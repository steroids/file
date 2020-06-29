<?php

namespace steroids\file\previews;

class ImageCropResize extends FilePreview
{
    public function run()
    {
        $imageContent = stream_get_contents($this->source);
        rewind($this->source);

        list($originalWidth, $originalHeight) = getimagesizefromstring($imageContent);

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
            'source' => $this->source,
            'width' => $cropWidth,
            'height' => $cropHeight,
            'previewQuality' => $this->previewQuality,
            'offsetX' => round(($originalWidth - $cropWidth) / 2),
            'offsetY' => round(($originalHeight - $cropHeight) / 2),
        ]);
        $cropProcessor->run();

        // Resize
        $fitProcessor = new ImageResize([
            'source' => $this->source,
            'width' => $this->width,
            'height' => $this->height,
            'previewQuality' => $this->previewQuality,
        ]);
        $fitProcessor->run();
        $this->width = $fitProcessor->width;
        $this->height = $fitProcessor->height;
    }
}