<?php

declare(strict_types=1);

namespace Goat\Domain\Generator;

use Goat\Domain\EventStore\NameMap;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Generates meta-information for documenting messages.
 *
 * I'm not very happy of this code, but it should work.
 *
 * @experimental
 * @codeCoverageIgnore
 *
 * @todo
 *   Implement whitelist
 */
final class MessageExtractor
{
    /** @var NameMap */
    private $nameMap;

    /** @var string[] */
    private $groups = [];

    /**
     * Default constructor
     */
    public function __construct(NameMap $nameMap)
    {
        if (!\class_exists(DocBlockFactory::class)) {
            throw new \RuntimeException("You must install phpdocumentor/reflection-common");
        }
        $this->nameMap = $nameMap;
    }

    /**
     * Parse coma or space separated array of string
     */
    private function parseMultipleKeys(string $value): array
    {
        return \preg_split('/[,\n]+/', $value);
    }

    /**
     * Parse boolean value from string
     */
    private function parseBoolean(string $value): bool
    {
        switch (\strtolower($value)) {
            case '0':
            case '':
            case 'f':
            case 'false':
            case 'no':
                return false;
        }
        return true;
    }

    /**
     * Parse property value
     */
    private function parseProperty(string $name, string $value)
    {
        switch ($name) {
            case 'group':
                return $this->parseMultipleKeys($value);
            case 'scope':
            default:
                return \trim($value);
        }
    }

    /**
     * From class description string parse properties, summary and others
     */
    private function parseDescription(MessageInformation $message, ?string $description = null): MessageInformation
    {
        if (!$description) {
            return $message;
        }

        if (!$lines = \preg_split('/[\n\r]+/', $description)) {
            return $message;
        }

        // First line is the summary.
        $message = $message->withSummary(\array_shift($lines));
        $properties = [];

        foreach ($lines as $index => $line) {
            // Attempt property parsing.
            $line = \trim($line);
            $matches = [];
            if (\preg_match('/\[\s*([a-zA-Z0-9_-]+)\s*=(.*)]/is', $line, $matches)) {
                $name = $matches[1];
                if (isset($properties[$name])) {
                    \trigger_error(\sprintf("Property '%s' is defined more than once on class '%s'", $message->getClass()));
                } else {
                    $properties[$name] = $this->parseProperty($name, $matches[2]);
                }
                unset($lines[$index]);
            }
        }

        if ($properties) {
            $message = $message->withProperties($properties);
        }

        return $message->withDescription(
            // Remove potential duplicated line feed, may happen since we
            // may have removed lines from the description while parsing
            // properties.
            \preg_replace('/[\n\r]+/', "\n", \implode("\n", $lines))
        );
    }

    /**
     * Get all found groups
     */
    public function getFoundGroups(): array
    {
        return $this->groups;
    }

    /**
     * List messages
     *
     * @return MessageInformation[]
     */
    public function listMessages(): iterable
    {
        $ret = [];
        $this->groups = [];
        $factory = DocBlockFactory::createInstance();

        foreach ($this->nameMap->getTypeMap() as $name => $class) {
            $description = null;
            if (\class_exists($class)) {
                try {
                    $docBlock = $factory->create(new \ReflectionClass($class));
                    $description = \trim(
                        \preg_replace('/[\n\r]+/', "\n",
                            \sprintf("%s\n%s", $docBlock->getSummary(), $docBlock->getDescription())
                        )
                    );
                } catch (\Throwable $e) {}
            }
            $ret[] = $message = new MessageInformation($class, $name);
            if ($description) {
                $this->parseDescription($message, $description);
            }
            // Register found groups
            if ($groups = $message->getGroups()) {
                foreach ($groups as $group) {
                    if (!\in_array($group, $this->groups)) {
                        $this->groups[] = $group;
                    }
                }
            }
        }

        return $ret;
    }
}

/**
 * Formats RestructuredText.
 *
 * @experimental
 * @codeCoverageIgnore
 */
final class MessageRstFormatter
{
    /**
     * Generate link for message
     */
    private static function generateLinkName(string $name): string
    {
        return "message_".\str_replace(".", "_", $name);
    }

