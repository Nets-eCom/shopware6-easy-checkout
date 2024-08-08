<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\Webhook;

enum EventNameEnum: string
{
    case PAYMENT_CREATED = 'payment.created';
    case PAYMENT_RESERVATION_CREATED = 'payment.reservation.created';
    case PAYMENT_RESERVATION_CREATED_V2 = 'payment.reservation.created.v2';
    case PAYMENT_RESERVATION_FAILED = 'payment.reservation.failed';
    case PAYMENT_CHECKOUT_COMPLETED = 'payment.checkout.completed';
    case PAYMENT_CHARGE_CREATED = 'payment.charge.created.v2';
    case PAYMENT_CHARGE_FAILED = 'payment.charge.failed';
    case PAYMENT_REFUND_INITIATED = 'payment.refund.initiated.v2';
    case PAYMENT_REFUND_FAILED = 'payment.refund.failed';
    case PAYMENT_REFUND_COMPLETED = 'payment.refund.completed';
    case PAYMENT_CANCEL_CREATED = 'payment.cancel.created';
    case PAYMENT_CANCEL_FAILED = 'payment.cancel.failed';
}
