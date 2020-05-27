<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\MessageBroker;

use Goat\Domain\Event\BrokenMessage;
use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\EventStore\Property;
use Goat\Domain\MessageBroker\MessageBroker;
use Goat\Domain\Serializer\UuidNormalizer;
use Goat\Domain\Tests\Event\MockMessage;
use Goat\Domain\Tests\Event\MockRetryableMessage;
use Goat\Runner\Runner;
use Goat\Runner\Testing\DatabaseAwareQueryTest;
use Goat\Runner\Testing\TestDriverFactory;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Goat\Query\ExpressionValue;

abstract class AbstractMessageBrokerTest extends DatabaseAwareQueryTest
{
    /**
     * @dataProvider runnerDataProvider
     */
    public function testGetWhenEmptyGivesNull(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        self::assertNull($messageBroker->get());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testGetFetchTheFirstOne(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));
        $messageBroker->dispatch(MessageEnvelope::wrap(new MockRetryableMessage()));

        $envelope1 = $messageBroker->get();
        self::assertSame(MockMessage::class, $envelope1->getProperty(Property::MESSAGE_TYPE));

        $envelope2 = $messageBroker->get();
        self::assertSame(MockRetryableMessage::class, $envelope2->getProperty(Property::MESSAGE_TYPE));

        self::assertNull($messageBroker->get());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testAutomaticPropertiesAreComputed(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));
        $envelope = $messageBroker->get();

        self::assertSame(MockMessage::class, $envelope->getProperty(Property::MESSAGE_TYPE));
        self::assertNotInstanceOf(BrokenMessage::class, $envelope->getMessage());
        self::assertSame(Property::DEFAULT_CONTENT_ENCODING, $envelope->getMessageContentEncoding());
        self::assertSame(Property::DEFAULT_CONTENT_TYPE, $envelope->getMessageContentType());
        self::assertNotNull($envelope->getMessageId());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testContentTypeFromEnvelopeIsUsed(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage(), [
            Property::CONTENT_TYPE => 'application/xml',
        ]));
        $envelope = $messageBroker->get();

        // This will fail if we change the default, just to be sure we do
        // really test the correct behaviour.
        self::assertNotSame(Property::DEFAULT_CONTENT_TYPE, 'application/xml');

        self::assertSame(MockMessage::class, $envelope->getProperty(Property::MESSAGE_TYPE));
        self::assertNotInstanceOf(BrokenMessage::class, $envelope->getMessage());
        self::assertSame('application/xml', $envelope->getMessageContentType());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testPropertiesArePropagated(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage(), [
            'x-foo' => 'bar',
        ]));

        $envelope = $messageBroker->get();

        self::assertSame('bar', $envelope->getProperty('x-foo'));
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testFailedMessagesAreNotGetAgain(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));

        $envelope = $messageBroker->get();
        $messageBroker->reject($envelope);

        self::assertNull($messageBroker->get());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testMissingTypeInDatabaseFallsBackWithHeader(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $messageBroker = $this->createMessageBroker($runner, $factory->getSchema());

        $runner
            ->getQueryBuilder()
            ->insert('message_broker')
            ->values([
                'id' => Uuid::uuid4(),
                'content_type' => 'application/json',
                'type' => null,
                'headers' => ExpressionValue::create([Property::MESSAGE_TYPE => MockMessage::class], 'json'),
                'body' => '{}',
            ])
            ->execute()
        ;

        $envelope = $messageBroker->get();

        self::assertSame(MockMessage::class, $envelope->getProperty(Property::MESSAGE_TYPE));
        self::assertInstanceOf(MockMessage::class, $envelope->getMessage());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testMissingContentTypeInDatabaseFallsBackWithHeader(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $messageBroker = $this->createMessageBroker($runner, $factory->getSchema());

        $runner
            ->getQueryBuilder()
            ->insert('message_broker')
            ->values([
                'id' => Uuid::uuid4(),
                'content_type' => null,
                'type' => MockMessage::class,
                'headers' => ExpressionValue::create([Property::CONTENT_TYPE => 'application/json'], 'json'),
                'body' => '{}',
            ])
            ->execute()
        ;

        $envelope = $messageBroker->get();

        self::assertSame(MockMessage::class, $envelope->getProperty(Property::MESSAGE_TYPE));
        self::assertInstanceOf(MockMessage::class, $envelope->getMessage());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testNoTypeGivesBrokenMessage(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $messageBroker = $this->createMessageBroker($runner, $factory->getSchema());

        $runner
            ->getQueryBuilder()
            ->insert('message_broker')
            ->values([
                'id' => Uuid::uuid4(),
                'content_type' => 'application/json',
                'body' => '{}',
            ])
            ->execute()
        ;

        $envelope = $messageBroker->get();

        self::assertNull($envelope->getProperty(Property::MESSAGE_TYPE));
        self::assertInstanceOf(BrokenMessage::class, $envelope->getMessage());
        self::assertSame('{}', $envelope->getMessage()->getOriginalData());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testNoContentTypeGivesBrokenMessage(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $messageBroker = $this->createMessageBroker($runner, $factory->getSchema());

        $runner
            ->getQueryBuilder()
            ->insert('message_broker')
            ->values([
                'id' => Uuid::uuid4(),
                'type' => MockMessage::class,
                'body' => '{}',
            ])
            ->execute()
        ;

        $envelope = $messageBroker->get();

        self::assertSame(MockMessage::class, $envelope->getProperty(Property::MESSAGE_TYPE));
        self::assertInstanceOf(BrokenMessage::class, $envelope->getMessage());
        self::assertSame('{}', $envelope->getMessage()->getOriginalData());
    }

    /**
     * Create message broker instance that will be tested.
     */
    protected abstract function createMessageBroker(Runner $runner, string $schema): MessageBroker;

    /**
     * Create a new UUIDv4
     */
    final protected function createUuid(): UuidInterface
    {
        return Uuid::uuid4();
    }

    /**
     * Create Symfony serializer
     */
    final protected function createSerializer(): SerializerInterface
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ArrayDenormalizer(), new UuidNormalizer(), new PropertyNormalizer(), new ObjectNormalizer()];

        return new Serializer($normalizers, $encoders);
    }
}
