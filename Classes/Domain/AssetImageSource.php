<?php

declare(strict_types=1);

namespace Sitegeist\Kaleidoscope\Domain;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Media\Domain\Model\ImageVariant;
use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Neos\Media\Domain\Model\VariantSupportInterface;
use Neos\Media\Domain\Service\AssetService;
use Neos\Media\Domain\Service\ThumbnailService;

class AssetImageSource extends AbstractScalableImageSource
{
    /**
     * @Flow\Inject
     *
     * @var ThumbnailService
     */
    protected $thumbnailService;

    /**
     * @Flow\Inject
     *
     * @var AssetService
     */
    protected $assetService;

    /**
     * @var ImageInterface
     */
    protected $asset;

    /**
     * @var bool
     */
    protected $async = false;

    /**
     * @var ActionRequest|null
     */
    protected $request;

    /**
     * @param ImageInterface     $asset
     * @param string|null        $title
     * @param string|null        $alt
     * @param bool               $async
     * @param ActionRequest|null $request
     */
    public function __construct(ImageInterface $asset, ?string $title = null, ?string $alt = null, bool $async = true, ?ActionRequest $request = null)
    {
        parent::__construct($title, $alt);
        $this->asset = $asset;
        $this->request = $request;
        $this->async = $async;
        $this->baseWidth = $this->asset->getWidth();
        $this->baseHeight = $this->asset->getHeight();
    }

    /**
     * Use the variant generated from the given variant preset in this image source.
     *
     * @param string $presetIdentifier
     * @param string $presetVariantName
     *
     * @return ImageSourceInterface
     */
    public function withVariantPreset(string $presetIdentifier, string $presetVariantName): ImageSourceInterface
    {
        /**
         * @var AssetImageSource $newSource
         */
        $newSource = parent::withVariantPreset($presetIdentifier, $presetVariantName);

        if ($newSource->targetImageVariant !== []) {
            $asset = ($newSource->asset instanceof ImageVariant) ? $newSource->asset->getOriginalAsset() : $newSource->asset;
            if ($asset instanceof VariantSupportInterface) {
                $assetVariant = $asset->getVariant($newSource->targetImageVariant['presetIdentifier'], $newSource->targetImageVariant['presetVariantName']);
            } else {
                $assetVariant = null;
            }
            if ($assetVariant instanceof ImageVariant) {
                $newSource->asset = $assetVariant;
                $newSource->baseWidth = $assetVariant->getWidth();
                $newSource->baseHeight = $assetVariant->getHeight();
            } else {
                // if no alternate variant is found we estimate the target dimensions
                $targetDimensions = $this->estimateDimensionsFromVariantPresetAdjustments($presetIdentifier, $presetVariantName);
                $newSource->baseWidth = $targetDimensions->getWidth();
                $newSource->baseHeight = $targetDimensions->getHeight();
            }
        }

        return $newSource;
    }

    /**
     * @throws \Neos\Flow\Mvc\Routing\Exception\MissingActionNameException
     * @throws \Neos\Media\Exception\AssetServiceException
     * @throws \Neos\Media\Exception\ThumbnailServiceException
     *
     * @return string
     */
    public function src(): string
    {
        if (!$this->asset instanceof AssetInterface) {
            return '';
        }

        $width = $this->getCurrentWidth();
        $height = $this->getCurrentHeight();

        $async = $this->request ? $this->async : false;
        $allowCropping = true;
        $allowUpScaling = false;
        $thumbnailConfiguration = new ThumbnailConfiguration(
            $width,
            $width,
            $height,
            $height,
            $allowCropping,
            $allowUpScaling,
            $async,
            null,
            $this->targetFormat
        );

        $thumbnailData = $this->assetService->getThumbnailUriAndSizeForAsset(
            $this->asset,
            $thumbnailConfiguration,
            $this->request
        );

        if ($thumbnailData === null) {
            return '';
        }

        return $thumbnailData['src'];
    }
}
