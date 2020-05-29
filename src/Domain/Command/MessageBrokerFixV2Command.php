<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Bridge\Symfony\Messenger\Transport\MessageBrokerTransport;
use Goat\Domain\EventStore\NameMap;
use Goat\Domain\EventStore\Property;
use Goat\Domain\Serializer\MimeTypeConverter;
use Goat\Query\ExpressionValue;
use Goat\Query\Where;
use Goat\Runner\Runner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Serializer\SerializerInterface;

final class MessageBrokerFixV2Command extends Command
{
    const ROW_FAIL = -1;
    const ROW_IGNORE = 1;
    const ROW_SUCCESS = 0;

    protected static $defaultName = 'goat:message-broker:fix-v2';

    private NameMap $nameMap;
    private PhpSerializer $phpSerializer;
    private Runner $runner;
    private SerializerInterface $serializer;

    private bool $letErrorPass = false;
    private bool $dryRun = true;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, SerializerInterface $serializer, NameMap $nameMap)
    {
        parent::__construct();

        $this->nameMap = $nameMap;
        $this->phpSerializer = new PhpSerializer();
        $this->runner = $runner;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Traverse the whole message queue, and fix messages data')
            ->addOption('no-dry-run', null, InputOption::VALUE_NONE, "If set, data will be saved, otherwise it's a dry run.")
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, "If set, will only proceed the given number of rows.")
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($output->isVeryVerbose()) {
            $this->letErrorPass = true;
        }
        if ($input->hasOption('no-dry-run') && $input->getOption('no-dry-run')) {
            $this->dryRun = false;
            $output->writeln('<error>Warning, dry run is disabled</error>');
        }

        $limit = \abs((int)$input->getOption('limit'));

        $query = $this
            ->runner
            ->getQueryBuilder()
            ->select('message_broker')
            ->columnExpression('*')
            ->where(static function (Where $where) {
                // Identify broken items by the lack of "content_type" or "type"
                // values in the database row.
                $where
                    ->or()
                    ->condition('content_type', '')
                    ->condition('type', '')
                    ->isNull('content_type')
                    ->isNull('type')
                ;
            })
            ->orderBy('serial')
        ;

        if ($limit) {
            $query->range($limit);
        }

        $total = $query->getCountQuery()->execute()->fetchField();
        $result = $query->execute();

        $progress = new ProgressBar($output);
        $progress->setFormat('debug');
        $progress->setMaxSteps($total);

        $failed = $ignored = $success = 0;

        foreach ($result as $row) {
            switch ($this->processMessageBrokerRow($row)) {

                case self::ROW_FAIL:
                    ++$failed;
                    break;

                case self::ROW_IGNORE:
                    ++$ignored;
                    break;

                case self::ROW_SUCCESS:
                    ++$success;
                    break;
            }
            $progress->advance(1);
        }

        $progress->finish();
        $output->writeln("");

        $message = \sprintf("%d success, %d ignored, %d failed", $success, $ignored, $failed);
        if ($failed) {
            $output->writeln('<error>' . $message . '</error>');
        } else {
            $output->writeln($message);
        }

        return 0;
    }

    private function processMessageBrokerRow(array $row): int
    {
        $breaker = $this->fixMessageData($row);

        if (self::ROW_SUCCESS !== $breaker) {
            return $breaker;
        }

        if (!$this->dryRun) {
            $this
                ->runner
                ->getQueryBuilder()
                ->update('message_broker')
                ->sets([
                    'body' => $row['body'],
                    'content_type' => $row['content_type'],
                    'headers' => ExpressionValue::create($row['headers'], 'json'),
                    'type' => $row['type'],
                ])
                ->condition('serial', $row['serial'])
                ->execute()
            ;
        }

        return self::ROW_SUCCESS;
    }

    private function fixMessageData(array &$row): int
    {
        $object = $this->decodeMessageBody($row);

        if (null === $object) {
            return self::ROW_FAIL;
        }

        if ($object instanceof Envelope) {
            // Messenger did a complete serialization of the object, which
            // seems to have happened always. Deal with this.
            $headers = MessageBrokerTransport::fromSymfonyStamps($object->all());
            if ($headers) {
                foreach ($headers as $key => $value) {
                    // @todo this will squash our own in case of conflict,
                    //   which one should be authoritative, messenger, or
                    //   custom?
                    $row[$key] = $value;
                }
            }
            $object = $object->getMessage();
        }

        $contentType = [];
        // Content type from database.
        if (!empty($row['content_type'])) {
            if (!$contentType) {
                $contentType = $row['content_type'];
            }
        }
        // Content type from our custom API.
        if (!empty($row['headers'][Property::CONTENT_TYPE])) {
            if (!$contentType) {
                $contentType = $row['headers'][Property::CONTENT_TYPE];
            }
            unset($row['headers'][Property::CONTENT_TYPE]);
        }
        // Content type AMQP variant.
        if (!empty($row['headers']['content_type'])) {
            if (!$contentType) {
                $contentType = $row['headers']['content_type'];
            }
            unset($row['headers']['content_type']);
        }
        // Content type Symfony variant.
        if (!empty($row['headers']['Content-Type'])) {
            if (!$contentType) {
                $contentType = $row['headers']['Content-Type'];
            }
            unset($row['headers']['Content-Type']);
        }
        // If none, use our default.
        if (!$contentType) {
            $contentType = Property::DEFAULT_CONTENT_TYPE;
        }

        try {
            $row['body'] = $this->serializer->serialize($object, MimeTypeConverter::mimetypeToSerializer($contentType));
            $row['headers'][Property::CONTENT_TYPE] = $contentType;
            $row['headers'][Property::CONTENT_ENCODING] = Property::DEFAULT_CONTENT_ENCODING;
            $row['content_type'] = $contentType;
        } catch (\Throwable $e) {
            if ($this->letErrorPass) {
                throw $e;
            }
            return self::ROW_FAIL;
        }

        // And now, handle message type.
        $row['type'] = $row['headers'][Property::MESSAGE_TYPE] = $this->nameMap->getMessageName($object);

        return self::ROW_SUCCESS;
    }

    private function decodeMessageBody(array $row): ?object
    {
        // Field can be a bytea or not, depending upon the target environment
        // schema, in certain cases, ext-pgsql will give us a resource here,
        // depending upon goat-query version as well, it could already have
        // been decoded.
        $body = $row['body'];
        if (\is_resource($body)) {
            $body = \stream_get_contents($body);
        }

        $object = null;

        try {
            // Attempt unserialize using PHP serializer from the messenger
            // component. It's our best guess.
            $object = $this->phpSerializer->decode(['body' => $body]);
        } catch (MessageDecodingFailedException $e) {
            // Continue.
        }
        if (null !== $object && false !== $object) {
            return $object;
        }

        // Attempt with PHP native serialization, with older version of the
        // messenger, it could have been serialized this way.
        $object = @\unserialize($body);
        if (null !== $object && false !== $object) {
            return $object;
        }

        // For some reason, at some point in time, we did use base64 encoding
        // to avoid some serialization errors in database, attempt with it.
        $object = @\unserialize(@\base64_decode($body));
        if (null !== $object && false !== $object) {
            return $object;
        }

        return null;
    }
}
