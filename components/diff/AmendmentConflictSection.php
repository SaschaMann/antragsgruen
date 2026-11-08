<?php

declare(strict_types=1);

namespace app\components\diff;

/** A request-local snapshot: no database access during pair comparisons. */
final class AmendmentConflictSection
{
    /** @param int[]|null $lineNumbers */
    public function __construct(
        public readonly string $title,
        public readonly string $original,
        public readonly string $text,
        public readonly bool $supported,
        public readonly bool $fixedWidth = false,
        public readonly ?array $lineNumbers = null,
    ) {
    }
}
