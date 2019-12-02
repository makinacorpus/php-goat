<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\Event\Dispatcher;
use Goat\Domain\EventStore\Exchange\EventImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class EventStoreImportCommand extends Command
{
    private $dispatcher;
    private $eventImporter;
    protected static $defaultName = 'eventstore:import';

    /**
     * Default constructor
     */
    public function __construct(Dispatcher $dispatcher, EventImporter $eventImporter)
    {
        parent::__construct();

        $this->dispatcher = $dispatcher;
        $this->eventImporter = $eventImporter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Import events from an event store export')
            ->addArgument('filename')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');
        $stream = $this->eventImporter->importFrom($filename);

        foreach ($stream as $message) {
            $this->dispatcher->process($message);
            $output->write(".");
        }

        $output->writeln("");
    }
}
