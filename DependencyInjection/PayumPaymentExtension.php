<?php
namespace Payum\PaymentBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use Payum\PaymentBundle\DependencyInjection\Factory\Storage\StorageFactoryInterface;
use Payum\PaymentBundle\DependencyInjection\Factory\Payment\PaymentFactoryInterface;

class PayumPaymentExtension extends Extension
{
    protected $storageFactories = array();

    protected $paymentFactories = array();
    
    public function load(array $configs, ContainerBuilder $container)
    {
        $mainConfig = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($mainConfig, $configs);

        $container->setParameter('payum_payment.template.engine', $config['template']['engine']);

        // load services
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('payum.xml');
        
        $this->loadContexts($config['contexts'], $container);
    }
    
    protected function loadContexts(array $config, ContainerBuilder $container)
    {
        foreach ($config as $contextName => $context) {
            foreach ($context as $serviceName => $service) {
                if (isset($this->paymentFactories[$serviceName])) {
                    $paymentServiceId = $this->paymentFactories[$serviceName]->create($container, $contextName, $service);
                }
                if (isset($this->storageFactories[$serviceName])) {
                    $storageServiceId = $this->storageFactories[$serviceName]->create($container, $contextName, $service);
                }
            }
            
            $contextDefinition = new Definition();
            $contextDefinition->setClass('Payum\PaymentBundle\Context\LazyContext');
            $contextDefinition->setPublic(false);
            $contextDefinition->addMethodCall('setContainer', array(
                new Reference('service_container')
            ));
            $contextDefinition->setArguments(array(
                $contextName,
                $paymentServiceId,
                $storageServiceId,
                $context['status_request_class'],
                $context['interactive_controller'],
                $context['status_controller'],
            ));
            $contextId = 'payum_payment.context.'.$contextName;
            $container->setDefinition($contextId, $contextDefinition);
            
            $payumPaymentDefinition = $container->getDefinition('payum_payment');
            $payumPaymentDefinition->addMethodCall('addContext', array(
                new Reference($contextId)
            ));
        }
    }

    /**
     * @param Factory\Storage\StorageFactoryInterface $factory
     */
    public function addStorageFactory(StorageFactoryInterface $factory)
    {
        $this->storageFactories[$factory->getName()] = $factory;
    }

    /**
     * @param Factory\Payment\PaymentFactoryInterface $factory
     */
    public function addPaymentFactory(PaymentFactoryInterface $factory)
    {
        $this->paymentFactories[$factory->getName()] = $factory;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new MainConfiguration($this->paymentFactories, $this->storageFactories);
    }
}