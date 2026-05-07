<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   https://magebit.com/code-license
 */

declare(strict_types=1);

namespace Magebit\HyvaMontonioHirepurchase\Magewire\Checkout\Payment;

use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Hyva\Checkout\Model\Magewire\Payment\AbstractOrderData;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Model\Quote;
use Montonio\Hirepurchase\Command\Uuid\LoadByUuidCommand;
use Montonio\Hirepurchase\Command\Uuid\SaveCommand;
use Montonio\Hirepurchase\Gateway\Payments\ResponseHandler\RestoreLastCartSession;
use Montonio\Hirepurchase\Model\Payment\PaymentsCardConfigProvider;
use Montonio\Hirepurchase\Model\Payment\PaymentsConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;

class MontonioPlaceOrderService extends AbstractPlaceOrderService
{
    /**
     * @var string
     */
    private string $redirectUrl = self::REDIRECT_PATH;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RestoreLastCartSession $restoreLastCartSession
     * @param LoadByUuidCommand $loadByUuidCommand
     * @param SaveCommand $saveCommand
     * @param CartManagementInterface $cartManagement
     * @param AbstractOrderData|null $orderData
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RestoreLastCartSession $restoreLastCartSession,
        private readonly LoadByUuidCommand $loadByUuidCommand,
        private readonly SaveCommand $saveCommand,
        CartManagementInterface $cartManagement,
        ?AbstractOrderData $orderData = null
    ) {
        parent::__construct($cartManagement, $orderData);
    }

    /**
     * @param Quote $quote
     * @return int
     * @throws LocalizedException
     */
    public function placeOrder(Quote $quote): int
    {
        $orderId = parent::placeOrder($quote);
        $order = $this->orderRepository->get($orderId);
        $payment = $order->getPayment();
        $methodInstance = $payment->getMethodInstance();
        $methodCode = (string) $methodInstance->getCode();

        if (!method_exists($methodInstance, 'buildPaymentRequest') || !method_exists($methodInstance, 'createOrderRequest')) {
            throw new LocalizedException(__('The selected Montonio method is missing required gateway handlers.'));
        }

        $selectedProvider = '';
        if ($methodCode === PaymentsConfigProvider::CODE) {
            $additionalInformation = $payment->getAdditionalInformation();
            $selectedProvider = (string)($additionalInformation[PaymentsConfigProvider::ADDITIONAL_DATA_KEY] ?? '');
        }

        $payload = $methodInstance->buildPaymentRequest(['order' => $order, 'method' => $selectedProvider]);
        $request = $methodInstance->createOrderRequest(['payload' => $payload]);

        if (isset($request['statusCode']) && (string)$request['statusCode'] !== '200') {
            $this->restoreLastCartSession->execute();

            if (isset($request['message']) && is_array($request['message'])) {
                $message = implode(', ', $request['message']);
            } elseif (isset($request['message']) && !is_array($request['message'])) {
                $message = (string) $request['message'];
            } else {
                $message = (string) __('Failed redirect to payment gateway');
            }

            throw new LocalizedException(__($message));
        }

        $paymentUrl = $request['paymentUrl'] ?? null;
        if (!is_string($paymentUrl) || $paymentUrl === '') {
            throw new LocalizedException(__('Montonio did not return a valid payment redirect URL.'));
        }

        if (isset($request['uuid']) && is_string($request['uuid']) && $request['uuid'] !== '') {
            try {
                $this->loadByUuidCommand->execute($request['uuid']);
            } catch (NoSuchEntityException) {
                $this->saveCommand->execute((string)$order->getIncrementId(), $request['uuid']);
            }
        }

        $this->redirectUrl = $paymentUrl;

        return $orderId;
    }

    /**
     * @param string $code
     * @return bool
     */
    public function canHandle(string $code): bool
    {
        return in_array($code, [PaymentsConfigProvider::CODE, PaymentsCardConfigProvider::CODE], true);
    }

    /**
     * Get redirect URL for payment method
     *
     * @param Quote $quote
     * @param int|null $orderId
     * @return string
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        return $this->redirectUrl;
    }
}
