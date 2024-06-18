<?php

namespace Mugo\PageBundle\DependencyInjection;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\Config\Resource\FileResource;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class MugoPageExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $fileLocator = new FileLocator(__DIR__ . '/../Resources/config');
        $loader = new Loader\YamlFileLoader( $container, $fileLocator );
        $loader->load( 'services.yaml' );
    }

    /**
     * Extending the 'ibexa' configuration section
     *
     * @param ContainerBuilder $container
     */
    public function prepend( ContainerBuilder $container )
    {
        // more specific configuration before more generic config
        $standardConfigFileTypes = array(
            'ibexa',
        );
        foreach( $standardConfigFileTypes as $file )
        {
            $configFile = __DIR__ . '/../Resources/config/'. $file .'.yaml';
            if( file_exists( $configFile ) )
            {
                $config = Yaml::parse( file_get_contents( $configFile ) );
                if( !empty( $config ) && isset( $config[ 'ibexa' ] ) )
                {
                    $container->prependExtensionConfig( 'ibexa', $config[ 'ibexa' ] );
                    $container->addResource( new FileResource( $configFile ) );
                }
                else
                {
                    // report unexpected format
                }
            }
        }
    }
}