<?php

namespace LR\QuickQuote\Block;

class Form extends \Magento\Framework\View\Element\Template implements \Magento\Widget\Block\BlockInterface
{
	
	protected $_template = 'LR_QuickQuote::form.phtml';

	protected $_localeFormat;

	protected $dataHelper;

	public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        \LR\QuickQuote\Helper\Data $dataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
		$this->_localeFormat = $localeFormat;
		$this->dataHelper    = $dataHelper;
    }

    public function toHtml()
    {
    	if (!$this->dataHelper->isEnabled() || !$this->dataHelper->isGroupValid()) return;

    	return parent::toHtml();
    }

    /**
     * @return array
     */
	public function getPriceFormat()
	{
		return $this->_localeFormat->getPriceFormat();
	}
}