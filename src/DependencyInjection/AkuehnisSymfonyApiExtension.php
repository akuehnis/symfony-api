<?php
namespace Akuehnis\SymfonyApi\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class AkuehnisSymfonyApiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        if (isset($config['documentation']) && 0 < count($config['documentation'])){
            $container->setParameter('akuehnis_symfony_api.documentation', $config['documentation']);
        } else {
            $container->setParameter('akuehnis_symfony_api.documentation', [
                'servers' =>  [
                    [
                        'url' => 'https://api.example.com/v1',
                    ],
                    [
                        'url' => 'http://api.example.com/v1',
                    ]
                ],
                'info' => [
                    'title' => 'My App',
                    'description' => 'This is an awesome app!',
                    'version' => '1.0.0',
                ]
            ]);
        }

        $loader = new XmlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.xml');
    }
}