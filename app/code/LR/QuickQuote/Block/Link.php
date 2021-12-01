<?php

namespace LR\QuickQuote\Block;

class Link extends \Magento\Framework\View\Element\Html\Link
{
    protected $dataHelper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \LR\QuickQuote\Helper\Data $dataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dataHelper = $dataHelper;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
	protected function _toHtml()
	{
        if (!$this->dataHelper->getConfig('general/show_toplinks')) {
            return;
        }
        $route = $this->dataHelper->getConfig('general/route');
        $title = $this->dataHelper->getTitle();
        if ($route && $title && $this->dataHelper->isGroupValid()) {
            return '<li><a href="' . $this->getUrl($route) . '" >' . $title . '</a></li>';
        }
	}
}