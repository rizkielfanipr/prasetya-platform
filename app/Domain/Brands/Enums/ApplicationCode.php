<?php

declare(strict_types=1);

namespace App\Domain\Brands\Enums;

enum ApplicationCode: string
{
    case Webworks = 'WEBWORKS';
    case Satamu = 'SATAMU';

    public function invoicePrefix(): string
    {
        return match ($this) {
            self::Webworks => 'PW',
            self::Satamu => 'SAT',
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::Webworks => 'Prasetya Webworks',
            self::Satamu => 'Satamu Invitation',
        };
    }
}
