<?php 

namespace PWF\CruiseCart\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Helper\Context;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    
    public function __construct(
        \Magento\Framework\App\Helper\Context $context, 
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Checkout\Model\Cart $cart
    )
    {
        $this->productRepository = $productRepository;
        $this->checkoutHelper = $checkoutHelper;
        $this->cart = $cart;
        parent::__construct($context);
    }
    
    public function isCruiseTicket($item)
    {
        $isCruiseTicket = $item->getProduct()->getAttributeSetId() == '9';
        return($isCruiseTicket);
    }
    
    public function isCruiseMeal($item)
    {
        $isCruiseMeal = $item->getProduct()->getAttributeSetId() == '13';
        return($isCruiseMeal);
    }
    
    public function getParentSku($item){
        $parent_sku = $item->getOptions()[4]->getValue();
        return $parent_sku;
    }
    
    public function getParentCruise($sku){
        return $this->productRepository->get($sku);
    }
    
    public function getFullCruiseSku($item){
        $parentSku = $this->getParentSku($item);
        $product = $item->getProduct();
        $date_time = $this->getCruiseDateAndTime($product);
        $date = str_replace('/', '_', $date_time['date']);
        $time = str_replace(':', '_', $date_time['time']);
        $fullSku = $parentSku . '_' . $date . '_' . $time;
        return($fullSku);
    }
    
    public function buildCruiseObject($cruiseProduct, $item){
        $date_time = $this->getCruiseDateAndTime($item->getProduct());
        $obj = [];
        $obj['name'] = $cruiseProduct->getName();
        $obj['date'] = $date_time['date'];
        $obj['time'] = $date_time['time'];
        $obj['sku'] = $cruiseProduct->getSku();
        $obj['cart_id'] = $item->getId();
        return $obj;
    }
    
    public function buildTicketObject($item){
        $product = $item->getProduct();
        $obj = [];
        $obj['name'] = $this->productRepository->get($item->getProduct()->getSku())->getData('ticket_label');
        $obj['qty'] = $item->getQty();
        $obj['price'] = $this->checkoutHelper->formatPrice($item->getCalculationPrice());
        $obj['subtotal'] = $this->checkoutHelper->formatPrice($item->getRowTotal());
        return $obj;
    }
    
    public function buildMealObject($item){
        $product = $item->getProduct();
        $loadedProduct = $this->productRepository->get($product->getSku());
        $obj = []; //dinner_cruise_meal_type
        $obj['qty'] = $item->getQty();
        $id = $loadedProduct->getData('dinner_cruise_meal_type');
        $obj['name'] = $product->getResource()->getAttribute('dinner_cruise_meal_type')->getSource()->getOptionText($id);
        return $obj;
    }
    
    public function deleteItemsByCruiseSku($sku){
        $items = $this->cart->getItems()->getItems();
        foreach($items as $item){
            if(($this->isCruiseTicket($item) || $this->isCruiseMeal($item)) && $this->getFullCruiseSku($item) == $sku){
                $id = $item->getId();
                try {
                    $this->cart->removeItem($id)->save();
                } catch (\Exception $e) {
                    $this->messageManager->addError(__('We can\'t remove the cruise.'));
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }
            }
        }
    }
    
    public function getNonCruiseItems($items){
        $nonCruiseItems = [];
        foreach($items as $item){
            if(!$this->isCruiseTicket($item) && !$this->isCruiseMeal($item)){
                $nonCruiseItems[] = $item;
            }
        }
        return $nonCruiseItems;
    }
    
    public function getCruiseTickets($items){
        $cruiseTickets = [];
        foreach($items as $item){
            if($this->isCruiseTicket($item) || $this->isCruiseMeal($item)){
               $cruiseSku = $this->getFullCruiseSku($item);
               if(!isset($cruiseTickets[$cruiseSku])){
                    $parentSku = $this->getParentSku($item);
                    $parentCruise = $this->getParentCruise($parentSku);
                    $cruiseObj = $this->buildCruiseObject($parentCruise, $item);
                    $cruiseTickets[$cruiseSku] = ['cruise_product'=>$cruiseObj, 'cruise_tickets'=>[], 'cruise_meals'=>[]];
               }
               if($this->isCruiseTicket($item)){
                   $cruiseTickets[$cruiseSku]['cruise_tickets'][] = $this->buildTicketObject($item);
               }
               if($this->isCruiseMeal($item)){
                    $cruiseTickets[$cruiseSku]['cruise_meals'][] = $this->buildMealObject($item);
               }
            }
        }
        return $cruiseTickets;
    }
    
    public function getCruiseDateAndTime($_product){
        // return date and time from custom options of product
        $dateAndTime = ['date' => '', 'time' => ''];
        $customOptions = $_product->getCustomOptions();
        $options = $_product->getOptions();
        // make an key value array of the titles and ids for the custom options
        $optionIds = [];
        foreach($options as $option){
            $title = $option->getTitle();
            $id = $option->getId();
            $optionIds[$title] = $id;
        }
        // get values in custom options, using id provided in optionIds, set key of custom option to 'option_' + value
        $time = $customOptions['option_' . $optionIds['Cruise Time']]->getValue();
        $date = $customOptions['option_' . $optionIds['Cruise Date']]->getValue();
        // set the values and return them
        $dateAndTime['date'] = $date;
        $dateAndTime['time'] = $time;
        return $dateAndTime;
    }
}