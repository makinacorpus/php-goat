<?php

declare(strict_types=1);

namespace Goat\Normalization\Tests;

use Goat\Bridge\Symfony\Serializer\GoatSerializerAdapter;
use Goat\Bridge\Symfony\Serializer\UuidNormalizer;
use Goat\Normalization\Serializer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

trait WithSerializerTestTrait
{
    /**
     * Create serializer.
     */
    final protected function createSerializer(): Serializer
    {
        return new GoatSerializerAdapter(
            new SymfonySerializer(
                [
                    new ArrayDenormalizer(),
                    new UuidNormalizer(),
                    new ObjectNormalizer(),
                ],
                [
                    new XmlEncoder(),
                    new JsonEncoder(),
                ]
            )
        );
    }
}
