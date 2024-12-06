<?php

declare(strict_types=1);

namespace NexiNets\CheckoutApi\Api;

enum ErrorCodeEnum: int
{
    case Unknown = 1000;
    case OverCharge = 1001;
    case ZeroCharge = 1002;
    case NotReserved = 1003;
    case AlreadyReserved = 1004;
    case AmountDiffersFromOrderItems = 1005;
    case ConsumerNotAddedToPayment = 1006;
    case PartialCancelNotAllowed = 1007;
    case AlreadyCharged = 1008;
    case InvalidRefundAmount = 1009;
    case ReservationExpired = 1010;
    case ChargeNotFound = 1011;
    case RefundCancelled = 1012;
    case RefundExpired = 1013;
    case RefundFailed = 1014;
    case OrderItemsMissing = 1015;
    case OrderReferenceWasPreviouslySet = 1016;
    case ChangingCheckoutDomainNotAllowed = 1017;
    case PaymentMissingOrderReference = 1018;
    case AlreadyCancelled = 1019;
    case PaymentMethodChargeFailed = 1020;
    case ShippingCostHasNotBeenSpecified = 1021;
    case PaymentReserveMethodFailed = 1022;
    case PaymentAlreadyRefunded = 1023;
    case PartialChargeNotAllowed = 1024;
    case RefundCancelFailed = 1025;
    case ChargeTriedWithDifferentAmount = 1026;
    case RefundTriedWithDifferentAmount = 1027;
    case UpdateOrderAtAfterPayFailed = 1028;
    case HardDecline = 1029;
    case ExpiredSubscription = 1030;
    case InvalidSubscription = 1031;
    case NotGenuineSubscriptionTraceId = 1032;
}
