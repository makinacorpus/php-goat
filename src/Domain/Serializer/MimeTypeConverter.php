<?php

declare(strict_types=1);

namespace Goat\Domain\Serializer;

final class MimeTypeConverter
{
    /**
     * Mimetype to Symfony serializer type.
     */
    public static function mimetypeToSerializer(string $mimetype): string
    {
        if (false !== \stripos($mimetype, 'json')) {
            return 'json';
        }
        if (false !== \stripos($mimetype, 'xml')) {
            return 'xml';
        }
        return $mimetype;
    }

    /**
     * Symfony serializer to mime type.
     */
    public static function serializerToMimetype(string $type): string
    {
        switch ($type) {
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            default:
                return $type;
        }
    }
}
