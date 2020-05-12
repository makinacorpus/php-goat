<?php

declare(strict_types=1);

namespace Goat\Preferences\Form;

use Goat\Preferences\Domain\Model\ValueSchema;
use Goat\Preferences\Domain\Model\ValueValidator;
use Goat\Preferences\Domain\Repository\PreferencesRepository;
use Goat\Preferences\Domain\Repository\PreferencesSchema;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type as Form;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Preferences value type.
 *
 * This does not support the 'hashmap' option, please write custom forms
 * for this one, depending upon your business.
 */
final class PreferenceValueType extends AbstractType
{
    /** @var PreferencesRepository */
    private $repository;

    /** @var null|PreferencesSchema */
    private $schema;

    /**
     * Default constructor
     */
    public function __construct(PreferencesRepository $repository, ?PreferencesSchema $schema = null)
    {
        $this->repository = $repository;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $name = null;
        if ($this->schema) {
            if (!$name = $options['name']) {
                throw new \InvalidArgumentException(\sprintf("You must specify preference 'name'"));
            }
        } else {
            throw new \InvalidArgumentException("You cannot use this form type without a schema");
        }

        $schema = $this->schema->getType($name);
        $default = $schema->getDefault();
        $current = $this->repository->get($name) ?? $default;

        $options = $this->buildSingleValueTypeOptions($schema, $options, $current);

        if ($schema->isCollection()) {
            throw new \InvalidArgumentException("collection value are not supported yet");
        } else {
            $builder->add('value', $options['entry_type'], $options['entry_options'] + [
                'constraints' => [
                    new Callback([
                        'callback' => static function ($value, ExecutionContextInterface $context) use ($schema) {
                            try {
                                ValueValidator::validate($schema, $value);
                            } catch (\InvalidArgumentException $e) {
                                $context->addViolation($e->getMessage());
                            }
                        },
                    ])
                ],
            ]);
        }

        $builder->addModelTransformer(new CallbackTransformer(
            static function ($value) {
                return ['value' => $value];
            },
            static function ($value) use ($default) {
                if (($value = $value['value']) === $default) {
                    return null;
                }
                return $value;
            }
        ));
    }

    /**
     * Build form type for value, return suitable input for CollectionType
     */
    private function buildSingleValueTypeOptions(ValueSchema $schema, array $options, $default = null): array
    {
        $defaultEntryOptions = ($options['entry_options'] ?? []) + [
            'attr' => ['novalidate' => 'novalidate', 'maxlength' => '500'],
            'data' => $default,
            'label' => $schema->getDescription(),
            'required' => false,
        ];

        if (isset($options['entry_type'])) {
            return [
                'entry_type' => $options['entry_type'],
                'entry_options' => $defaultEntryOptions,
            ];
        }

        switch ($schema->getNativeType()) {

            case 'bool':
                return [
                    'entry_type' => Form\CheckboxType::class,
                    'entry_options' => $defaultEntryOptions,
                ];

            case 'int':
                return [
                    'entry_type' => Form\IntegerType::class,
                    'entry_options' => $defaultEntryOptions + [
                        'html5' => true,
                    ],
                ];

            case 'float':
                return [
                    'entry_type' => Form\NumberType::class,
                    'entry_options' => $defaultEntryOptions + [
                        'html5' => true,
                    ],
                ];

            case 'string':
                return [
                    'entry_type' => Form\TextType::class,
                    'entry_options' => $defaultEntryOptions,
                ];

            default:
                throw new \InvalidArgumentException(\sprintf("type '%s' is not supported yet", $schema->getNativeType()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'groups' => [Constraint::DEFAULT_GROUP],
            'entry_type' => null, // FormType class name.
            'entry_options' => null, // FormType specific options.
            'help' => null,
            'label' => function (Options $options) {
                $name = $options['name'];
                // Do not autocompute the label if there's no name or no schema.
                if (!$name || !$this->schema || !$this->schema->has($name)) {
                    return null;
                }
                // This can return null.
                return $this->schema->getType($name)->getLabel();
            },
            'required' => false,
            'name' => null,
        ]);
    }
}
