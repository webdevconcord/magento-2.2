<?php

namespace Concordpay\Payment\Controller\Url;

use Magento\Authorizenet\Model\DirectPost;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class ConcordpayService
 *
 * @package Concordpay\Payment\Controller\Url
 */
class ConcordpayService extends Action //implements CsrfAwareActionInterface
{
    /** @var PageFactory */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Load the page defined
     */
    public function execute()
    {
        //load model
        /* @var $paymentMethod DirectPost */
        $paymentMethod = $this->_objectManager->create('Concordpay\Payment\Model\Concordpay');

        //get request data
        date_default_timezone_set("Europe/Kiev");
        $callback = json_decode(file_get_contents("php://input"), true);
        $logMsg = "";
        $logMsg .= "\r\n==================================================================";
        $logMsg .= "\r\n" . date('d M Y H:i:s', time());
        $logMsg .= "\r\nCall Back Service execute: " . json_encode($callback);
        //file_put_contents('/var/log/concordpayCallback.log', $logMsg, FILE_APPEND);
        $data = array();
        foreach ($callback as $key => $val) {
            $data[$key] = $val;
        }

        $paymentMethod->processResponse($data);
    }
}
