<?php

namespace Nets\Checkout\Service\Easy\Api;

class Payment
{
    private $paymentObj;

    public function __construct(string $paymentJson) {
        $this->paymentObj = json_decode($paymentJson);
    }

    public function getPaymentId() {
        return $this->paymentObj->payment->paymentId;
    }

    public function getPaymentType() {
        return isset($this->paymentObj->payment->paymentDetails->paymentType) ?
            $this->paymentObj->payment->paymentDetails->paymentType : null;
    }

    public function getCardDetails() {
        if( $this->getPaymentType() == 'CARD' ) {
            return ['maskedPan'  =>   $this->paymentObj->payment->paymentDetails->cardDetails->maskedPan,
                'expiryDate' =>  $this->paymentObj->payment->paymentDetails->cardDetails->expiryDate];
        }
    }

    public function getPaymentMethod() {
        return isset($this->paymentObj->payment->paymentDetails->paymentMethod) ?
            $this->paymentObj->payment->paymentDetails->paymentMethod : null;
    }

    public function getReservedAmount() {
        if( $this->getPaymentType() == 'A2A' ) {
            return isset($this->paymentObj->payment->summary->chargedAmount) ?
                 $this->paymentObj->payment->summary->chargedAmount : 0;
        }else{
            return isset($this->paymentObj->payment->summary->reservedAmount) ?
                $this->paymentObj->payment->summary->reservedAmount : null;
        }
    }

    public function getCheckoutUrl() {
        return $this->paymentObj->payment->checkout->url;
    }

    public function getFirstChargeId()
    {
        if (isset($this->paymentObj->payment->charges)) {
            $charges = current($this->paymentObj->payment->charges);
            return $charges->chargeId;
        }
    }

    public function getChargedAmount() {
        return isset($this->paymentObj->payment->summary->chargedAmount) ?
            $this->paymentObj->payment->summary->chargedAmount : 0;
    }

	 public function getCancelledAmount() {
        return isset($this->paymentObj->payment->summary->cancelledAmount) ?
            $this->paymentObj->payment->summary->cancelledAmount : 0;
    }
	
    public function getRefundedAmount() {
        if(isset($this->paymentObj->payment->summary->refundedAmount)){
            return $this->paymentObj->payment->summary->refundedAmount;
        }else if(isset($this->paymentObj->payment->refunds)){

            $refunds = json_decode(json_encode($this->paymentObj->payment->refunds), true);
         
            $new_array = array_filter($refunds , function($var) {
                return $var['state'] == 'Pending' || $var['state'] == 'Completed';
            });
            $sum = array_sum(array_column($new_array ,'amount'));
            return $sum;
        }else{
            return 0;
        }
    }

    public function getOrderAmount() {
        return $this->paymentObj->payment->orderDetails->amount;
    }

    public function getAllCharges() {
        if(isset($this->paymentObj->payment->charges)) {
            return $this->paymentObj->payment->charges;

        }
    }

    public function getAllRefund()
    {
        if (isset($this->paymentObj->payment->refunds)) {
            return $this->paymentObj->payment->refunds;
        }
    }
    public function getPaymentData() {
         return $this->paymentObj; 
    }
    public function getOrderId()
    {
        return $this->paymentObj->payment->orderDetails->reference;
    }
}