<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Projector\State;

use Goat\Domain\Projector\State\ArrayStateStore;
use Goat\Domain\Projector\State\StateStore;
use PHPUnit\Framework\TestCase;

class ArrayStateStoreTest extends TestCase
{
    use StateStoreTestTrait;

    /**
     * Create state store.
     */
    protected function createStateStore(): StateStore
    {
        return new ArrayStateStore();
    }

    public function testLockUpserts(): void
    {
        $this->doTestLockUpserts($this->createStateStore());
    }

    public function testLockWhenExists(): void
    {
        $this->doTestLockWhenExists($this->createStateStore());
    }

    public function testUnlockUpserts(): void
    {
        $this->doTestUnlockUpserts($this->createStateStore());
    }

    public function testUnlockWhenExists(): void
    {
        $this->doTestUnlockWhenExists($this->createStateStore());
    }

    public function testUpdateUpserts(): void
    {
        $this->doTestUpdateUpserts($this->createStateStore());
    }

    public function testUpdateWhenExists(): void
    {
        $this->doTestUpdateWhenExists($this->createStateStore());
    }

    public function testUpdateResetLocking(): void
    {
        $this->doTestUpdateResetLocking($this->createStateStore());
    }

    public function testUpdateResetError(): void
    {
        $this->doTestUpdateResetError($this->createStateStore());
    }

    public function testErrorUpserts(): void
    {
        $this->doTestErrorUpserts($this->createStateStore());
    }

    public function testErrorWhenExists(): void
    {
        $this->doTestErrorWhenExists($this->createStateStore());
    }

    public function testErrorResetLocking(): void
    {
        $this->doTestErrorResetLocking($this->createStateStore());
    }

    public function testErrorWithoutUnlock(): void
    {
        $this->doTestErrorWithoutUnlock($this->createStateStore());
    }

    public function testExceptionUpserts(): void
    {
        $this->doTestExceptionUpserts($this->createStateStore());
    }

    public function testExceptionWhenExists(): void
    {
        $this->doTestExceptionWhenExists($this->createStateStore());
    }

    public function testExceptionResetLocking(): void
    {
        $this->doTestExceptionResetLocking($this->createStateStore());
    }

    public function testExceptionWithoutUnlock(): void
    {
        $this->doTestExceptionWithoutUnlock($this->createStateStore());
    }
}
