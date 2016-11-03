<?php
/**
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PWF\CruiseCart\Controller\Checkout;


class Delete extends \Magento\Checkout\Controller\Cart
{
    /**
     * Delete shopping cart item action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            // return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $sku = $this->getRequest()->getParam('sku');
        $cart_helper = $this->_objectManager->get('PWF\\CruiseCart\\Helper\\Data');
        if ($sku) {
            try {
                //$this->cart->removeItem($id)->save();
                $cart_helper->deleteItemsByCruiseSku($sku);
            } catch (\Exception $e) {
                $this->messageManager->addError(__('We can\'t remove the cruise item.'));
                $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
            }
        }
        $defaultUrl = $this->_objectManager->create('Magento\Framework\UrlInterface')->getUrl('*/*');
        return $this->resultRedirectFactory->create()->setUrl($this->_redirect->getRedirectUrl($defaultUrl));
    }
}
