<?php

declare(strict_types=1);

namespace Goat\Domain\Serializer;

use Goat\Domain\EventStore\NameMap;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serializer component lacks important features, this is some of them.
 *
 * Here are all interfaces you need to implement to plug over all serializer
 * methods that carry the $type parameter:
 *
 *   - Symfony\Component\Serializer\Normalizer\DenormalizerInterface
 *   - Symfony\Component\Serializer\SerializerInterface
 *
 * But here are all other interfaces the Serializer object also implements:
 *
 *   - Symfony\Component\Serializer\Normalizer\NormalizerInterface
 *   - Symfony\Component\Serializer\Encoder\DecoderInterface
 *
 * Because we can't know for sure if anyone else already decorated the
 * serializer service, and because it's aliased in the container with all
 * those interfaces, we have to implement them all.
 *
 * Serializer object also adds silently optional parameters to some methods,
 * we can't just implement those interfaces, we also have to proceed to the
 * same methods alterations altogether.
 *
 * It would have been much simpler to extend it and simply add the feature
 * we need into, but anyone could have already decorated it, so the anything
 * but the decorator pattern is no-go.
 *
 * @codeCoverageIgnore
 *   I don't have more time for this, serializer already made me loose so much.
 */
final class NameMapSerializer implements
    DecoderInterface,
    DenormalizerInterface,
    NormalizerInterface,
    SerializerInterface
{
    /** @var NameMap */
    private $nameMap;

    /** @var \Symfony\Component\Serializer\Serializer */
    private $decorated;

    /**
     * Default constructor
     */
    public function __construct(NameMap $nameMap, $serializer)
    {
        $this->decorated = $serializer;
        $this->nameMap = $nameMap;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize($data, $format, array $context = [])
    {
        return $this->decorated->serialize($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format, array $context = [])
    {
        return $this->decorated->supportsDecoding($format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null, array $context = [])
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        return $this->decorated->denormalize($data, $this->nameMap->getType($type), $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format = null, array $context = [])
    {
        return $this->decorated->normalize($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format, array $context = [])
    {
        return $this->decorated->decode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = [])
    {
        return $this->decorated->supportsDenormalization($data, $this->nameMap->getType($type), $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function deserialize($data, $type, $format, array $context = [])
    {
        return $this->decorated->deserialize($data, $this->nameMap->getType($type), $format, $context);
    }
}
