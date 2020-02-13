<?php
declare(strict_types=1);

namespace Sb\ImageHandler\Transformation;

use Sb\ImageHandler\Exception\Exception;

class DynamicWidthHeight implements TransformationInterface
{
    /** @var string */
    private $pattern = 'w-(\d+)-h-(\d+)';

    /** @var array<int, string> */
    private static $params = [
        '-strip',
        '-sampling-factor 4:2:0',
        '-colorspace RGB',
        '-interlace none',

        '-gravity center',

        '-fill white',

        '-quality 85',
        '-format jpg',
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
        $height = $m[2];

        $params[] = "-resize {$width}x{$height}";
        $params[] = "-extent {$width}x{$height}";

        return $params;
    }
}