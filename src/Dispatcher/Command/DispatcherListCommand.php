<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Command;

use Goat\Dispatcher\Message\WithDescription;
use Goat\Normalization\NameMap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @codeCoverageIgnore
 */
final class DispatcherListCommand extends Command
{
    protected static $defaultName = 'dispatcher:list';

    private NameMap $nameMap;

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
            ->addOption('desc', null, InputOption::VALUE_NONE, "Show descriptions")
            ->addOption('config', null, InputOption::VALUE_REQUIRED, "Use configuration file")
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

        foreach ($this->nameMap->getTypeMap() as $name => $class) {
            $affichable = \in_array(WithDescription::class, $class);
            if ($withDescription) {
                $table->addRow([$name, $class, 'Not Implemented', $affichable ? "Yes" : '-']);
            } else {
                $table->addRow([$name, $class, $affichable ? "Yes" : '-']);
            }
            $count++;
        }

        $table->render();
        $output->writeln(\sprintf("%d messages trouvés.", $count));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->formatPlain($output, (bool)$input->getOption('desc'));

        return 0;
    }
}
