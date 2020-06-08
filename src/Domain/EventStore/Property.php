<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

/**
 * Property names are AMQP compatible, except for 'type', and 'X-*' that should
 * be message properties by the AMQP spec.
 *
 * @codeCoverageIgnore
 */
final class Property
{
    const DEFAULT_TYPE = 'none';
    const DEFAULT_NAMESPACE = 'default';
    const DEFAULT_CONTENT_TYPE = 'application/json';
    const DEFAULT_CONTENT_ENCODING = 'UTF-8';

    const APP_ID = 'app-id';
    const CONTENT_ENCODING = 'content-encoding';
    const CONTENT_TYPE = 'content-type';
    const MESSAGE_ID = 'message-id';
    const MESSAGE_TYPE = 'type';
    const REPLY_TO = 'reply-to';
    const SUBJECT = 'subject';
    const USER_ID = 'user-id';

    /** Custom header for storing event processing duration. */
    const PROCESS_DURATION = 'x-goat-duration';

    /** Current number of retry count. */
    const RETRY_COUNT = 'x-retry-count';
    /** Retry after at least <VALUE> milliseconds. */
    const RETRY_DELAI = 'x-retry-delai';
    /** Maximum number of retries (AMQP would use a TTL instead). */
    const RETRY_MAX = 'x-retry-max';

    /** Event was modified, this contains arbitrary text. */
    const MODIFIED_BY = 'x-goat-modified-by';
    /** Event was modified, an ISO8601 is welcome in this value. */
    const MODIFIED_AT = 'x-goat-modified-at';
    /** Event was modified, just arbitrary text here. */
    const MODIFIED_WHY = 'x-goat-modified-why';
    /** Event was modified, previous name it had. */
    const MODIFIED_PREVIOUS_NAME = 'x-goat-modified-prev-name';
    /** Event was modified, previous revision it was at. */
    const MODIFIED_PREVIOUS_REVISION = 'x-goat-modified-prev-rev';
    /** Event was modified, an ISO8601 previous valid date. */
    const MODIFIED_PREVIOUS_VALID_AT = 'x-goat-modified-prev-valid-at';
    /** Event was modified, an ISO8601 previous valid date. */
    const MODIFIED_INSERTED = 'x-goat-modified-inserted';

    /**
     * Convert custom properties to AMQP properties.
     */
    public static function toAmqpProperties(array $properties): array
    {
        $ret = [];

        foreach ($properties as $key => $value) {
            switch ($key) {

                case Property::APP_ID:
                case Property::CONTENT_ENCODING:
                case Property::CONTENT_TYPE:
                case Property::MESSAGE_ID:
                case Property::MESSAGE_TYPE:
                case Property::REPLY_TO:
                case Property::SUBJECT:
                case Property::USER_ID:
                    $ret[\str_replace('-', '_', $key)] = $value;
                    break;

                // @todo should we handle those?
                //   They've been copied from php-amqplib.
                case 'delivery_mode':
                case 'priority':
                case 'correlation_id':
                case 'expiration':
                case 'timestamp':
                case 'cluster_id':
                    $ret[$key] = $key;
                    break;

                default:
                    $ret['application_headers'][$key] = $value;
                    break;
            }
        }

        return $ret;
    }

    /**
     * convert AMQP properties to custom properties.
     */
    public static function fromAmqpProperties(array $properties): array
    {
        $ret = [];

        foreach ($properties as $key => $value) {
            switch ($key) {

                case 'app_id':
                case 'content_encoding':
                case 'content_type':
                case 'message_id':
                case 'type':
                case 'reply_to':
                case 'subject':
                case 'user_id':
                    $ret[\str_replace('_', '-', $key)] = $value;
                    break;

                case 'application_headers':
                    if (\is_array($value)) {
                        foreach ($value as $name => $headerValue) {
                            $ret[$name] = $headerValue;
                        }
                    }
                    break;

                // @todo should we handle those?
                //   They've been copied from php-amqplib.
                case 'delivery_mode':
                case 'priority':
                case 'correlation_id':
                case 'expiration':
                case 'timestamp':
                case 'cluster_id':
                    $ret[$key] = $key;
                    break;

                default:
                    $ret[$key] = $value;
                    break;
            }
        }

        return $ret;
    }
}
