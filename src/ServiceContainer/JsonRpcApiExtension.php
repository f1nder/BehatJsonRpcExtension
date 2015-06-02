<?php

namespace Solution\JsonRpcApiExtension\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Symfony2Extension\ServiceContainer\Symfony2Extension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Graze\GuzzleHttp\JsonRpc\Client;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class JsonRpcApiExtension implements Extension
{
    const CLIENT_ID = 'jsonrpc_api.client';

    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigKey()
    {
        return 'jsonrpc_api';
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('base_url')
                    ->info('The URL for send request with use JSON-RPC protocol.')
                    ->defaultValue('http://localhost')
                    ->beforeNormalization()
                        ->ifTrue(function ($value){
                            return strpos($value, 'http') === false;
                        })
                        ->then(function ($value) {
                            return $value ? 'http://' . $value : $value;
                        })
                    ->end()
                ->end()
            ->end();
    }

    /**
     * {@inheritDoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $this->loadClient($container, $config);
        $this->loadContextInitializer($container, $config);
    }

    private function loadClient(ContainerBuilder $container, $config)
    {
        $clientDefinition = new Definition('Solution\JsonRpcApiExtension\Client\JsonRpcClient');
        $clientDefinition->setFactory('Solution\JsonRpcApiExtension\Client\JsonRpcClient::factory');
        $clientDefinition->setArguments($config);

        $container->setDefinition(self::CLIENT_ID, $clientDefinition);
    }

    private function loadContextInitializer(ContainerBuilder $container, $config)
    {
        $definition = new Definition('Solution\JsonRpcApiExtension\Context\Initializer\ClientAwareInitializer', array(
            new Reference(self::CLIENT_ID),
            $config
        ));

        $definition->addTag(ContextExtension::INITIALIZER_TAG);
        $container->setDefinition('jsonrpc_api.context_initializer', $definition);
    }
}
