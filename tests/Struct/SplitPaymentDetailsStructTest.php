<?php

declare(strict_types=1);

namespace Nexi\Checkout\Tests\Struct;

use Nexi\Checkout\Struct\SplitPaymentDetailsStruct;
use PHPUnit\Framework\TestCase;

final class SplitPaymentDetailsStructTest extends TestCase
{
    public function testItReturnsSubselections(): void
    {
        $subselections = [
            [
                'value' => 'Card',
                'label' => 'Card',
            ],
            [
                'value' => 'Swish',
                'label' => 'Swish',
            ],
        ];

        $sut = new SplitPaymentDetailsStruct($subselections, null);

        $this->assertSame($subselections, $sut->getSubselections());
    }

    public function testItReturnsCreateEmbeddedPaymentUrl(): void
    {
        $url = 'https://example.com/create-embedded';

        $sut = new SplitPaymentDetailsStruct([], $url);

        $this->assertSame($url, $sut->getCreateEmbeddedPaymentUrl());
    }

    public function testItReturnsNullCreateEmbeddedPaymentUrlByDefault(): void
    {
        $sut = new SplitPaymentDetailsStruct([]);

        $this->assertNull($sut->getCreateEmbeddedPaymentUrl());
    }

    public function testItReturnsEmptySubselectionsWhenNoneProvided(): void
    {
        $sut = new SplitPaymentDetailsStruct();

        $this->assertSame([], $sut->getSubselections());
    }
}
