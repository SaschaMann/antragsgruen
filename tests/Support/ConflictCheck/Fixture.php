<?php

declare(strict_types=1);

namespace Tests\Support\ConflictCheck;

use app\components\diff\AmendmentConflictSection;

final class Fixture
{
    /** @return array<int, array<int, AmendmentConflictSection>> */
    public static function sections(int $count, int $paragraphs, bool $dense): array
    {
        $base = [];
        for ($p = 0; $p < $paragraphs; $p++) {
            $base[] = '<p>Paragraph ' . $p . ': We will support local communities with fair funding, transparent decisions, accessible services and accountable democratic institutions.</p>';
        }
        $original = implode('', $base);
        $sections = [];
        for ($id = 1; $id <= $count; $id++) {
            $text = $base;
            $changed = $dense ? range(0, min(9, $paragraphs - 1)) : [($id - 1) % $paragraphs];
            foreach ($changed as $p) {
                $text[$p] = str_replace('fair funding', 'funding proposal ' . $id, $text[$p]);
            }
            $sections[$id][1] = new AmendmentConflictSection('Motion text', $original, implode('', $text), supported: true, lineNumbers: range(1, $paragraphs + 1));
        }
        return $sections;
    }
}
