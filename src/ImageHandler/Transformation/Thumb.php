<?php
declare(strict_types=1);

namespace Sb\ImageHandler\Transformation;

use Sb\ImageHandler\Exception\Exception;

class Thumb implements TransformationInterface
{
    /** @var string */
    private $pattern = 'thumb-(\d+)';

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
     * @throws Exception
     */
    public function getParams(string $transformation): array
    {
        $params = self::$params;

        if (!preg_match("#{$this->pattern}#i", $transformation, $m)) {
            throw new Exception('Transformation ' . self::class . ' pattern not match');
        }

        $width = $m[1];

        $params[] = "-thumbnail {$width}";

        return $params;
    }
}