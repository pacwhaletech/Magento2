<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Sugarcode\Test\Model\Total;


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

    public function __construct(\Magento\Quote\Model\QuoteValidator $quoteValidator, \Magento\Customer\Model\Session $session)
    {
        $this->quoteValidator = $quoteValidator;
        $this->session = $session;
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
        $discountRate = 0;
        $discount = 0;
        $loggedIn = $this->session->isLoggedIn();
        if($loggedIn){
            $customer = $this->session->getCustomer();
            $groupId = $customer->getData('group_id');
            if(true){ //if($groupId == 1){
                $discountRate = .2;

            }
        }
        $discountRate = .2;
        $discount = $discountRate * ($this->getEligibleTotal($quote, $total) / 0.9) * -1;
        return $discount;
    }
    
    public function getEligibleDiscountedSeats($quote, $total){
        // if logged in and groupId
        $seats = 2;
        if($this->session->isLoggedIn()){
            $customer = $this->session->getCustomer();
            $groupId = $customer->getData('group_id');
            switch ($groupId) {
                case 4:
                    $seats = 5;
                    break;
                case 2:
                    $seats = 0;
                    break;
                default:
                    $seats = 0;
            }
        }
        return $seats;
    }
    
    public function getCruiseDateAndTime($_product){
        $dateAndTime = ['date' => '09-20-2016', 'time' => '09:00'];
        
        $customOptions = $_product->getCustomOptions();
        $options = $_product->getOptions();
        
        $optionIds = $customOptions['option_ids']->getValue();
        $ids = explode(',', $optionIds);
        
        $time = $customOptions['option_' . $ids[0]]->getValue();
        $date = $customOptions['option_' . $ids[1]]->getValue();
        $dateAndTime['date'] = $date;
        $dateAndTime['time'] = $time;
        return $dateAndTime;
    }
    
    public function getCruiseTickets($quote, $total){
        $cruises = [];
        $items = $quote->getAllVisibleItems();
        foreach($items as $item){
            $itemId = $item->getProductId();
            $_product = $item->getProduct();
            $attributeSetId = $_product->getAttributeSetId();
            
            
            if($attributeSetId != 'hello'){ // its a cruiseTicket
                 $dateAndTime = $this->getCruiseDateAndTime($_product);
                 $sku = $_product->getSku();
                 $sku = explode('-', $sku)[0];
                 $fullSku = $sku .'|'. $dateAndTime['date'] .'|'. $dateAndTime['time'];
                 if(!isset($cruises[$fullSku])){
                     $cruises[$fullSku] = [];
                 }
                 $price = $_product->getPrice();
                 $qty = $item->getQty();
                 for($i = 0; $i < $qty; $i++){
                     $cruises[$fullSku][] = $price;
                     rsort($cruises[$fullSku]);
                     $x = 9;
                 }
            }
            else{
                $x = 0;
            }
        }
        return $cruises;
    }   
    public function getEligibleTotal($quote, $total){
         $totalKeepers = [];
         $eligibleTotal = 0;
         $ticketsEligible = $this->getEligibleDiscountedSeats($quote, $total);
         if($ticketsEligible > 0){
            $allTix = $this->getcruisetickets($quote, $total);
            foreach($allTix as $skutix){
                $numTixToGet = (count($skutix) < $ticketsEligible) ? count($skutix) : $ticketsEligible; // if there are more elibile than exist set to num exist
                $skuKeepers = array_slice($skutix, 0, $numTixToGet);
                $totalKeepers = array_merge($totalKeepers, $skuKeepers);
            }
            foreach($totalKeepers as $keeperPrice){
                $eligibleTotal += $keeperPrice;
            }
         }
        return $eligibleTotal;
        //return 50;
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