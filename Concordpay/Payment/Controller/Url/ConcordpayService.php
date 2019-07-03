<?php

namespace Concordpay\Payment\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class ConcordpayService extends Action //implements CsrfAwareActionInterface
{
    /** @var \Magento\Framework\View\Result\PageFactory */
    protected $resultPageFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }


    /**
     * Load the page defined
     *
     */
    public function execute()
    {
        //load model
        /* @var $paymentMethod \Magento\Authorizenet\Model\DirectPost */
        $paymentMethod = $this->_objectManager->create('Concordpay\Payment\Model\Concordpay');

        //get request data
        date_default_timezone_set("Europe/Kiev");
        $callback = json_decode(file_get_contents("php://input"),true);
        $logMsg = "";
        $logMsg .= "\r\n==================================================================";
        $logMsg .= "\r\n" . date('d M Y H:i:s', time());
        $logMsg .= "\r\nCall Back Service execute: " . json_encode($callback);
        file_put_contents('/var/www/magento-2-2/var/log/concordpayCallback.log', $logMsg, FILE_APPEND);
        $data = array();
        foreach ($callback as $key => $val) {
            $data[$key] = $val;
        }

        $paymentMethod->processResponse($data);
    }

//    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
//    {
//        return null;
//    }
//
//    public function validateForCsrf(RequestInterface $request): ?bool
//    {
//        return true;
//    }
}
