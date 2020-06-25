<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\Projector\NoRuntimeProjector;
use Goat\Domain\Projector\Projector;
use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Projector\State\StateStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectorListCommand extends Command
{
    protected static $defaultName = 'projector:list';

    private ProjectorRegistry $projectorRegistry;
    private StateStore $stateStore;

    /**
     * Default constructor
     */
    public function __construct(ProjectorRegistry $projectorRegistry, StateStore $stateStore)
    {
        parent::__construct();

        $this->projectorRegistry = $projectorRegistry;
        $this->stateStore = $stateStore;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription('List projector status');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['Name', 'Class', 'Last position', 'Last date', 'Error', 'Enabled at runtime']);

        foreach ($this->projectorRegistry->getAll() as $projector) {
            \assert($projector instanceof Projector);

            $id = $projector->getIdentifier();
            $state = $this->stateStore->latest($id);

            $error = "OK";
            $runtime = "Enabled";
            $position = '-';
            $date = '-';

            if ($state) {
                if ($state->isError()) {
                    $error = "Error";
                }
                $position = $state->getLatestEventPosition();
                $date = $state->getLatestEventDate()->format('Y-m-d H:i:s');
            }

            if ($projector instanceof NoRuntimeProjector) {
                $runtime = 'Manual run only';
            }

            $table->addRow([
                $projector->getIdentifier(),
                \get_class($projector),
                $position,
                $date,
                $error,
                $runtime,
            ]);
        }

        $table->render();

        return 0;
    }
}
