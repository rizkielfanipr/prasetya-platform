<?php

declare(strict_types=1);

namespace App\Integrations\Duitku\Exceptions;

use App\Domain\Payments\Exceptions\GatewayException;

final class DuitkuException extends GatewayException {}
