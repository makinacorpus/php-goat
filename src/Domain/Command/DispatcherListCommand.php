<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\Event\WithDescription;
use Goat\Domain\EventStore\NameMap;
use Goat\Domain\Generator\MessageExtractor;
use Goat\Domain\Generator\MessageInformation;
use Goat\Domain\Generator\MessageRstFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DispatcherListCommand extends Command
{
    protected static $defaultName = 'dispatcher:list';

    /** @var NameMap */
    private $nameMap;

    /**
     * Default constructor
     */
    public function __construct(NameMap $nameMap)
    {
        parent::__construct();

        $this->nameMap = $nameMap;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('List commands the process can process, list might be incomplete')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, "Format, can be 'plain' or 'rst'", 'plain')
            ->addOption('group', null, InputOption::VALUE_OPTIONAL, "Only given group")
            ->addOption('desc', null, InputOption::VALUE_NONE, "Show descriptions")
            ->addOption('config', null, InputOption::VALUE_REQUIRED, "Use configuration file")
            ->setHelp(<<<EOT
Write messages documentation.

For it to work, you need your messages to be explicitely named within the
"goat.normalization.map" configuration key in the config/packages/goat.yaml
file.

Messages descriptions will be parsed from their classes PHP-doc blocks,
in order to provide meta-information, you must add annotation-like tags
within with the following formalism:

    [key=value]

Or for multiple value properties:

    [key=value1,value2,...]

For example:

    /**
     * This is the message summary
     *
     * This is a long message description that will be shown in
     * detailed message documentation.
     *
     * [group=client,crud]
     * [scope=internal]
     */
    final class MyMessage {}

Allowed properties are:
    - group: one or more groups
    - scope: "internal" if set, will be documented as being internal

If you wish to generated documentation for multiple groups in different .rst
files, you need to write a JSON configuration file as such:

    {
        "group1": {
            "name": "This is group one !"
            "file": "/target/file1.rst"
        },
        "group2": {
            "name": "This is group two."
            "file": "/target/file1.rst"
        }
    }

And pass this filename along the --config=my_config.json argument.

This option applies only for restructured text output.

If the target file exists, the parser will attempt to find comment markers
within in order to replace only the generated text and leave the rest
untouched, so you may document properly your application.

EOT
            )
        ;
    }

    /**
     * Format and output plain text.
     */
    private function formatPlain(OutputInterface $output, bool $withDescription, ?string $onlyGroup = null): void
    {
        $table = new Table($output);
        if ($withDescription) {
            $table->setHeaders(["Name", "PHP class", "Description", "Describe"]);
        } else {
            $table->setHeaders(["Name", "PHP class", "Describe"]);
        }

        $count = 0;
        $extractor = new MessageExtractor($this->nameMap);

        /** @var \Goat\Domain\Generator\MessageInformation $message */
        foreach ($extractor->listMessages() as $message) {
            if ($onlyGroup && !\in_array($onlyGroup, $message->getGroups())) {
                continue;
            }
            $affichable = \in_array(WithDescription::class, \class_implements($message->getClass()));
            if ($withDescription) {
                $table->addRow([$message->getName(), $message->getClass(), $message->getSummary(), $affichable ? "Yes" : '-']);
            } else {
                $table->addRow([$message->getName(), $message->getClass(), $affichable ? "Yes" : '-']);
            }
            $count++;
        }

        $table->render();
        $output->writeln(\sprintf("%d messages trouvés.", $count));
    }

    /**
     * Format and output plain RestructuredText.
     */
    private function formatRst(OutputInterface $output, ?string $onlyGroup = null)
    {
        $extractor = new MessageExtractor($this->nameMap);
        $messages = $extractor->listMessages();

        if ($onlyGroup) {
            $messages = \array_filter(
                $messages,
                static function (MessageInformation $message) use ($onlyGroup) {
                    return \in_array($onlyGroup, $message->getGroups());
                }
            );
        }

        $output->writeln(MessageRstFormatter::formatPage($messages));
        $output->writeln("");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $onlyGroup = $input->getOption('group');

        if ($filename = $input->getOption('config')) {
            $configDirname = \dirname($filename);
            if ('rst' !== $input->getOption('format')) {
                throw new \InvalidArgumentException("Format must be 'rst' when using --config");
            }
            if (!\file_exists($filename)) {
                throw new \InvalidArgumentException(\sprintf("File '%s' does not exists for --config option", $filename));
            }
            if (!\is_readable($filename)) {
                throw new \InvalidArgumentException(\sprintf("File '%s' cannot be read for --config option", $filename));
            }
            if (!$config = @\json_decode(\file_get_contents($filename), true)) {
                throw new \InvalidArgumentException(\sprintf("File '%s' is not valid JSON --config option", $filename));
            }

            // Normalize configuration first.
            foreach ($config as $group => $desc) {
                if (empty($desc['name'])) {
                    $output->writeln(\sprintf("<error>You should consider adding a 'name' for group '%s'", $group));
                }
                if (empty($desc['file'])) {
                    throw new \InvalidArgumentException(\sprintf("Missing 'file' for group '%s'", $group));
                }

                $filename = $desc['file'];
                // Consider filenames NOT starting with '/' or './' as being
                // relative to the config file itself. 
                if ('/' !== $filename[0] || './' !== \substr($filename, 0, 2)) {
                    $original = $filename;
                    $filename = $configDirname.'/'.$filename;
                    $output->writeln(\sprintf("%s -> %s", $original, $filename));
                }

                if (!\is_string($filename)) {
                    throw new \InvalidArgumentException(\sprintf("'file' must be a string for group '%s'", $group));
                }
                if (\file_exists($filename)) {
                    if (!\is_writable($filename)) {
                        throw new \InvalidArgumentException(\sprintf("File '%s' cannot be written for group '%s'", $filename, $group));
                    }
                }
            }

            $extractor = new MessageExtractor($this->nameMap);
            $messages = $extractor->listMessages();
            $foundGroups = $extractor->getFoundGroups();

            // Now proceed.
            foreach ($config as $group => $desc) {
                $filename = $desc['file'];
                $output->writeln(\sprintf("Writing group '%s' in file '%s'", $group, $filename));

                // Amend found groups so we can advertise ignored ones.
                if (false !== ($index = \array_search($group, $foundGroups))) {
                    unset($foundGroups[$index]);
                }

                $groupMessages = \array_filter(
                    $messages,
                    static function (MessageInformation $message) use ($group) {
                        return \in_array($group, $message->getGroups());
                    }
                );

                if (empty($groupMessages)) {
                    $output->writeln(\sprintf("<error>No messages to document for group '%s'", $group));
                    continue;
                }

                if (\file_exists($filename)) {
                    $text = MessageRstFormatter::formatWithinExistingPage($groupMessages, \file_get_contents($filename));
                } else {
                    $text = MessageRstFormatter::formatPage($groupMessages, $desc['name'] ?? null);
                }

                \file_put_contents($filename, $text);
            }

            if ($foundGroups) {
                $output->writeln(\sprintf("<error>There are some ignored groups: '%s'</error>", \implode("', '", $foundGroups)));
            }
        } else {
            switch ($input->getOption('format')) {

                case 'rst':
                    $this->formatRst($output, $onlyGroup);
                    break;

                default:
                    $withDescription = (bool)$input->getOption('desc');
                    $this->formatPlain($output, $withDescription, $onlyGroup);
                    break;
            }
        }

        return 0;
    }
}