    /**
     * Format complete new page
     */
    public static function formatPage(iterable $messages, ?string $title = null): string
    {
        $title = $title ?? "Documentation des messages";
        $titleSep = \str_repeat("!", \strlen($title));

        $text = <<<EOT
{$title}
{$titleSep}

.. note::

    Cette document est générée. Pour plus d'informations consultez la
    documentation de la commande de génération:

    ``bin/console dispatcher:list --help``.

Tableau
=======

.. PLEASE DO NOT EDIT BELOW - GENERATED CODE START
.. message-table-start
EOT;

        $text .= "\n\n".self::formatMessageTable($messages)."\n";

        $text .= <<<EOT
.. message-table-end
.. PLEASE DO NOT EDIT ABOVE - GENERATED CODE END

Documentation
=============

.. PLEASE DO NOT EDIT BELOW - GENERATED CODE START
.. message-detail-list-start
EOT;

        /** @var \Goat\Domain\Generator\MessageInformation $message */
        foreach ($messages as $message) {
            $text .= "\n".self::formatMessageDetails($message)."\n";
        }

        return $text."\n".<<<EOT
.. message-detail-list-end
.. PLEASE DO NOT EDIT ABOVE - GENERATED CODE END
EOT;
    }

    /**
     * From existing rst file, regenerate target content
     */
    public static function formatWithinExistingPage(iterable $messages, string $existing): string
    {
        $list = "";
        /** @var \Goat\Domain\Generator\MessageInformation $message */
        foreach ($messages as $message) {
            $list .= "\n".self::formatMessageDetails($message)."\n\n";
        }

        return self::replaceBetween(
            self::replaceBetween(
                $existing."\n",
                "\n\n".self::formatMessageTable($messages)."\n",
                ".. message-table-start",
                ".. message-table-end"
            ),
            $list,
            ".. message-detail-list-start",
            ".. message-detail-list-end"
        );
    }

    /**
     * Format all messages table
     */
    public static function formatMessageTable(iterable $messages): string
    {
        Table::setStyleDefinition('rst', (new TableStyle())
            ->setHorizontalBorderChars('-')
            ->setVerticalBorderChars('|')
            ->setDefaultCrossingChar('+')
            ->setHeaderTitleFormat('%s')
            ->setFooterTitleFormat('%s')
        );

        $tableBuffer = new BufferedOutput();
        $table = new Table($tableBuffer);
        $table->setStyle('rst');
        $table->setHeaders(["Nom", "Classe", "Description"]);

        /** @var \Goat\Domain\Generator\MessageInformation $message */
        foreach ($messages as $message) {
            $name = $message->getName();
            $table->addRow([
                \sprintf(":ref:`%s <%s>`", $name, self::generateLinkName($name)),
                \sprintf("``%s``", $message->getClass()),
                ($summary = $message->getSummary()) ? $summary  : "*N/A*",
            ]);
            $table->addRow(new TableSeparator());
        }

        $output = '';

        $table->render();
        $lines = \preg_split('/[\n\r]+/', $tableBuffer->fetch());
        // Remove last line, since we have a separator, and border is wrong.
        $index = 0; $size = \count($lines) - 2;
        foreach ($lines as $line) {
            if (2 === $index) {
                 // We are between the header and content, redraw the line.
                 $line = \str_replace("-", "=", $line);
            }
            if ($index === $size) {
                // Ignore last line.
                break;
            }
            $output .= $line ."\n";
            $index++;
        }

        return $output;
    }

    /**
     * Format a single message details
     */
    public static function formatMessageDetails(MessageInformation $message): string
    {
        $name = $message->getName();
        $class = $message->getClass();
        $ref = self::generateLinkName($name);

        $description = $message->getDescription();
        $summary = $message->getSummary();

        if (!$description && !$summary) {
            $summary = <<<EOT
.. warning::

   Ce message n'a pas de documentation, veuillez documenter sa PHP-doc.
EOT;
        }

        return <<<EOT

------------

.. _{$ref}:

**{$name}** (``{$class}``)

{$summary}

{$description}
EOT;
    }

    /**
     * Replace text between $start and $end with given $replacement in $text
     */
    public static function replaceBetween(string $text, string $replacement, string $start, string $end): string
    {
        // Not invented here: found this somewhere on the internet,
        // can't remember where, shame on me.
        return \preg_replace(
            '#('.\preg_quote($start).')(.*?)('.\preg_quote($end).')#si',
            '$1'.$replacement.'$3',
            $text
        );
    }
}

/**
 * Message meta-information, not to be used at runtime.
 *
 * @experimental
 * @codeCoverageIgnore
 */
final class MessageInformation
{
    /** @var string */
    private $class;

    /** @var ?string */
    private $summary;

    /** @var ?string */
    private $description;

    /** @var mixed[] */
    private $properties = [];

    /** @var string */
    private $name;

    /**
     * Default constructor
     */
    public function __construct(string $class, ?string $name = null)
    {
        $this->class = $class;
        $this->name = $name ?? $class;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getGroups(): array
    {
        if (empty($this->properties['group'])) {
            return ['unclassified'];
        }
        return $this->properties['group'];
    }

    public function getScope(): ?string
    {
        return $this->properties['scope'] ?? null;
    }

    public function withSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function withDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function withProperties(array $properties)
    {
        $this->properties = $properties;

        return $this;
    }
}
