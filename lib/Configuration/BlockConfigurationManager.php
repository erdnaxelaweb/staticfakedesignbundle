<?php
/*
 * staticfakedesignbundle.
 *
 * @package   DesignBundle
 *
 * @author    florian
 * @copyright 2023-present Florian ALEXANDRE
 * @license   https://github.com/erdnaxelaweb/staticfakedesign/blob/main/LICENSE
 */

declare(strict_types=1);

namespace ErdnaxelaWeb\StaticFakeDesign\Configuration;

use ErdnaxelaWeb\StaticFakeDesign\Fake\BlockGenerator\AttributeGeneratorRegistry;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlockConfigurationManager extends AbstractConfigurationManager
{
    public function __construct(
        array                                   $definitions,
        protected AttributeGeneratorRegistry $attributeGeneratorRegistry
    ) {
        parent::__construct($definitions);
    }

    protected function configureFieldOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->define('required')
            ->default(false)
            ->allowedTypes('bool')
            ->info('Tell if field is required or not');

        $optionsResolver->define('type')
            ->required()
            ->allowedTypes('string')
            ->info('Field type');

        $optionsResolver->define('value')
            ->default(null)
            ->info('Forced value');

        $optionsResolver->define('options')
            ->default([])
            ->normalize(function (Options $options, $fieldDefinitionOptions) {
                $optionsResolver = new OptionsResolver();
                $attributeGenerator = $this->attributeGeneratorRegistry->getGenerator($options['type']);
                $attributeGenerator->configureOptions($optionsResolver);
                return $this->resolveOptions($options['type'], $optionsResolver, $fieldDefinitionOptions);
            })
            ->allowedTypes('array')
            ->info('Options to pass to the field type generator');
    }

    protected function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->define('attributes')
            ->required()
            ->allowedTypes()
            ->normalize(function (Options $options, $fieldsDefinitionOptions) {
                $optionsResolver = new OptionsResolver();
                $this->configureFieldOptions($optionsResolver);
                $fieldsDefinition = [];
                foreach ($fieldsDefinitionOptions as $fieldIdentifier => $fieldDefinitionOptions) {
                    $fieldsDefinition[$fieldIdentifier] = $this->resolveOptions(
                        $fieldIdentifier,
                        $optionsResolver,
                        $fieldDefinitionOptions
                    );
                }
                return $fieldsDefinition;
            })
            ->info('Array of field definition');

        $optionsResolver->define('views')
            ->default([])
            ->allowedTypes('array');

        $optionsResolver->define('models')
            ->default([])
            ->allowedTypes('array');
    }
}
