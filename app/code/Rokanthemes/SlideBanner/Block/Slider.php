<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Rokanthemes\SlideBanner\Block;

/**
 * Cms block content block
 */
class Slider extends \Magento\Framework\View\Element\Template 
{
    
	protected $_sliderFactory;
	protected $_bannerFactory;

	protected $_scopeConfig;

	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	protected $_storeManager;

    /**
     * @param Context $context
     * @param array $data
     */
	
   public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Rokanthemes\SlideBanner\Model\SliderFactory $sliderFactory,
		\Rokanthemes\SlideBanner\Model\SlideFactory $slideFactory,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		array $data = []
	) {
		parent::__construct($context, $data);
		$this->_sliderFactory = $sliderFactory;
		$this->_bannerFactory = $slideFactory;
		$this->_scopeConfig = $context->getScopeConfig();
		$this->_storeManager = $storeManager;
	}

    /**
     * Prepare Content HTML
     *
     * @return string
     */
    protected function _beforeToHtml()
    {
        $sliderId = $this->getSliderId();
        if ($sliderId && !$this->getTemplate()) {
			$this->setTemplate("Rokanthemes_SlideBanner::slider.phtml");
        }
        return parent::_beforeToHtml();
    }

    /**
     * Return identifiers for produced content
     *
     * @return array
     */
	public function getImageElement($src)
	{
		$mediaUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
		return '<img src="'. $mediaUrl . $src . '" />';
	}
	public function getBannerCollection()
	{
		$sliderId = $this->getSlider()->getId();
		if(!$sliderId)
			return [];
		$collection = $this->_bannerFactory->create()->getCollection();
		$collection->addFieldToFilter('slider_id', $sliderId);
		return $collection;
	}
	public function getSlider()
	{
		$sliderId = $this->getSliderId();
		$slider = $this->_sliderFactory->create();
		$slider->load($sliderId);
		return $slider;
	}
}
