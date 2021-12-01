<?php

namespace LR\QuickQuote\Block\Adminhtml\Renderer\Config;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Version extends \Magento\Config\Block\System\Config\Form\Field
{
	protected $moduleResource;

	
	public function __construct(
		\Magento\Framework\Module\ModuleResource $moduleResource,
		\Magento\Backend\Block\Template\Context $context,
		array $data = []
	) {
		parent::__construct($context, $data);
		$this->moduleResource = $moduleResource;
	}

	/**
	 * @param  AbstractElement $element
	 * @return string
	 */
	protected function _getElementHtml(AbstractElement $element)
	{
		return $this->moduleResource->getDataVersion('LR_QuickQuote');
	}

}