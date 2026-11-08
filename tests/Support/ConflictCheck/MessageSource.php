<?php

namespace Tests\Support\ConflictCheck;

/** Flatten structured translations without querying consultation wording overrides. */
final class MessageSource extends \yii\i18n\PhpMessageSource
{
    protected function loadMessages($category, $language): array
    {
        return array_map(static fn ($entry) => is_array($entry) ? $entry['text'] : $entry, parent::loadMessages($category, $language));
    }
}
