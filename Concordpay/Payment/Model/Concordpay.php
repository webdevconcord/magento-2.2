<?php

namespace Concordpay\Payment\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Class Concordpay
 * @package Concordpay\Payment\Model
 */
class Concordpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_CODE           = 'concordpay';
    const SIGNATURE_SEPARATOR   = ';';
    const ORDER_SEPARATOR       = '#';
    const TRANSACTION_APPROVED  = 'Approved';
    const TRANSACTION_DECLINED  = 'Declined';
    const RESPONSE_TYPE_PAYMENT = 'payment';
    const RESPONSE_TYPE_REVERSE = 'reverse';

    /**
     * @var string
     */
    protected $_gateUrl = "https://pay.concord.ua/api";

    /** @var array */
    protected $keysForResponseSignature = array(
        'merchantAccount',
        'orderReference',
        'amount',
        'currency'
    );

    /** @var array */
    protected $keysForSignature = array(
        'merchant_id',
        'order_id',
        'amount',
        'currency_iso',
        'description'
    );

    /** @var array */
    protected $operationTypes = array(
        'payment',
        'reverse'
    );

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Transaction\BuilderInterface
     */
    protected $_transactionBuilder;

    /**
     * @var \Zend\Log\Logger
     */
    protected $_logger;

    /** @var */
    protected $_order;

    /** @var */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $_store;

    /**
     * Concordpay constructor.
     * @param \Magento\Framework\Model\Context                              $context
     * @param \Magento\Framework\Registry                                   $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory             $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory                  $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                                  $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface            $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                          $logger
     * @param \Magento\Framework\Module\ModuleListInterface                 $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface          $localeDate
     * @param \Magento\Framework\Encryption\EncryptorInterface              $encryptor
     * @param \Magento\Framework\UrlInterface                               $urlBuilder
     * @param Transaction\BuilderInterface                                  $builderInterface
     * @param \Magento\Sales\Model\OrderFactory                             $orderFactory
     * @param \Magento\Store\Model\StoreManagerInterface                    $storeManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null  $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null            $resourceCollection
     * @param \Magento\Framework\Locale\Resolver                            $store
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context                                $context,
        \Magento\Framework\Registry                                     $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory               $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory                    $customAttributeFactory,
        \Magento\Payment\Helper\Data                                    $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface              $scopeConfig,
        \Magento\Payment\Model\Method\Logger                            $logger,
        \Magento\Framework\Module\ModuleListInterface                   $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface            $localeDate,
        \Magento\Framework\Encryption\EncryptorInterface                $encryptor,
        \Magento\Framework\UrlInterface                                 $urlBuilder,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $builderInterface,
        \Magento\Sales\Model\OrderFactory                               $orderFactory,
        \Magento\Store\Model\StoreManagerInterface                      $storeManager,
        \Magento\Framework\Locale\Resolver                              $store,
        \Magento\Framework\Model\ResourceModel\AbstractResource         $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb                   $resourceCollection = null,
        array                                                           $data = []
    ) {
        $this->orderFactory        = $orderFactory;
        $this->urlBuilder          = $urlBuilder;
        $this->_transactionBuilder = $builderInterface;
        $this->_encryptor          = $encryptor;
        $this->_storeManager       = $storeManager;
        $this->_store              = $store;
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $writer        = new \Zend\Log\Writer\Stream(BP . '/var/log/concordpay.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
    }

    /**
     * @param $orderId
     * @return int|null
     */
    public function getCustomerId($orderId)
    {
        return $this->getOrder($orderId)->getCustomerId();
    }

    /**
     * @param $orderId
     * @return Order
     */
    public function getOrder($orderId)
    {
        return $this->orderFactory->create()->loadByIncrementId($orderId);
    }

    /**
     * @param $orderId
     * @return null|string
     */
    public function getCurrencyCode($orderId)
    {
        return $this->getOrder($orderId)->getBaseCurrencyCode();
    }

    /**
     * @param string $paymentAction
     * @param \Magento\Framework\DataObject $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }
        return parent::isAvailable($quote) && $this->isCarrierAllowed(
            $quote->getShippingAddress()->getShippingMethod()
        );
    }

    /**
     * @param string $shippingMethod
     * @return bool
     */
    protected function isCarrierAllowed($shippingMethod)
    {
        return strpos($this->_scopeConfig->getValue('payment/concordpay/allowed_carrier'), $shippingMethod) !== false;
    }

    /**
     * @return string
     */
    public function getGateUrl()
    {
        return $this->_gateUrl;
    }

    /**
     * @return mixed
     */
    public function getDataIntegrityCode()
    {
        return $this->_encryptor->decrypt($this->_scopeConfig->getValue('payment/concordpay/secret_key'));
    }

    /**
     * @param $orderId
     * @return array
     */
    public function getPostData($orderId)
    {
        $order  = $this->getOrder($orderId);
        $amount = $this->getAmount($orderId);

        $client_first_name = $order->getCustomerFirstname() ?? '';
        $client_last_name  = $order->getCustomerLastname() ?? '';

        $phone= $order->getBillingAddress()->getTelephone() ?? '';

        $description = __('Payment by card on the site') . ' ' . $this->_storeManager->getStore()->getBaseUrl() .
            ", $client_first_name $client_last_name, $phone";

        $fields = array(
            'operation'    => 'Purchase',
            'merchant_id'  => $this->_scopeConfig->getValue('payment/concordpay/merchant'),
            'order_id'     => $orderId,
            'amount'       => $amount,
            'currency_iso' => $order->getOrderCurrencyCode(),
            'description'  => $description,
            'add_params'   => [],
            'approve_url'  => $this->urlBuilder->getUrl('concordpay/url/concordpaysuccess'),
            'decline_url'  => $this->urlBuilder->getUrl('checkout/cart', ['_secure' => true]),
            'cancel_url'   => $this->urlBuilder->getUrl('checkout/cart', ['_secure' => true]),
            'callback_url' => $this->urlBuilder->getUrl('concordpay/url/concordpayservice'),
            // Statistics.
            'client_first_name' => $client_first_name,
            'client_last_name'  => $client_last_name,
            'email'             => $order->getCustomerEmail() ?? '',
            'phone'             => $phone,
        );

        $fields['signature'] = $this->getRequestSignature($fields);

        return $fields;
    }

    /**
     *
     * @param $orderId
     * @return float
     */
    public function getAmount($orderId)
    {
        return $this->getOrder($orderId)->getGrandTotal();
    }

    /**
     * @param $options
     * @return string
     */
    public function getRequestSignature($options)
    {
        return $this->getSignature($options, $this->keysForSignature);
    }

    /**
     * @param $data
     * @return string
     */
    public function getFormFields($data)
    {
        $html = '';
        foreach ($data as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $avalue) {
                    $html .= '<input type="hidden" name="' . $name . '[]" value="' . htmlspecialchars($avalue) . '">';
                }
            } else {
                $html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '">';
            }
        }

        return $html;
    }

    /**
     * @param $responseData
     * @return bool
     */
    public function processResponse($responseData)
    {
        if (empty($responseData)) {
            $callback = json_decode(file_get_contents("php://input"), true);
            $responseData = array();
            foreach ($callback as $key => $val) {
                $responseData[$key] = $val;
            }
        }
        $debugData = ['response' => $responseData];
        $this->_logger->debug("processResponse", $debugData);

        list($orderId, ) = explode(self::ORDER_SEPARATOR, $responseData['orderReference']);
        $order = $this->getOrder($orderId);

        if ($order && ($this->_processOrder($order, $responseData) === true)) {
            return true;
        }

        return false;
    }

    /**
     *
     * @param Order $order
     * @param mixed $response
     * @return bool
     */
    protected function _processOrder(Order $order, $response)
    {
        $this->_logger->debug(
            "_processConcordpay",
            [
                "\$order" => $order,
                "\$response" => $response
            ]
        );
        try {
            if ($this->getResponseSignature($response) !== $response['merchantSignature']) {
                $this->_logger->debug("_processOrder: wrong merchantSignature");
                return false;
            }

            if ((float)$order->getGrandTotal() !== (float)$response["amount"]) {
                $this->_logger->debug("_processOrder: amount mismatch, order FAILED");
                return false;
            }

            if (!$response['type'] || !in_array($response['type'], $this->operationTypes, true)) {
                $this->_logger->debug("_processOrder: unknown operation type, order FAILED");
                return false;
            }

            if ($response["transactionStatus"] === self::TRANSACTION_APPROVED) {
                $this->createTransaction($order, $response);
                if ($response['type'] === self::RESPONSE_TYPE_PAYMENT) {
                    // Ordinary payment.

                    $order->setState($this->_scopeConfig->getValue('payment/concordpay/after_pay_status'))
                        ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PAYMENT_REVIEW))
                        ->save();
                } elseif ($response['type'] === self::RESPONSE_TYPE_REVERSE) {
                    // Refunded payment.
                    $order->setState($this->_scopeConfig->getValue('payment/concordpay/after_refund_status'))
                        ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED))
                        ->save();
                }
                $this->_logger->debug("_processOrder: order state changed: STATE_PROCESSING");
                $this->_logger->debug("_processOrder: order data saved, order OK");
            } else {
                $order->setState(Order::STATE_CANCELED)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED))
                    ->save();

                $this->_logger->debug("_processOrder: order state not STATE_CANCELED");
                $this->_logger->debug("_processOrder: order data saved, order not approved");
            }
            return true;
        } catch (\Exception $e) {
            $this->_logger->debug("_processOrder exception", $e->getTrace());
            return false;
        }
    }

    /**
     * @param $options
     * @return string
     */
    public function getResponseSignature($options)
    {
        return $this->getSignature($options, $this->keysForResponseSignature);
    }

    /**
     * @param $option
     * @param $keys
     * @return string
     */
    public function getSignature($option, $keys)
    {
        $hash = [];
        foreach ($keys as $dataKey) {
            if (!isset($option[$dataKey])) {
                continue;
            }
            if (is_array($option[$dataKey])) {
                foreach ($option[$dataKey] as $v) {
                    $hash[] = $v;
                }
            } else {
                $hash [] = $option[$dataKey];
            }
        }
        $hash = implode(self::SIGNATURE_SEPARATOR, $hash);

        $secret = $this->_scopeConfig->getValue('payment/concordpay/secret_key');

        $callbacklogMsg = "";
        $callbacklogMsg .= "\r\n==================================================================";
        $callbacklogMsg .= "\r\n" . date('d M Y H:i:s', time());
        $callbacklogMsg .= "\r\nhash: " . json_encode($hash);
        $callbacklogMsg .= "\r\nsecret: " . $secret;
        $callbacklogMsg .= "\r\nSignature: " . hash_hmac('md5', $hash, $secret);
        //file_put_contents('/var/www/magento-two/var/log/concordpayCallback.log', $callbacklogMsg, FILE_APPEND);

        return hash_hmac('md5', $hash, $secret);
    }

    public function createTransaction($order = null, $paymentData = [])
    {
        try {
            // Get payment object from order object.
            $payment = $order->getPayment();

            $payment->setLastTransId($paymentData['orderReference']);
            $payment->setTransactionId($paymentData['orderReference']);
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
            );
            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $message = __('The authorized amount is %1.', $formatedPrice);
            //get the object of builder class
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentData['orderReference'])
                ->setAdditionalInformation(
                    [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
                )
                ->setFailSafe(true)
                ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            $payment->save();
            $order->save();

            return $transaction->save()->getTransactionId();
        } catch (\Exception $e) {
            $this->_logger->debug("_processOrder exception", $e->getTrace());
        }
    }

    /**
     * @param $response
     * @return bool|string
     */
    public function isPaymentValid($response)
    {
        $merchant = $this->_scopeConfig->getValue('payment/concordpay/merchant');
        if ($merchant !== $response['merchantAccount']) {
            return __('An error has occurred during payment. Merchant data is incorrect.');
        }
        if ($response['transactionStatus'] === self::TRANSACTION_DECLINED) {
            return __('An error has occurred during payment. Order is declined.');
        }

        $responseSignature = $response['merchantSignature'];
        if ($this->getSignature($response, $this->keysForResponseSignature) !== $responseSignature) {
            return __('An error has occurred during payment. Signature is not valid.');
        }

        return true;
    }
}
