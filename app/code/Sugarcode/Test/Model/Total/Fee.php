<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sugarcode\Test\Model\Total;
//$_product->getAttributes()['harbor']->getFrontend()->getValue($_product)
//$membershipProduct->getAttributes()['member_discounted_seats']->getFrontEnd()->getValue($membershipProduct)
//                 // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                // $loadedProduct = $objectManager->get('Magento\Catalog\Model\Product')->load($id);

class Fee extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
   /**
     * Collect grand total address amount
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     * @return $this
     */
    protected $quoteValidator = null; 
    protected $session;
    protected $productRepository;

    public function __construct(\Magento\Quote\Model\QuoteValidator $quoteValidator, \Magento\Customer\Model\Session $session, \Magento\Catalog\Model\ProductRepository $productRepository)
    {
        $this->quoteValidator = $quoteValidator;
        $this->session = $session;
        $this->productRepository = $productRepository;
    }
    
    public function collect(
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment,
        \Magento\Quote\Model\Quote\Address\Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);


        $exist_amount = 0; //$quote->getFee(); 
        $fee = $this->getDiscountAmount($quote, $total); //-100; //Excellence_Fee_Model_Fee::getFee();
        $balance = $fee - $exist_amount;

        $total->setTotalAmount('fee', $balance);
        $total->setBaseTotalAmount('fee', $balance);

        $total->setFee($balance);
        $total->setBaseFee($balance);

        $total->setGrandTotal($total->getGrandTotal() + $balance);
        $total->setBaseGrandTotal($total->getBaseGrandTotal() + $balance);


        return $this;
    } 

    protected function clearValues(Address\Total $total)
    {
        $total->setTotalAmount('subtotal', 0);
        $total->setBaseTotalAmount('subtotal', 0);
        $total->setTotalAmount('tax', 0);
        $total->setBaseTotalAmount('tax', 0);
        $total->setTotalAmount('discount_tax_compensation', 0);
        $total->setBaseTotalAmount('discount_tax_compensation', 0);
        $total->setTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setBaseTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setSubtotalInclTax(0);
        $total->setBaseSubtotalInclTax(0);
    }
    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param Address\Total $total
     * @return array|null
     */
    /**
     * Assign subtotal amount and label to address object
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param Address\Total $total
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(\Magento\Quote\Model\Quote $quote, \Magento\Quote\Model\Quote\Address\Total $total)
    {
        $discount = $this->getDiscountAmount($quote, $total);
        return [
            'code' => 'fee',
            'title' => 'Member Discount',
            'value' => $discount
        ];
    }
    
    public function getDiscountAmount($quote, $total){
        $info = $this->getEligibleInfo($quote);
        if(!$info['eligible']){
            return 0;
        }
        else{
            $eligibleTotal = $this->getEligibleTotal($info['discounted_seats'], $quote);
            $discount = ($info['rate'] - 0.1) * ( $eligibleTotal/ 0.9) * -1;
        }
        return $discount;
    }
    
    public function getEligibleInfo($quote){
        $data = ['eligible'=>false, 'rate'=>0, 'discounted_seats'=>0];
        if($this->session->isLoggedIn()){
            $sku_keys = ['4'=>'M-PWF-Ohana']; // lookup to find skus based on customer groupId
            $customer = $this->session->getCustomer();
            $groupId = $customer->getData('group_id');
            $sku = $sku_keys[$groupId];
            $loadedMembershipProduct = $this->productRepository->get($sku);
            $data['eligible'] = true;
            $data['discounted_seats'] = $loadedMembershipProduct->getData('member_discounted_seats');
            $data['rate'] = $loadedMembershipProduct->getData('member_discount_rate');
        }
        if(!$data['eligible']){ // see if there's a membership in the cart
            $membershipProduct = $this->getMembershipFromCart($quote);  // $membership is a product object
            if($membershipProduct){
                $sku = $membershipProduct->getSku();
                $loadedProduct = $this->productRepository->get($sku);
                $data['eligible'] = true;
                $data['discounted_seats'] = $loadedProduct->getData('member_discounted_seats');
                $data['rate'] = $loadedProduct->getData('member_discount_rate');
            }
        }
        return $data;
    } 

    public function getMembershipFromCart($quote){
        // loop through items in cart, if we find one that's a membership, we return it, otherwise return false
        $items = $quote->getAllVisibleItems();
        foreach($items as $item){
            $_product = $item->getProduct();
            $attributeSetId = $_product->getAttributeSetId();
            if($attributeSetId == '12'){ // its a membership
                return $_product;
            }
        }
        return false;
    }

    public function getEligibleTotal($discountedSeats, $quote){
        $totalKeepers = [];
        $eligibleTotal = 0;
        if($discountedSeats > 0){
            $allTix = $this->getcruisetickets($quote);
            foreach($allTix as $skutix){ // tickets for a specific sku-date-time
                $numTixToGet = (count($skutix) < $discountedSeats) ? count($skutix) : $discountedSeats;
                // if there are more elibile than exist set to num exist
                $skuKeepers = array_slice($skutix, 0, $numTixToGet);
                $totalKeepers = array_merge($totalKeepers, $skuKeepers); // add them to the master list of tickets that will get discounts
            }
            foreach($totalKeepers as $keeperPrice){
                $eligibleTotal += $keeperPrice; // add the total price for all the tickets getting discounts - the eligible total
            }
        }
        return $eligibleTotal;
    }

    public function getCruiseTickets($quote){
        $cruises = [];
        $items = $quote->getAllVisibleItems();
        foreach($items as $item){
            $_product = $item->getProduct();
            $attributeSetId = $_product->getAttributeSetId();
            if($attributeSetId == '9'){ // its a cruiseTicket
                $dateAndTime = $this->getCruiseDateAndTime($_product);
                $sku = $_product->getSku();
                $stub_sku = explode('-', $sku)[0];
                $fullSku = $stub_sku .'|'. $dateAndTime['date'] .'|'. $dateAndTime['time'];
                // build full identifier sku stub + | + date + | + time
                if(!isset($cruises[$fullSku])){
                    $cruises[$fullSku] = [];
                }
                $price = $_product->getPrice();
                $qty = $item->getQty();
                for($i = 0; $i < $qty; $i++){
                    $cruises[$fullSku][] = $price; // put the price of the product in the full sku array
                    rsort($cruises[$fullSku]); // bring the most expensive ones to the top
                }
            }
        }
        return $cruises;
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


    /**
     * Get Subtotal label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Member Discount');
    }
}