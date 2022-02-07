<?php

declare(strict_types=1);

namespace Goat\Normalization;

/**
 * This exists because in Symfony, there are here and there a few API bits
 * that don't use mimetypes, but what they call a "format" instead.
 *
 * For example, "symfony/serializer" doesn't care about mimetypes, it just
 * uses a "format" name, such as "json" or "xml". At some point, we needed
 * something to glue Symfony's "format" with real mimetypes.
 */
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
