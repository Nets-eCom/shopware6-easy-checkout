<?php declare(strict_types=1);

namespace NexiNets\CheckoutApi\Model\Result\RetrievePayment;

enum PaymentStatusEnum: string
{
    case NEW = 'new';
    case RESERVED = 'reserved';
    case CHARGED = 'charged';
    case PARTIALLY_CHARGED = 'partially_charged';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case PENDING_REFUND = 'pending_refund';
    case CANCELLED = 'cancelled';
    case TERMINATED = 'terminated';
}
