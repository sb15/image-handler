<?php
declare(strict_types=1);

namespace Sb\ImageHandler\Transformation;

class None implements TransformationInterface
{
    /** @var string */
    private $pattern = 'source';

    /** @var array<int, string> */
    private static $params = [];

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