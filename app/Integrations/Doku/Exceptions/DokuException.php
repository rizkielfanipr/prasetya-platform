<?php

declare(strict_types=1);

namespace App\Integrations\Doku\Exceptions;

use App\Domain\Payments\Exceptions\GatewayException;

final class DokuException extends GatewayException {}
