<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Projector\State;

use Goat\Domain\Projector\State\GoatStateStore;
use Goat\Domain\Projector\State\StateStore;
use Goat\Runner\Runner;
use Goat\Runner\Testing\DatabaseAwareQueryTest;
use Goat\Runner\Testing\TestDriverFactory;

final class GoatStateStoreTest extends DatabaseAwareQueryTest
{
    use StateStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getSupportedDrivers(): ?array
    {
        return ['pgsql'];
    }

    /**
     * {@inheritdoc}
     */
    protected function createTestSchema(Runner $runner)
    {
        $runner->execute(
            <<<SQL
            CREATE TABLE IF NOT EXISTS "projector_state" (
                "id" varchar(500) NOT NULL,
                "created_at" timestamp NOT NULL DEFAULT current_timestamp,
                "updated_at" timestamp NOT NULL DEFAULT current_timestamp,
                "last_position" bigint NOT NULL DEFAULT 0,
                "last_valid_at" timestamp NOT NULL DEFAULT current_timestamp,
                "is_locked" bool NOT NULL DEFAULT false,
                "is_error" bool NOT NULL DEFAULT false,
                "error_code" bigint NOT NULL DEFAULT 0,
                "error_message" text DEFAULT null,
                "error_trace" text DEFAULT null,
                PRIMARY KEY("id")
            );
            SQL
        );

        $runner->execute('DELETE FROM "projector_state";');
    }

    /**
     * Create your own event store
     *
     * Override this for your own event store.
     */
    protected function createStateStore(Runner $runner, string $schema): StateStore
    {
        $this->createTestSchema($runner);

        return new GoatStateStore($runner, $schema);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testLockUpserts(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestLockUpserts($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testLockWhenExists(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestLockWhenExists($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUnlockUpserts(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestUnlockUpserts($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUnlockWhenExists(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestUnlockWhenExists($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUpdateUpserts(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestUpdateUpserts($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUpdateWhenExists(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestUpdateWhenExists($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUpdateResetLocking(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestUpdateResetLocking($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testUpdateResetError(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestUpdateResetError($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testErrorUpserts(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestErrorUpserts($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testErrorWhenExists(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestErrorWhenExists($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testErrorResetLocking(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestErrorResetLocking($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testErrorWithoutUnlock(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestErrorWithoutUnlock($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testExceptionUpserts(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestExceptionUpserts($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testExceptionWhenExists(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestExceptionWhenExists($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testExceptionResetLocking(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestExceptionResetLocking($stateStore);
    }

    /**
     * @dataProvider runnerDataProvider
     */
    public function testExceptionWithoutUnlock(TestDriverFactory $factory): void
    {
        $runner = $factory->getRunner();

        $stateStore = $this->createStateStore($runner, $factory->getSchema());

        $this->doTestExceptionWithoutUnlock($stateStore);
    }
}

