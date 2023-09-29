<?php
/*
 * DesignBundle.
 *
 * @package   DesignBundle
 *
 * @author    florian
 * @copyright 2018 Novactive
 * @license   https://github.com/Novactive/NovaHtmlIntegrationBundle/blob/master/LICENSE
 */

declare( strict_types=1 );

namespace ErdnaxelaWeb\StaticFakeDesign\Fake\Generator;


use ErdnaxelaWeb\StaticFakeDesign\Exception\ConfigurationNotFoundException;
use ErdnaxelaWeb\StaticFakeDesign\Fake\ContentGenerator\ContentFieldGeneratorRegistry;
use ErdnaxelaWeb\StaticFakeDesign\Fake\FakerGenerator;
use ErdnaxelaWeb\StaticFakeDesign\Configuration\TaxonomyEntryConfigurationManager;
use ErdnaxelaWeb\StaticFakeDesign\Value\TaxonomyEntry;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaxonomyEntryGenerator extends AbstractContentGenerator
{

    public function __construct(
        protected TaxonomyEntryConfigurationManager $taxonomyEntryConfigurationManager,
        FakerGenerator                              $fakerGenerator,
        ContentFieldGeneratorRegistry               $fieldGeneratorRegistry
    )
    {
        parent::__construct( $fakerGenerator, $fieldGeneratorRegistry );
    }

    public function configureOptions(OptionsResolver $optionResolver): void
    {
        parent::configureOptions($optionResolver);
        $optionResolver->define('identifier')
            ->required()
            ->allowedTypes('string')
            ->info('Identifier of the taxonomy entry to generate. See erdnaxelaweb.static_fake_design.taxonomy_entry_definition');

    }

    /**
     * @throws ConfigurationNotFoundException
     */
    public function __invoke(string $type): TaxonomyEntry
    {
        $configuration = $this->taxonomyEntryConfigurationManager->getConfiguration( $type );
        return TaxonomyEntry::createLazyGhost( function ( TaxonomyEntry $instance ) use ( $type, $configuration ) {
            $instance->__construct(
                $this->fakerGenerator->randomNumber(),
                $this->fakerGenerator->sentence(),
                $type,
                $this->generateFieldsValue( $configuration['fields'] )
            );
        } );
    }
}
