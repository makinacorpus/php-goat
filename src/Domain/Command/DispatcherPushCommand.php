<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\Event\Dispatcher;
use Goat\Domain\EventStore\DefaultNameMap;
use Goat\Domain\EventStore\NameMap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class DispatcherPushCommand extends Command
{
    protected static $defaultName = 'dispatcher:push';

    /** @var Dispatcher */
    private $dispatcher;

    /** @var SerializerInterface */
    private $serializer;

    /** @var NameMap */
    private $nameMap;

    /**
     * Default constructor
     */
    public function __construct(Dispatcher $dispatcher, SerializerInterface $serializer, ?NameMap $nameMap = null)
    {
        parent::__construct();

        $this->dispatcher = $dispatcher;
        $this->serializer = $serializer;
        $this->nameMap = $nameMap ?? new DefaultNameMap();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Push a command into the dispatcher')
            ->addArgument('event-name', InputArgument::REQUIRED, 'Command name, usually a class name')
            ->addOption('content-type', 't', InputOption::VALUE_REQUIRED, 'Content type, must be a known type to the serializer component', 'json')
            ->addOption('content', 'c', InputOption::VALUE_REQUIRED, 'Content formatted using the given --content-type format (default is JSON)')
            ->addOption('async', 'a', InputOption::VALUE_NONE, 'Only pushes the event into the bus for later asynchronous execution')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('event-name');
        $format = $input->getOption('content-type');
        $data = $input->getOption('content');

        if ($data) {
            $message = $this->serializer->deserialize($data, $type, $format);
        } else {
            $className = $this->nameMap->getType($type);
            if (!\class_exists($className)) {
                throw new \InvalidArgumentException(\sprintf("No content was provided, and I cannot instanciate the '%s' class", $className));
            }
            $message = new $className();
        }

        if ($input->getOption('async')) {
            $this->dispatcher->dispatch($message);
            $output->writeln(\sprintf("<info>Message '%s' has been pushed into the asynchronous command bus successfuly</info>", $type));
        } else {
            $this->dispatcher->process($message);
            $output->writeln(\sprintf("<info>Message '%s' has been processed successfuly</info>", $type));
        }

        return 0;
    }
}
