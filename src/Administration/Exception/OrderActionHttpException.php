<?php

declare(strict_types=1);

namespace NexiNets\Administration\Exception;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

final class OrderActionHttpException extends HttpException
{
    private const NEXI_NETS__CHARGE_FAILED = 'NEXI_NETS__NETS_CHARGE_FAILED';

    private const NEXI_NETS__REFUND_FAILED = 'NEXI_NETS__NETS_REFUND_FAILED';

    private const NEXI_NETS__REFUND_AMOUNT_EXCEEDED = 'NEXI_NETS__REFUND_AMOUNT_EXCEEDED';

    private const NEXI_NETS__CANCEL_FAILED = 'NEXI_NETS__NETS_CANCEL_FAILED';

    private static string $chargeFailedMessage = 'Charge failed for a given payment ID: {{ paymentId }}';

    private static string $refundAmountExceededMessage = 'Refund value must not exceed total amount of given charge ID: {{ chargeId }}';

    private static string $refundFailedMessage = 'Refund failed for a given charge ID: {{ chargeId }}';

    private static string $cancelFailedMessage = 'Cancel failed for a given payment ID: {{ paymentId }}';

    public static function refundAmountExceeded(string $chargeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEXI_NETS__REFUND_AMOUNT_EXCEEDED,
            self::$refundAmountExceededMessage,
            [
                'chargeId' => $chargeId,
            ]
        );
    }

    public static function refundFailed(string $chargeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEXI_NETS__REFUND_FAILED,
            self::$refundFailedMessage,
            [
                'chargeId' => $chargeId,
            ]
        );
    }

    public static function chargeFailed(string $paymentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEXI_NETS__CHARGE_FAILED,
            self::$chargeFailedMessage,
            [
                'paymentId' => $paymentId,
            ]
        );
    }

    public static function cancelFailed(string $paymentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NEXI_NETS__CANCEL_FAILED,
            self::$cancelFailedMessage,
            [
                'paymentId' => $paymentId,
            ]
        );
    }
}
