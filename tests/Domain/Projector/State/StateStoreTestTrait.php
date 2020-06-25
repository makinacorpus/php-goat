<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Projector\State;

use Goat\Domain\EventStore\Event;
use Goat\Domain\Projector\State\StateStore;
use Goat\Domain\Projector\State\ProjectorLockedError;

trait StateStoreTestTrait
{
    protected function doTestLockUpserts(StateStore $stateStore): void
    {
        self::assertNull($stateStore->latest('foo'));

        $ret = $stateStore->lock('foo');
        self::assertSame('foo', $ret->getProjectorId());
        self::assertTrue($ret->isLocked());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertTrue($load->isLocked());
    }

    protected function doTestLockWhenExists(StateStore $stateStore): void
    {
        $prev = $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable()));
        self::assertSame('foo', $prev->getProjectorId());
        self::assertFalse($prev->isLocked());

        $ret = $stateStore->lock('foo');
        self::assertSame('foo', $ret->getProjectorId());
        self::assertTrue($ret->isLocked());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertTrue($load->isLocked());
    }

    protected function doTestLockWhenLockedRaiseError(StateStore $stateStore): void
    {
        self::assertNull($stateStore->latest('foo'));

        $ret = $stateStore->lock('foo');
        self::assertSame('foo', $ret->getProjectorId());
        self::assertTrue($ret->isLocked());

        self::expectException(ProjectorLockedError::class);
        $stateStore->lock('foo');
    }

    protected function doTestUnlockUpserts(StateStore $stateStore): void
    {
        self::assertNull($stateStore->latest('foo'));

        $ret = $stateStore->unlock('foo');
        self::assertSame('foo', $ret->getProjectorId());
        self::assertFalse($ret->isLocked());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertFalse($load->isLocked());
    }

    protected function doTestUnlockWhenExists(StateStore $stateStore): void
    {
        $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 2));
        $prev = $stateStore->lock('foo');
        self::assertSame('foo', $prev->getProjectorId());
        self::assertTrue($prev->isLocked());

        $ret = $stateStore->unlock('foo', $this->createEventAt(new \DateTimeImmutable(), 17));
        self::assertSame('foo', $ret->getProjectorId());
        self::assertFalse($ret->isLocked());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertFalse($ret->isLocked());
    }

    protected function doTestUpdateUpserts(StateStore $stateStore): void
    {
        self::assertNull($stateStore->latest('foo'));

        $ret = $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 32));
        self::assertSame('foo', $ret->getProjectorId());
        self::assertSame(32, $ret->getLatestEventPosition());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertSame(32, $load->getLatestEventPosition());
    }

    protected function doTestUpdateWhenExists(StateStore $stateStore): void
    {
        $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 2));
        $prev = $stateStore->lock('foo');
        self::assertSame('foo', $prev->getProjectorId());
        self::assertSame(2, $prev->getLatestEventPosition());

        $ret = $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 17));
        self::assertSame('foo', $ret->getProjectorId());
        self::assertSame(17, $ret->getLatestEventPosition());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertSame(17, $load->getLatestEventPosition());
    }

    protected function doTestUpdateResetLocking(StateStore $stateStore): void
    {
        $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 2));
        $prev = $stateStore->lock('foo');
        self::assertSame('foo', $prev->getProjectorId());
        self::assertSame(2, $prev->getLatestEventPosition());
        self::assertTrue($prev->isLocked());

        $ret = $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 17), false);
        self::assertSame('foo', $ret->getProjectorId());
        self::assertSame(17, $ret->getLatestEventPosition());
        self::assertTrue($ret->isLocked());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertSame(17, $load->getLatestEventPosition());
        self::assertTrue($load->isLocked());

        $ret = $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 17));
        self::assertSame('foo', $ret->getProjectorId());
        self::assertSame(17, $ret->getLatestEventPosition());
        self::assertFalse($ret->isLocked());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertSame(17, $load->getLatestEventPosition());
        self::assertFalse($load->isLocked());
    }

    protected function doTestUpdateResetError(StateStore $stateStore): void
    {
        $prev = $stateStore->error('foo', $this->createEventAt(new \DateTimeImmutable(), 29), 'This is terrible.', 27);
        self::assertSame('foo', $prev->getProjectorId());
        self::assertTrue($prev->isError());
        self::assertSame(27, $prev->getErrorCode());
        self::assertSame('This is terrible.', $prev->getErrorMessage());

        $ret = $stateStore->update('foo', $this->createEventAt(new \DateTimeImmutable(), 30), false);
        self::assertSame('foo', $ret->getProjectorId());
        self::assertFalse($ret->isError());
        self::assertSame(0, $ret->getErrorCode());
        self::assertNull($ret->getErrorMessage());
        self::assertNull($ret->getErrorTrace());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertFalse($load->isError());
        self::assertSame(0, $load->getErrorCode());
        self::assertNull($load->getErrorMessage());
        self::assertNull($load->getErrorTrace());
    }

    protected function doTestErrorUpserts(StateStore $stateStore): void
    {
        self::assertNull($stateStore->latest('foo'));

        $ret = $stateStore->error('foo', $this->createEventAt(new \DateTimeImmutable(), 29), 'This is terrible.', 27);
        self::assertSame('foo', $ret->getProjectorId());
        self::assertTrue($ret->isError());
        self::assertSame(29, $ret->getLatestEventPosition());
        self::assertSame(27, $ret->getErrorCode());
        self::assertSame('This is terrible.', $ret->getErrorMessage());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertTrue($load->isError());
        self::assertSame(29, $load->getLatestEventPosition());
        self::assertSame(27, $load->getErrorCode());
        self::assertSame('This is terrible.', $load->getErrorMessage());
    }

    protected function doTestErrorWhenExists(StateStore $stateStore): void
    {
        self::markTestIncomplete("Implement me");
    }

    protected function doTestErrorResetLocking(StateStore $stateStore): void
    {
        self::markTestIncomplete("Implement me");
    }

    protected function doTestErrorWithoutUnlock(StateStore $stateStore): void
    {
        self::markTestIncomplete("Implement me");
    }

    protected function doTestExceptionUpserts(StateStore $stateStore): void
    {
        self::assertNull($stateStore->latest('foo'));

        $ret = $stateStore->exception('foo', $this->createEventAt(new \DateTimeImmutable(), 29), new \Exception("Foo Bar !", 7));
        self::assertSame('foo', $ret->getProjectorId());
        self::assertTrue($ret->isError());
        self::assertSame(29, $ret->getLatestEventPosition());
        self::assertSame(7, $ret->getErrorCode());
        self::assertSame('Foo Bar !', $ret->getErrorMessage());

        $load = $stateStore->latest('foo');
        self::assertSame('foo', $load->getProjectorId());
        self::assertTrue($load->isError());
        self::assertSame(29, $load->getLatestEventPosition());
        self::assertSame(7, $load->getErrorCode());
        self::assertSame('Foo Bar !', $load->getErrorMessage());
    }

    protected function doTestExceptionWhenExists(StateStore $stateStore): void
    {
        self::markTestIncomplete("Implement me");
    }

    protected function doTestExceptionResetLocking(StateStore $stateStore): void
    {
        self::markTestIncomplete("Implement me");
    }

    protected function doTestExceptionWithoutUnlock(StateStore $stateStore): void
    {
        self::markTestIncomplete("Implement me");
    }

    private function createEventAt($message, int $position = 1, ?\DateTimeInterface $validAt = null): Event
    {
        $event = Event::create(new \DateTimeImmutable());

        $func = \Closure::bind(
            static function (Event $event) use ($position, $validAt) {
                $event->position = $position;
                $event->validAt = $validAt ?? new \DateTimeImmutable();
            },
            null,
            Event::class
        );

        $func($event);

        return $event;
    }
}
