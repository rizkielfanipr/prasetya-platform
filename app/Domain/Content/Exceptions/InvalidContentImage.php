<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use DomainException;

/** An upload that is not an acceptable post image. The message is shown next to the image field. */
final class InvalidContentImage extends DomainException {}
