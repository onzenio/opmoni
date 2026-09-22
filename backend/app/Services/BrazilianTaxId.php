<?php

namespace App\Services;

final class BrazilianTaxId
{
    public function normalize(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function isValidCpf(string $value): bool
    {
        $digits = $this->normalize($value);

        return strlen($digits) === 11
            && ! preg_match('/^(\d)\1+$/', $digits)
            && $this->digit($digits, 9, range(10, 2)) === (int) $digits[9]
            && $this->digit($digits, 10, range(11, 2)) === (int) $digits[10];
    }

    public function isValidCnpj(string $value): bool
    {
        $digits = $this->normalize($value);

        return strlen($digits) === 14
            && ! preg_match('/^(\d)\1+$/', $digits)
            && $this->digit($digits, 12, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) === (int) $digits[12]
            && $this->digit($digits, 13, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) === (int) $digits[13];
    }

    /** @param list<int> $weights */
    private function digit(string $digits, int $length, array $weights): int
    {
        $sum = 0;

        for ($index = 0; $index < $length; $index++) {
            $sum += (int) $digits[$index] * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
