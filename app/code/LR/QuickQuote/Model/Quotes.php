<?php

namespace LR\QuickQuote\Model;

use Magento\Cron\Exception;
use Magento\Framework\Model\AbstractModel;

class Quotes extends AbstractModel
{
    protected $_dateTime;

    protected function _construct()
    {
        $this->_init(\LR\QuickQuote\Model\ResourceModel\Quotes::class);
    }
}
