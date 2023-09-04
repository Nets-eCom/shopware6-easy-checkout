<?php

namespace Nets\Checkout\Service\Easy\Api;

class Payment
{
    private $paymentObj;

    public function __construct(string $paymentJson)
    {
        $this->paymentObj = json_decode($paymentJson);
    }

    public function getPaymentId()
    {
        return $this->paymentObj->payment->paymentId;
    }

    public function getPaymentType()
    {
        return $this->paymentObj->payment->paymentDetails->paymentType ?? null;
    }

    public function getCardDetails()
    {
        if ($this->getPaymentType() == 'CARD') {
            return ['maskedPan' => $this->paymentObj->payment->paymentDetails->cardDetails->maskedPan,
                'expiryDate'    => $this->paymentObj->payment->paymentDetails->cardDetails->expiryDate];
        }
    }

    public function getPaymentMethod()
    {
        return $this->paymentObj->payment->paymentDetails->paymentMethod ?? null;
    }

    public function getReservedAmount()
    {
        if ($this->getPaymentType() == 'A2A') {
            return $this->paymentObj->payment->summary->chargedAmount ?? 0;
        }

        return $this->paymentObj->payment->summary->reservedAmount ?? null;
    }

    public function getCheckoutUrl()
    {
        return $this->paymentObj->payment->checkout->url;
    }

    public function getFirstChargeId()
    {
        if (isset($this->paymentObj->payment->charges)) {
            $charges = current($this->paymentObj->payment->charges);

            return $charges->chargeId;
        }
    }

    public function getChargedAmount()
    {
        return $this->paymentObj->payment->summary->chargedAmount ?? 0;
    }

    public function getCancelledAmount()
    {
        return $this->paymentObj->payment->summary->cancelledAmount ?? 0;
    }

    public function getRefundedAmount()
    {
        if (isset($this->paymentObj->payment->summary->refundedAmount)) {
            return $this->paymentObj->payment->summary->refundedAmount;
        } elseif (isset($this->paymentObj->payment->refunds)) {
            $refunds = json_decode(json_encode($this->paymentObj->payment->refunds), true);

            $new_array = array_filter($refunds, fn ($var) => $var['state'] == 'Pending' || $var['state'] == 'Completed');

            return array_sum(array_column($new_array, 'amount'));
        }

        return 0;
    }

    public function getOrderAmount()
    {
        return $this->paymentObj->payment->orderDetails->amount;
    }

    public function getAllCharges()
    {
        if (isset($this->paymentObj->payment->charges)) {
            return $this->paymentObj->payment->charges;
        }
    }

    public function getAllRefund()
    {
        if (isset($this->paymentObj->payment->refunds)) {
            return $this->paymentObj->payment->refunds;
        }
    }

    public function getPaymentData()
    {
        return $this->paymentObj;
    }

    public function getOrderId()
    {
        return $this->paymentObj->payment->orderDetails->reference;
    }
}
