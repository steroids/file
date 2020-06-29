<?php

namespace steroids\file\previews;

use steroids\file\structure\ResizeParameters;

class ImageCrop extends FilePreview
{
    /**
     * @var int
     */
    public $offsetX;

    /**
     * @var int
     */
    public $offsetY;

    public function run()
    {
        $imageContent = stream_get_contents($this->source);
        rewind($this->source);

        list($originalWidth, $originalHeight) = getimagesizefromstring($imageContent);

        // Check if crop is smaller or equal than original image
        $isCropSizeCorrect = ($originalWidth >= $this->width) && ($originalHeight >= $this->height);

        // Check if crop offset doesn't exceed original image
        $isCropOffsetCorrect = ($this->offsetX < $originalWidth) && ($this->offsetY < $originalHeight);

        // Leaving image intact when crop sizes or offsets are incorrect
        if (!$isCropSizeCorrect || !$isCropOffsetCorrect) {
            $this->width = $originalWidth;
            $this->height = $originalHeight;
            return;
        }

        $resizeParameters = new ResizeParameters([
            'width' => $this->width,
            'height' => $this->height,
            'offsetX' => $this->offsetX,
            'offsetY' => $this->offsetY,
            'originalWidth' => $this->width,
            'originalHeight' => $this->height,
            'previewQuality' => $this->previewQuality,
        ]);

        PreviewHelper::resizeImage($imageContent, $this->source, $this->previewExtension,$resizeParameters);
    }
}
