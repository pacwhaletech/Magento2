<?php
namespace Rokanthemes\PWFLogin\Block;

class Login extends \Magento\Framework\View\Element\Template
{
    protected $customerSession;
    
/**
     * Construct
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->customerSession = $customerSession;
    }
    
    public function isLoggedIn(){
        $loggedIn = $this->customerSession->isLoggedIn();
        return $loggedIn;
    }
    
    public function getFirstName(){
        if(!$this->isLoggedIn()){
            return false;
        }
        return $this->customerSession->getCustomer()->getFirstname();
    }
}

