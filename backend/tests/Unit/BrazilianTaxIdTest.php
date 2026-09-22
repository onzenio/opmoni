<?php

namespace Tests\Unit;

use App\Services\BrazilianTaxId;
use PHPUnit\Framework\TestCase;

class BrazilianTaxIdTest extends TestCase
{
    public function test_normalizes_punctuation(): void
    {
        $service = new BrazilianTaxId;

        $this->assertSame('27865757000102', $service->normalize('27.865.757/0001-02'));
        $this->assertSame('52998224725', $service->normalize('529.982.247-25'));
    }

    public function test_validates_cpf_and_rejects_repeated_digits(): void
    {
        $service = new BrazilianTaxId;

        $this->assertTrue($service->isValidCpf('52998224725'));
        $this->assertFalse($service->isValidCpf('52998224724'));
        $this->assertFalse($service->isValidCpf('11111111111'));
    }

    public function test_validates_cnpj_and_rejects_repeated_digits(): void
    {
        $service = new BrazilianTaxId;

        $this->assertTrue($service->isValidCnpj('27865757000102'));
        $this->assertFalse($service->isValidCnpj('27865757000103'));
        $this->assertFalse($service->isValidCnpj('00000000000000'));
    }
}
