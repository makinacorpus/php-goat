<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\EventStore\EventStore;
use Goat\Domain\EventStore\Property;
use Goat\Domain\EventStore\Exchange\EventExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class EventStoreQueryCommand extends Command
{
    private $eventExporter;
    private $eventStore;
    protected static $defaultName = 'eventstore:query';

    /**
     * Default constructor
     */
    public function __construct(EventStore $eventStore, ?EventExporter $eventExporter)
    {
        parent::__construct();

        $this->eventExporter = $eventExporter;
        $this->eventStore = $eventStore;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Query the event store')
            ->addOption('aggregate-id', null, InputOption::VALUE_REQUIRED, 'Aggregate identifier(s)')
            ->addOption('aggregate-type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Aggregate type(s)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Include all event, including failed ones, superseed --all')
            ->addOption('failed', null, InputOption::VALUE_NONE, 'Display only rollbacked events, excluded when not specified')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Query a single namespace', Property::DEFAULT_NAMESPACE)
            ->addOption('output-format', null, InputOption::VALUE_REQUIRED, "Outputs result with the given format, use 'binary' for later import")
            ->addOption('reverse', null, InputOption::VALUE_NONE, 'Query in descending order, all other filters will be reversed as well')
            ->addOption('start-date', null, InputOption::VALUE_REQUIRED, 'Start at given date/time, can be any string that new DateTime("...") will understand')
            ->addOption('start-position', null, InputOption::VALUE_REQUIRED, 'Start at given date/time, can be any string that new DateTime("...") will understand')
            ->addOption('start-revision', null, InputOption::VALUE_REQUIRED, 'Start at given revision, do not use with --start-at')
            ->addOption('stop-date', null, InputOption::VALUE_REQUIRED, 'Stop at given date/time, can be any string that new DateTime("...") will understand')
        ;
    }

    private function normalizeDate(InputInterface $input, OutputInterface $output, string $option): ?\DateTimeInterface
    {
        $value = $input->getOption($option);

        if ($value) {
            $date = new \DateTimeImmutable($value);

            if (!$date) {
                throw new \InvalidArgumentException(sprintf("--%s date format is invalid", $option));
            }

            return $date;
        }

        return null;
    }

    /**
     * Output list as plain text
     */
    protected function outputAsPlainText(iterable $stream, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders([
            'pos.',
            'created at',
            'valid at',
            'aggr. type',
            'aggr. id',
            'rev.',
            'name',
            'fail',
        ]);

        /** @var \Goat\Domain\EventStore\Event $event */
        foreach ($stream as $event) {
            $table->addRow([
                $event->getPosition(),
                $event->createdAt()->format('Y-m-d H:i:s'),
                $event->validAt()->format('Y-m-d H:i:s'),
                $event->getAggregateType(),
                $event->getAggregateId(),
                $event->getRevision(),
                $event->getName(),
                $event->hasFailed() ? 'FAIL' : '',
            ]);
        }

        $table->render();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $displayOnlyFailed = $input->getOption('failed');
        $displayAll = $input->getOption('all');
        if ($displayOnlyFailed && $displayAll) {
            $output->writeln('<comment>--all option superseeds --failed</comment>');
        }

        $aggregateId = $input->getOption('aggregate-id');
        $aggregateType = $input->getOption('aggregate-type');

        $namespace = $input->getOption('namespace');
        if (Property::DEFAULT_NAMESPACE !== $namespace) {
            $output->writeln('<comment>--namespaceNAMESPACE_ option is not supported yet, falling back to default</comment>');
        }

        $dateStart = $this->normalizeDate($input, $output, "start-date");
        $dateStop = $this->normalizeDate($input, $output, "stop-date");
        if ($dateStop && !$dateStart) {
            $output->writeln('<comment>Using --stop-date without --start-date is not supported, ignoring</comment>');
        }

        $position = $input->getOption('start-position');
        if ($dateStart || $dateStop) {
            $output->writeln('<comment>Using --start-position with --start-date or --stop-date will yield inconsistent event history</comment>');
        }
        $revision = $input->getOption('start-revision');
        if (!$aggregateId && $revision) {
            $output->writeln('<comment>Using --start-revision without --aggregate-id will yield inconsistent event history</comment>');
        }
        if ($position && $revision) {
            $output->writeln('<comment>Using --start-revision with --start-position will yield inconsistent event history</comment>');
        }

        $query = $this->eventStore->query();

        if ($displayAll) {
            $query->failed(null);
        } else if ($displayOnlyFailed) {
            $query->failed(true);
        } else {
            $query->failed(false);
        }

        if ($dateStart && $dateStop) {
            $query->betweenDates($dateStart, $dateStop);
        } else if ($dateStart) {
            $query->fromDate($dateStart);
        }

        if ($aggregateId) {
            $query->for($aggregateId);
        }
        if ($aggregateType) {
            $query->withType($aggregateType);
        }
        if ($position) {
            $query->fromPosition($position);
        }
        if ($revision) {
            $query->fromRevision($revision);
        }
        if ($input->getOption('reverse')) {
            $query->reverse(true);
        }

        $stream = $query->execute();

        if ($format = $input->getOption('output-format')) {
            if (!$this->eventExporter) {
                throw new \InvalidArgumentException(\sprintf("You cannot export to file without symfony/serializer-pack installed"));
            }
            $this->eventExporter->exportAs($stream, STDOUT, $format);
        } else {
            $this->outputAsPlainText($stream, $output);
        }
    }
}
