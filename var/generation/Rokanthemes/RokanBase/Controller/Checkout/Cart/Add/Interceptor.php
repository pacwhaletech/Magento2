<?php
namespace Rokanthemes\RokanBase\Controller\Checkout\Cart\Add;

/**
 * Interceptor class for @see \Rokanthemes\RokanBase\Controller\Checkout\Cart\Add
 */
class Interceptor extends \Rokanthemes\RokanBase\Controller\Checkout\Cart\Add implements \Magento\Framework\Interception\InterceptorInterface
{
    use \Magento\Framework\Interception\Interceptor;

    public function __construct(\Magento\Framework\App\Action\Context $context, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, \Magento\Checkout\Model\Session $checkoutSession, \Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator, \Magento\Checkout\Model\Cart $cart, \Magento\Catalog\Api\ProductRepositoryInterface $productRepository)
    {
        $this->___init();
        parent::__construct($context, $scopeConfig, $checkoutSession, $storeManager, $formKeyValidator, $cart, $productRepository);
    }

    /**
     * {@inheritdoc}
     */
    public function processCruiseItems($ids, $quantities, $params)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'processCruiseItems');
        if (!$pluginInfo) {
            return parent::processCruiseItems($ids, $quantities, $params);
        } else {
            return $this->___callPlugins('processCruiseItems', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addCruiseItem($qty, $productId, $params)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'addCruiseItem');
        if (!$pluginInfo) {
            return parent::addCruiseItem($qty, $productId, $params);
        } else {
            return $this->___callPlugins('addCruiseItem', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCruiseItemOptions($loadedProduct, $postParams)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getCruiseItemOptions');
        if (!$pluginInfo) {
            return parent::getCruiseItemOptions($loadedProduct, $postParams);
        } else {
            return $this->___callPlugins('getCruiseItemOptions', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionIdsByTitle($options)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getOptionIdsByTitle');
        if (!$pluginInfo) {
            return parent::getOptionIdsByTitle($options);
        } else {
            return $this->___callPlugins('getOptionIdsByTitle', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'execute');
        if (!$pluginInfo) {
            return parent::execute();
        } else {
            return $this->___callPlugins('execute', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'dispatch');
        if (!$pluginInfo) {
            return parent::dispatch($request);
        } else {
            return $this->___callPlugins('dispatch', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getActionFlag()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getActionFlag');
        if (!$pluginInfo) {
            return parent::getActionFlag();
        } else {
            return $this->___callPlugins('getActionFlag', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequest()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getRequest');
        if (!$pluginInfo) {
            return parent::getRequest();
        } else {
            return $this->___callPlugins('getRequest', func_get_args(), $pluginInfo);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse()
    {
        $pluginInfo = $this->pluginList->getNext($this->subjectType, 'getResponse');
        if (!$pluginInfo) {
            return parent::getResponse();
        } else {
            return $this->___callPlugins('getResponse', func_get_args(), $pluginInfo);
        }
    }
}
