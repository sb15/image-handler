<?php
declare(strict_types=1);

namespace Sb\ImageHandler\Transformation;

interface TransformationInterface
{
    /**
     * @return string
     */
    public function getPattern(): string;

    /**
     * @param string $transformation
     * @return array<int, string>
     */
    public function getParams(string $transformation): array;
}