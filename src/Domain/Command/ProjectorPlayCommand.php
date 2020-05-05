<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\EventStore\EventStore;
use Goat\Domain\Projector\Projector;
use Goat\Domain\Projector\ProjectorRepository;
use Goat\Domain\Projector\ReplayableProjector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectorPlayCommand extends Command
{
    protected static $defaultName = 'projector:play';

    /** @var ProjectorRepository */
    private $projectorRepository;

    /** @var EventStore */
    private $eventStore;

    /**
     * Default constructor
     */
    public function __construct(ProjectorRepository $projectorRepository, EventStore $eventStore)
    {
        parent::__construct();

        $this->projectorRepository = $projectorRepository;
        $this->eventStore = $eventStore;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Play a projector from the last processed event date')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Projector identifier or className')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Reset all projector\'s data and replay it for all events present in EventStore (only available for ReplayableProjector)')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Play projector from a specific date (format Y-m-d)')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $identifier = $input->getArgument('identifier');

        if (!$projector = $this->projectorRepository->findByIdentifierOrClassName($identifier)) {
            throw new \InvalidArgumentException(\sprintf(
                "No projector can't be found for identifier or className '%s'",
                $identifier
            ));
        }

        if($input->getOption('reset')) {
            $this->handleReset($projector);
        }

        if ($dateOption = $input->getOption('date')) {
            if (!$date = \DateTimeImmutable::createFromFormat('Y-m-d', $input->getOption('date'))) {
                throw new \InvalidArgumentException(\sprintf(
                    "Can't create Datetime from given date (%s). Format should be Y-m-d",
                    $dateOption
                ));
            }
        } else {
            $date = $projector->getLastProcessedEventDate();
        }

        $events = $this->findEventsToProcess($date);

        if (!$total = \count($events)) {
            $output->writeln('there is no event to process for this projector.');

            return 0;
        }

        $output->writeln(\sprintf("%d events has(ve) to be processed", $total));

        $progress = new ProgressBar($output);
        $progress->setMaxSteps($total);

        $failed = 0;
        foreach ($events as $event) {
            try {
                $projector->onEvent($event);
            } catch (\Throwable $e) {
                $failed++;
            }
            $progress->advance(1);
        }

        $progress->finish();
        $output->writeln("");

        $output->writeln(\sprintf(
            "%d events(s) has(ve) been correctly process, %d failed.",
            $total - $failed,
            $failed
        ));

        return 0;
    }

    private function handleReset(Projector $projector): void
    {
        if (!$projector instanceof ReplayableProjector) {
            throw new \InvalidArgumentException(\sprintf(
                "Can't 'reset' Projector : '%s' does not implement %s",
                $projector::class,
                ReplayableProjector::class
            ));
        }

        $projector->reset();
    }

    private function findEventsToProcess(?\DateTimeInterface $date): ?iterable
    {
        $query =  $this
            ->eventStore
            ->query()
            ->failed(false)
        ;

        if($date) {
            $query = $query->fromDate($date);
        }

        return $query->execute();
    }
}
