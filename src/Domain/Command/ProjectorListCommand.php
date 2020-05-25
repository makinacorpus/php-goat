<?php

declare(strict_types=1);

namespace Goat\Domain\Command;

use Goat\Domain\Projector\NoRuntimeProjector;
use Goat\Domain\Projector\ProjectorRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ProjectorListCommand extends Command
{
    protected static $defaultName = 'projector:list';

    private ProjectorRegistry $projectorRegistry;

    /**
     * Default constructor
     */
    public function __construct(ProjectorRegistry $projectorRegistry)
    {
        parent::__construct();

        $this->projectorRegistry = $projectorRegistry;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('List known projectors')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $table->setHeaders(['Name', 'Class', 'Enabled at runtime']);

        foreach ($this->projectorRegistry->getAll() as $projector) {
            if ($projector instanceof NoRuntimeProjector) {
                $table->addRow([
                    $projector->getIdentifier(),
                    \get_class($projector),
                    'Manual run only',
                ]);
            } else {
                $table->addRow([
                    $projector->getIdentifier(),
                    \get_class($projector),
                    'Enabled',
                ]);
            }
        }

        $table->render();

        return 0;
    }
}
