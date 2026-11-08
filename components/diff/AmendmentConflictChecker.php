<?php

declare(strict_types=1);

namespace app\components\diff;

use app\components\{HTMLTools, MotionSorter};
use app\models\db\{Amendment, IMotion, Motion, User};
use app\models\sectionTypes\ISectionType;
use app\models\settings\{PrivilegeQueryContext, Privileges};

final class AmendmentConflictChecker
{
    public const MAX_SELECTED = 50;

    public static function canRead(IMotion $item): bool
    {
        if (!$item->isReadable()) {
            return false;
        }
        $context = PrivilegeQueryContext::imotion($item);
        return $item->isVisible() || User::haveOneOfPrivileges($item->getMyConsultation(), [
            Privileges::PRIVILEGE_MOTION_SEE_UNPUBLISHED,
            Privileges::PRIVILEGE_SCREENING,
            Privileges::PRIVILEGE_MOTION_STATUS_EDIT,
        ], $context);
    }

    /** @return Amendment[] */
    public static function eligibleAmendments(Motion $motion): array
    {
        $consultation = $motion->getMyConsultation();
        if (!$consultation) {
            return [];
        }
        $amendments = array_filter($motion->amendments, static function (Amendment $amendment) use ($motion): bool {
            return $amendment->motionId === $motion->id
                && $amendment->amendingAmendmentId === null
                && $amendment->isSubmitted()
                && !in_array($amendment->status, [
                    Amendment::STATUS_PROPOSED_MODIFIED_AMENDMENT,
                    Amendment::STATUS_PROPOSED_MODIFIED_MOTION,
                    Amendment::STATUS_INLINE_REPLY,
                ], true)
                && self::canRead($amendment);
        });
        return MotionSorter::getSortedAmendments($consultation, array_values($amendments));
    }

    public static function selectedByDefault(Amendment $amendment): bool
    {
        return $amendment->isVisible() && in_array($amendment->status, [
            Amendment::STATUS_SUBMITTED_SCREENED,
            Amendment::STATUS_SUBMITTED_UNSCREENED,
            Amendment::STATUS_SUBMITTED_UNSCREENED_CHECKED,
            Amendment::STATUS_QUORUM_REACHED,
            Amendment::STATUS_VOTE,
            Amendment::STATUS_PAUSED,
        ], true);
    }

    /**
     * @param Amendment[] $amendments
     * @return array<int, array<int, AmendmentConflictSection>>
     */
    public static function snapshot(array $amendments): array
    {
        $snapshots = [];
        foreach ($amendments as $amendment) {
            $id = (int)$amendment->id;
            $snapshots[$id] = [];
            foreach ($amendment->getActiveSections() as $section) {
                $settings = $section->getSettings();
                if (!$settings) {
                    continue; // getActiveSections normally already excludes missing settings.
                }
                $original = $section->getOriginalMotionSection();
                $supported = $settings->type === ISectionType::TYPE_TEXT_SIMPLE && $original !== null;
                $snapshots[$id][$section->sectionId] = new AmendmentConflictSection(
                    $settings->title,
                    $original?->getData() ?? '',
                    $section->getData(),
                    supported: $supported,
                    fixedWidth: (bool)$settings->fixedWidth,
                    lineNumbers: $supported ? $section->getParagraphLineNumberHelper() : null,
                );
            }
        }
        return $snapshots;
    }

    /**
     * @param array<int, array<int, AmendmentConflictSection>> $amendments
     * @return array{pairs: array, warnings: array<int, string[]>}
     */
    public static function compare(array $amendments): array
    {
        $pairs = $warnings = $prepared = [];
        $diff = new Diff();
        $renderer = new DiffRenderer();
        foreach ($amendments as $id => $sections) {
            foreach ($sections as $sectionId => $section) {
                if ($section->supported) {
                    try {
                        $original = HTMLTools::sectionSimpleHTML($section->original);
                        $affected = AmendmentRewriter::computeAffectedParagraphs($original, HTMLTools::sectionSimpleHTML($section->text));
                        $prepared[$id][$sectionId] = [];
                        foreach ($affected as $paragraph => $html) {
                            $prepared[$id][$sectionId][$paragraph] = [
                                'words' => $diff->compareHtmlParagraphsToWordArray([$original[$paragraph]->html], [$html]),
                                'diff' => $renderer->renderHtmlWithPlaceholders($diff->computeLineDiff($original[$paragraph]->html, $html)),
                            ];
                        }
                    } catch (\Throwable $e) {
                        \Yii::error($e, __METHOD__);
                        unset($prepared[$id][$sectionId]);
                        $warnings[$id][] = $section->title;
                    }
                }
                if (!$section->supported && $section->text !== $section->original) {
                    $warnings[$id][] = $section->title;
                }
            }
        }
        $ids = array_keys($amendments);
        foreach ($ids as $index => $leftId) {
            foreach (array_slice($ids, $index + 1) as $rightId) {
                $passages = [];
                foreach (array_unique(array_merge(array_keys($amendments[$leftId]), array_keys($amendments[$rightId]))) as $sectionId) {
                    $left = $amendments[$leftId][$sectionId] ?? null;
                    $right = $amendments[$rightId][$sectionId] ?? null;
                    if (!$left || !$right || $left->original !== $right->original) {
                        $title = $left ? $left->title : ($right ? $right->title : (string)$sectionId);
                        $warnings[$leftId][] = $title;
                        $warnings[$rightId][] = $title;
                        continue;
                    }
                    if (!$left->supported || !$right->supported) {
                        continue;
                    }
                    if (!isset($prepared[$leftId][$sectionId], $prepared[$rightId][$sectionId])) {
                        continue;
                    }
                    $leftParagraphs = $prepared[$leftId][$sectionId];
                    $rightParagraphs = $prepared[$rightId][$sectionId];
                    $paragraphs = [];
                    foreach (array_intersect(array_keys($leftParagraphs), array_keys($rightParagraphs)) as $paragraph) {
                        $leftWords = $leftParagraphs[$paragraph]['words'];
                        $rightWords = $rightParagraphs[$paragraph]['words'];
                        $collides = false;
                        // Both directions matter: insertion alignment can be directional.
                        foreach ([[$leftWords, $rightWords], [$rightWords, $leftWords]] as [$first, $second]) {
                            try {
                                AmendmentRewriter::mergeWordArrays($first, $second);
                            } catch (\Exception $e) {
                                // The existing merge checker also treats merge exceptions as conflicts.
                                $collides = true;
                            }
                        }
                        if ($collides) {
                            $data = [
                                'motionNewDiff' => $leftParagraphs[$paragraph]['diff'],
                                'amendmentDiff' => $rightParagraphs[$paragraph]['diff'],
                            ];
                            if (isset($left->lineNumbers[$paragraph], $left->lineNumbers[$paragraph + 1])) {
                                $data['lineFrom'] = $left->lineNumbers[$paragraph];
                                $data['lineTo'] = $left->lineNumbers[$paragraph + 1] - 1;
                            }
                            $paragraphs[$paragraph] = $data;
                        }
                    }
                    if ($paragraphs) {
                        ksort($paragraphs);
                        $passages[$sectionId] = ['title' => $left->title, 'fixedWidth' => $left->fixedWidth, 'paragraphs' => $paragraphs];
                    }
                }
                if ($passages) {
                    $pairs[] = ['left' => $leftId, 'right' => $rightId, 'sections' => $passages];
                }
            }
        }
        foreach ($warnings as &$titles) {
            $titles = array_values(array_unique($titles));
        }
        return ['pairs' => $pairs, 'warnings' => $warnings];
    }
}
