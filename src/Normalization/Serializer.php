<?php

declare(strict_types=1);

namespace Goat\Normalization;

interface Serializer
{
    /**
     * Serialize anything using the given format.
     *
     * @param mixed $data
     *   Anything. Type will be determined automatically.
     * @param string $format
     *   A mimetype format for serialized data.
     * @param null|string $forceType
     *   Force another type than given one.
     *
     * @return string
     *   String data.
     */
    public function serialize($data, string $format, ?string $forceType = null): string;

    /**
     * Unserialize data of the given type serialized with the given format.
     *
     * @param string $type
     *   Data type, must be a valid PHP type (builtin or not).
     * @param string $format
     *   Data mimetype format.
     * @param string $data
     *   Data to unserialize.
     *
     * @return mixed
     *   Anything. Even null.
     */
    public function unserialize(string $type, string $format, string $data);
}
