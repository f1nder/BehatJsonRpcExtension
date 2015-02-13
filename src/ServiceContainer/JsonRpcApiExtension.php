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
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        // TODO: Implement process() method.
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        return 'jsonrpc_api';
    }

    /**
     * Initializes other extensions.
     *
     * This method is called immediately after all extensions are activated but
     * before any extension `configure()` method is called. This allows extensions
     * to hook into the configuration of other extensions providing such an
     * extension point.
     *
     * @param ExtensionManager $extensionManager
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        // TODO: Implement initialize() method.
    }

    /**
     * Setups configuration for the extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('base_url')
            ->defaultValue('http://localhost')
            ->end()
            ->end()
            ->end();
    }

    /**
     * Loads extension services into temporary container.
     *
     * @param ContainerBuilder $container
     * @param array $config
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $this->loadClient($container, $config);
        $this->loadContextInitializer($container, $config);
    }


    private function loadClient(ContainerBuilder $container, $config)
    {
        $clientDifinition = new Definition('Solution\JsonRpcApiExtension\Client\JsonRpcClient');
        $clientDifinition->setFactory('Solution\JsonRpcApiExtension\Client\JsonRpcClient::factory');

        $clientDifinition->setArguments($config,new Reference(Symfony2Extension::KERNEL_ID));

        $container->setDefinition(self::CLIENT_ID, $clientDifinition);
    }

    private function loadContextInitializer(ContainerBuilder $container, $config)
    {
        $definition = new Definition('Solution\JsonRpcApiExtension\Context\Initializer\ClientAwareInitializer', array(
            new Reference(self::CLIENT_ID),
            $config
        ));
        $definition->addTag(ContextExtension::INITIALIZER_TAG);
        $container->setDefinition('web_api.context_initializer', $definition);
    }
}