<?php

declare(strict_types=1);

namespace Goat\Tests\MessageBroker;

use Goat\Dispatcher\MessageEnvelope;
use Goat\Dispatcher\Message\BrokenMessage;
use Goat\Dispatcher\Tests\MockMessage;
use Goat\Dispatcher\Tests\MockRetryableMessage;
use Goat\EventStore\Property;
use Goat\MessageBroker\MessageBroker;
use Goat\Normalization\Tests\WithSerializerTestTrait;
use Goat\Query\ExpressionValue;
use Goat\Runner\Runner;
use Goat\Runner\Testing\DatabaseAwareQueryTest;
use Goat\Runner\Testing\TestDriverFactory;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract class AbstractMessageBrokerTest extends DatabaseAwareQueryTest
{
    use WithSerializerTestTrait;

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
    public function testRejectWithRetryCountIsRequeued(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));

        $originalEnvelope = $messageBroker->get();

        $serial = $originalEnvelope->getProperty('x-serial');
        self::assertNotNull($serial);

        $messageBroker->reject($originalEnvelope->withProperties([
            Property::RETRY_COUNT => "1",
        ]));

        $envelope = $messageBroker->get();

        self::assertSame("1", $envelope->getProperty(Property::RETRY_COUNT));
        self::assertSame($serial, $envelope->getProperty('x-serial'));
        self::assertSame($originalEnvelope->getMessageId(), $envelope->getMessageId());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testRejectWithRetryCountWithoutSerialIsRequeued(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));

        $originalEnvelope = $messageBroker->get();

        $serial = $originalEnvelope->getProperty('x-serial');
        self::assertNotNull($serial);

        $messageBroker->reject($originalEnvelope->withProperties([
            Property::RETRY_COUNT => "1",
            'x-serial' => null,
        ]));

        $envelope = $messageBroker->get();

        self::assertSame("1", $envelope->getProperty(Property::RETRY_COUNT));
        self::assertNotNull($envelope->getProperty('x-serial'));
        self::assertNotSame($serial, $envelope->getProperty('x-serial'));
        self::assertSame($originalEnvelope->getMessageId(), $envelope->getMessageId());
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testRejectWithRetryDelayInFarFutureIsNotGetRightNow(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));

        $originalEnvelope = $messageBroker->get();

        $serial = $originalEnvelope->getProperty('x-serial');
        self::assertNotNull($serial);

        $messageBroker->reject($originalEnvelope->withProperties([
            Property::RETRY_COUNT => "1",
            Property::RETRY_DELAI => "100000000",
        ]));

        $envelope = $messageBroker->get();
        self::assertNull($envelope);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testRejectWithLowerRetryCountGetsFixed(TestDriverFactory $factory): void
    {
        $messageBroker = $this->createMessageBroker($factory->getRunner(), $factory->getSchema());

        $messageBroker->dispatch(MessageEnvelope::wrap(new MockMessage()));

        $originalEnvelope = $messageBroker->get();
        $messageBroker->reject($originalEnvelope->withProperties([
            Property::RETRY_COUNT => "1",
        ]));

        $secondEnvelope = $messageBroker->get();
        $messageBroker->reject($secondEnvelope->withProperties([
            Property::RETRY_COUNT => "1",
        ]));

        $thirdEnvelope = $messageBroker->get();
        $messageBroker->reject($thirdEnvelope->withProperties([
            Property::RETRY_COUNT => "1",
        ]));

        $envelope = $messageBroker->get();
        self::assertSame("3", $envelope->getProperty(Property::RETRY_COUNT));
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
}
