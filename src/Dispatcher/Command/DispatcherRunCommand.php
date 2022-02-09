<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Command;

use Goat\Dispatcher\Dispatcher;
use Goat\Dispatcher\Worker\Worker;
use Goat\Dispatcher\Worker\WorkerEvent;
use Goat\MessageBroker\MessageBroker;
use MakinaCorpus\Message\Envelope;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
final class DispatcherRunCommand extends Command
{
    protected static $defaultName = 'dispatcher:run';

    private Dispatcher $dispatcher;
    private MessageBroker $messageBroker;

    public function __construct(Dispatcher $dispatcher, MessageBroker $messageBroker)
    {
        parent::__construct();

        $this->dispatcher = $dispatcher;
        $this->messageBroker = $messageBroker;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Run bus worker daemon')
            // ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, "Number of messages to consume", null)
            // ->addOption('idle-sleep', 's', InputOption::VALUE_OPTIONAL, "Idle sleep time, in micro seconds", null)
            ->addOption('memory-limit', 'm', InputOption::VALUE_OPTIONAL, "Maximum memory consumption; eg. 128M, 1G, ...", null)
            ->addOption('time-limit', 't', InputOption::VALUE_OPTIONAL, "Maximum run time; eg. '1 hour', '2 minutes', ...", null)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startedAt = new \DateTimeImmutable();
        $startedTimestamp = $startedAt->getTimestamp();
        $memoryLimit = self::parseSize($input->getOption('memory-limit'));
        $timeLimit = self::parseTime($input->getOption('time-limit'));

        $worker = new Worker($this->dispatcher, $this->messageBroker);

        $handleTick = static function () use ($worker, $startedTimestamp, $memoryLimit, $timeLimit, $output) {
            if ($memoryLimit && $memoryLimit <= \memory_get_usage(true)) {
                $output->writeln("Memory limit reached, stopping worker.");
                $worker->stop();
            }
            if ($timeLimit && $timeLimit < time() - $startedTimestamp) {
                $output->writeln("Time limit reached, stopping worker.");
                $worker->stop();
            }
        };

        $eventDispatcher = $worker->getEventDispatcher();

        $eventDispatcher->addListener(
            WorkerEvent::IDLE,
            function (WorkerEvent $event) use ($output, $handleTick) {
                if ($output->isVeryVerbose()) {
                    $output->writeln(\sprintf("%s - IDLE received.", self::nowAsString()));
                }
                $handleTick();
            }
        );

        $eventDispatcher->addListener(
            WorkerEvent::NEXT,
            function (WorkerEvent $event) use ($output, $handleTick) {
                if ($output->isVerbose()) {
                    $output->writeln(\sprintf("%s - NEXT message: %s.", self::nowAsString(), self::messageAsString($event->getMessage())));
                }
                $handleTick();
            }
        );

        $worker->run();

        return 0;
    }

    private static function nowAsString(): string
    {
        return (new \DateTime())->format('Y-m-d H:i:s.uP');
    }

    private static function messageAsString($message): string
    {
        if (null === $message) {
            return "(null)";
        }
        if ($message instanceof Envelope) {
            return \sprintf("%s - %s", $message->getMessageId(), self::messageAsString($message->getMessage()));
        }
        if (\is_object($message)) {
            return \sprintf("%s", \get_class($message));
        }
        return \sprintf("%s (%s)", \gettype($message), (string)$message);
    }

    /**
     * Parse user input time in seconds.
     */
    private static function parseTime(?string $input): ?int
    {
        if ('' === $input || null === $input) {
            return null;
        }

        $interval = \DateInterval::createFromDateString($input);

        if (!$interval) {
            throw new \InvalidArgumentException(\sprintf("Invalid interval: '%s'", $input));
        }

        $reference = new \DateTimeImmutable();

        return $reference->add($interval)->getTimestamp() - $reference->getTimestamp();
    }

    /**
     * Parse user input size in bytes.
     */
    private static function parseSize(?string $input): ?int
    {
        if ('' === $input || null === $input) {
            return null;
        }

        $value = \strtolower(\trim($input));
        $suffix = \substr($value, -1);
        $factor = 1;

        switch ($suffix) {
            case 'g':
                $value = \substr($value, 0, -1);
                $factor = 1024 * 1024 * 1024;
                break;

            case 'm':
                $value = \substr($value, 0, -1);
                $factor = 1024 * 1024;
                break;

            case 'k':
                $value = \substr($value, 0, -1);
                $factor = 1024;
                break;

            default:
                break;
        }

        if (!\ctype_digit($value)) {
            throw new InvalidArgumentException(\sprintf("Invalid number of bytes: '%s'", $input));
        }

        return ((int) $value) * $factor;
    }
}
