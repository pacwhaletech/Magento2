<?php
/**
 *
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Rokanthemes\RokanBase\Controller\Checkout\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart as CustomerCart;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Add extends \Magento\Checkout\Controller\Cart\Add
{
   
    /**
     * Add product to shopping cart action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
	protected $_messege = '';
	protected $_result = [];
	
	
	protected function initTicketProduct($productId)
	{
        $storeId = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId();
        try {
            return $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return false;
        }
	}	
	
	public function processCruiseItems($ids, $quantities, $params){
	    foreach($ids as $index=>$productId) {
	        $qty = $quantities[$index];
	        if($qty > 0){
	           $this->addCruiseItem($qty, $productId, $params);
	        }
	    }
	}
	

    public function addCruiseItem($qty, $productId, $params){
    	$loaded_product = $this->initTicketProduct($productId);
    	if (!$loaded_product) {return $this->goBack();} 
    	$productParams = $params;  // copy param values from original request
    	$productParams['qty'] = $qty;
    	$productParams['product'] = $productId;
    	$productParams['options'] = $this->getCruiseItemOptions($loaded_product, $params);
    	// $this->product = $product;
        $this->cart->addProduct($loaded_product, $productParams);
	}

	public function getCruiseItemOptions($loadedProduct, $postParams){
		$cruiseItemOptions = [];
		$optionIds = $this->getOptionIdsByTitle($loadedProduct->getOptions());           // map of options
		$cruiseItemOptions[$optionIds["Cruise Date"]] = $postParams['cruise_date'];
		$cruiseItemOptions[$optionIds["Cruise Time"]] = $postParams['cruise_time'];
		$cruiseItemOptions[$optionIds["Cruise SKU"]] = $postParams['cruise_sku'];
		return $cruiseItemOptions;
	}

	public function getOptionIdsByTitle($options){
		$optionsOut = [];
  		foreach ($options as $o) {
			$optionsOut[$o->getTitle()] = $o->getId();
		}
		return $optionsOut;
	}

	
	
	
	
    public function execute()
    {
        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            // return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        
        $params = $this->getRequest()->getParams();
        try {
            // get params[cruise_sku] then create product then check attribute set id
            if(isset($params['is_cruise'])){ // if this is a cruise, process differently
                $ticketIds = $params['ticket_id'];
                $ticketQuantities = $params['ticket_qty'];
                $this->processCruiseItems($ticketIds, $ticketQuantities, $params);
                $mealIds = $params['meal_id'];
                $mealQuantities = $params['meal_qty'];
                $this->processCruiseItems($mealIds, $mealQuantities, $params);
                $product = $this->initTicketProduct($params['cruise_id']);
            }
            else{   // do normal single-item processing
                 if (isset($params['qty'])) {
                    $filter = new \Zend_Filter_LocalizedToNormalized(
                        ['locale' => $this->_objectManager->get('Magento\Framework\Locale\ResolverInterface')->getLocale()]
                     );
                    $params['qty'] = $filter->filter($params['qty']);
                 }

                $product = $this->_initProduct();
                $related = $this->getRequest()->getParam('related_product');
                
                /**
                 * Check product availability
                 */
                if (!$product) {
                   return $this->goBack();
                }               
                $this->cart->addProduct($product, $params);
            }

            $this->cart->save();

            /**
             * @todo remove wishlist observer \Magento\Wishlist\Observer\AddToCart
             */
            
            
            $this->_eventManager->dispatch(
                'checkout_cart_add_product_complete',
                ['product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse()]
            );

            if (!$this->_checkoutSession->getNoCartRedirect(true)) {
                if (!$this->cart->getQuote()->getHasError()) {
                    $message = __(
                        'You added %1 to your shopping cart.',
                        $product->getName()
                    );
					$this->_messege = $message;
                    $this->messageManager->addSuccessMessage($message);
                }
				$this->_result['html'] = $this->_getHtmlResponeAjaxCart($product);
                return $this->goBack(null, $product);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            if ($this->_checkoutSession->getUseNotice(true)) {
                $this->messageManager->addNotice(
                    $this->_objectManager->get('Magento\Framework\Escaper')->escapeHtml($e->getMessage())
                );
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach ($messages as $message) {
                    $this->messageManager->addError(
                        $this->_objectManager->get('Magento\Framework\Escaper')->escapeHtml($message)
                    );
                }
            }

            $url = $this->_checkoutSession->getRedirectUrl(true);

            if (!$url) {
                $cartUrl = $this->_objectManager->get('Magento\Checkout\Helper\Cart')->getCartUrl();
                $url = $this->_redirect->getRedirectUrl($cartUrl);
            }

            return $this->goBack($url);

        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('We can\'t add this item to your shopping cart right now.'));
            $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
            return $this->goBack();
        }
    }
    


    /**
     * Resolve response
     *
     * @param string $backUrl
     * @param \Magento\Catalog\Model\Product $product
     * @return $this|\Magento\Framework\Controller\Result\Redirect
     */
    protected function goBack($backUrl = null, $product = null)
    {
        if (!$this->getRequest()->isAjax()) {
            return parent::_goBack($backUrl);
        }
		
        $result = $this->_result;

        if ($backUrl || $backUrl = $this->getBackUrl()) {
            $result['backUrl'] = $backUrl;
        } else {
            if ($product && !$product->getIsSalable()) {
                $result['product'] = [
                    'statusText' => __('Out of stock')
                ];
            }
        }

        $this->getResponse()->representJson(
            $this->_objectManager->get('Magento\Framework\Json\Helper\Data')->jsonEncode($result)
        );
    }
	protected function _getHtmlResponeAjaxCart($product)
	{
		$message = __('You added <a href="'. $product->getProductUrl() .'">%1</a> to your shopping cart.',
                        $product->getName()
                    );
		$html = '<div class="popup_avaiable">'.$message.'<br>
					<div class="action_button">
						<ul>
							<li>
								<button title="'. __('Continue Shopping') . '" class="button btn-continue" onclick="jQuery.fancybox.close();">'. __('Continue Shopping') . '</button>
							</li>
							<li>
								<a title="Checkout" class="button btn-viewcart" href="'. $this->_url->getUrl('checkout/cart') .'"><span>'. __('View cart &amp; checkout'). '</span></a>
							</li>
						</ul>
					</div>
				</div>';
		return $html;
	}
}
