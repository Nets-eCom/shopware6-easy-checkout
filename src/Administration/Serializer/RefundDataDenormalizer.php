<?php

declare(strict_types=1);

namespace NexiNets\Administration\Serializer;

use NexiNets\Administration\Model\ChargeItem;
use NexiNets\Administration\Model\RefundData;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RefundDataDenormalizer implements DenormalizerInterface
{
    /**
     * @param array<string, string> $context
     */
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === RefundData::class
            && isset($data['charges'])
            && $data['charges'] !== [];
    }

    /**
     * @param array<string, string> $context
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): RefundData
    {
        $chargeData = [];

        foreach ($data['charges'] as $chargeId => $charges) {
            $chargeData[$chargeId] = [
                'amount' => $charges['amount'],
                'items' => array_map(fn (array $item): ChargeItem => new ChargeItem(
                    $chargeId,
                    $item['name'],
                    (int) $item['quantity'],
                    $item['unit'],
                    (float) $item['unitPrice'],
                    $item['amount'],
                    (float) $item['netTotalAmount'],
                    $item['reference'],
                    null,
                ), $charges['items']),
            ];
        }

        return new RefundData($data['amount'], $chargeData);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            RefundData::class => true,
        ];
    }
}
