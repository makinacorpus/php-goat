<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\Projector\ProjectorRegistry;
use Goat\Domain\Projector\ReplayableProjector;
use Goat\Domain\Projector\Worker\Worker;
use Goat\Domain\Projector\Worker\WorkerEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectorPlayCommand extends Command
{
    protected static $defaultName = 'projector:play';

    private ProjectorRegistry $projectorRegistry;
    private Worker $worker;

    /**
     * Default constructor
     */
    public function __construct(ProjectorRegistry $projectorRegistry, Worker $worker)
    {
        parent::__construct();

        $this->projectorRegistry = $projectorRegistry;
        $this->worker = $worker;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Play projectors.')
            ->addArgument('projector', InputArgument::OPTIONAL, 'Projector identifier or className')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset all projector\'s data and replay it for all events present in EventStore (only available for ReplayableProjector)')
            ->addOption('continue', null, InputOption::VALUE_NONE, 'If set, ignore current projectors state and restart playing erroneous ones.')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Deprecated ignored option.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('reset') and $input->getOption('date')) {
            throw new InvalidArgumentException("You can not use both 'reset' and 'date' options, you have to choose one.");
        }

        $projectorId = $input->getArgument('projector');

        if ($input->getOption('reset')) {
            if (!$projectorId) {
                throw new InvalidArgumentException("You cannot specify --reset without a projector identifier.");
            }
        }

        if ($input->getOption('date')) {
            $output->writeln("<error>--date option is ignored and will be replaced in a near future.</error>");
        }

        if ($projectorId) {
            $this->handleSingleProjector($input, $output, $projectorId);
        } else {
            $this->handleAllProjectors($input, $output);
        }
    }

    private function handleSingleProjector(InputInterface $input, OutputInterface $output, string $projectorId): void
    {
        if ($input->getOption('reset')) {
            $projector = $this->projectorRegistry->find($projectorId);

            if (!$projector instanceof ReplayableProjector) {
                throw new \InvalidArgumentException(\sprintf(
                    "Cannot 'reset' projector '%s': does not implement %s",
                    $projectorId,
                    ReplayableProjector::class
                ));
            }

            $this->worker->reset($projector);
        }

        $progressBar = $this->prepareProgressBar($output);
        $progressBar->start();

        $this->worker->play(
            $projectorId,
            (bool) $input->getOption('continue')
        );
    }

    private function handleAllProjectors(InputInterface $input, OutputInterface $output): void
    {

        $progressBar = $this->prepareProgressBar($output);
        $progressBar->start();

        $this->worker->playAll(
            (bool) $input->getOption('continue')
        );
    }

    private function prepareProgressBar(OutputInterface $output): ProgressBar
    {
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('debug');

        $dispatcher = $this->worker->getEventDispatcher();

        $dispatcher->addListener(
            WorkerEvent::BEGIN,
            static function (WorkerEvent $event) use ($progressBar) {
                $progressBar->setMaxSteps($event->getStreamSize());
            }
        );

        $dispatcher->addListener(
            WorkerEvent::END,
            static function (WorkerEvent $event) use ($progressBar, $output) {
                $progressBar->finish();
                $output->writeln("");
            }
        );

        $dispatcher->addListener(
            WorkerEvent::NEXT,
            static function (WorkerEvent $event) use ($progressBar) {
                $progressBar->setProgress($event->getCurrentPosition());
            }
        );

        return $progressBar;
    }
}
