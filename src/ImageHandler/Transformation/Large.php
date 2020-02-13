<?php
declare(strict_types=1);

namespace Sb\ImageHandler\Transformation;

class Large implements TransformationInterface
{
    /** @var string */
    private $pattern = 'large';

    /** @var array<int, string> */
    private static $params = [
        '-filter Triangle',
        '-define filter:support=2',
        '-unsharp 0.25x0.25+8+0.065',
        '-dither None',
        '-posterize 136',
        '-quality 82',
        '-define jpeg:fancy-upsampling=off',
        '-define png:compression-filter=5',
        '-define png:compression-level=9',
        '-define png:compression-strategy=1',
        '-define png:exclude-chunk=all',
        '-interlace none',
        '-colorspace sRGB',
        '-strip',

        '-resize 500x500',
        '-gravity center',
        '-extent 500x500',
        '-fill white',
    ];

    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @param string $transformation
     * @return array<int, string>
     */
    public function getParams(string $transformation): array
    {
        return self::$params;
    }
}