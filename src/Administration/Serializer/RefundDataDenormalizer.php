<?php

declare(strict_types=1);

namespace NexiNets\Administration\Serializer;

use NexiNets\Administration\Model\ChargeItem;
use NexiNets\Administration\Model\RefundData;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RefundDataDenormalizer implements DenormalizerInterface
{
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === RefundData::class
            && isset($data['items'])
            && $data['items'] !== [];
    }

    public function denormalize($data, string $type, ?string $format = null, array $context = []): RefundData
    {
        $items = [];

        foreach ($data['items'] as $item) {
            $items[] = new ChargeItem($item['chargeId'], $item['reference'], (int) $item['quantity'], $item['amount']);
        }

        return new RefundData($data['amount'], $items);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            RefundData::class => true,
        ];
    }
}