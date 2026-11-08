<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\components\diff\{AmendmentConflictChecker, AmendmentConflictSection, AmendmentRewriter};
use app\models\settings\Consultation;
use Codeception\Test\Unit;
use Tests\Support\ConflictCheck\Fixture;

final class AmendmentConflictCheckerTest extends Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        require dirname(__DIR__) . '/Support/ConflictCheck/bootstrap.php';
    }

    protected function tearDown(): void
    {
        \Yii::$app = null;
        parent::tearDown();
    }

    private function section(string $text, string $original = '<p>one two three</p><p>four five six</p>'): AmendmentConflictSection
    {
        return new AmendmentConflictSection('Text', $original, $text, supported: true, lineNumbers: [1, 4, 7]);
    }

    public function testConflictIsDeduplicatedAndLabelsSurviveReverseOrder(): void
    {
        $left = $this->section('<p>one left three</p><p>four five six</p>');
        $right = $this->section('<p>one right three</p><p>four five six</p>');
        $result = AmendmentConflictChecker::compare([10 => [1 => $left], 20 => [1 => $right]]);
        self::assertCount(1, $result['pairs']);
        self::assertSame([], $result['warnings']);
        $paragraph = $result['pairs'][0]['sections'][1]['paragraphs'][0];
        self::assertStringContainsString('left', $paragraph['motionNewDiff']);
        self::assertStringContainsString('right', $paragraph['amendmentDiff']);
        self::assertSame(1, $paragraph['lineFrom']);
        self::assertSame(3, $paragraph['lineTo']);
        $reverse = AmendmentConflictChecker::compare([20 => [1 => $right], 10 => [1 => $left]]);
        self::assertCount(1, $reverse['pairs']);
        self::assertStringContainsString('right', $reverse['pairs'][0]['sections'][1]['paragraphs'][0]['motionNewDiff']);
    }

    public function testCompatibleChangesAndIdenticalTextsMatchMergeChecker(): void
    {
        $left = $this->section('<p>ONE two three</p><p>four five six</p>');
        foreach ([
            '<p>one two three</p><p>four FIVE six</p>',
            '<p>one two THREE</p><p>four five six</p>',
            $left->text,
        ] as $text) {
            $right = $this->section($text);
            $expected = AmendmentRewriter::getCollidingParagraphs($left->original, $left->text, $right->text)
                || AmendmentRewriter::getCollidingParagraphs($left->original, $right->text, $left->text);
            $result = AmendmentConflictChecker::compare([1 => [1 => $left], 2 => [1 => $right]]);
            self::assertSame($expected, count($result['pairs']) > 0);
            self::assertSame([], $result['warnings']);
        }
    }

    public function testMultipleSectionsAndEveryPair(): void
    {
        $fixture = Fixture::sections(3, 2, dense: true);
        foreach ($fixture as &$sections) {
            $sections[2] = $sections[1];
        }
        $result = AmendmentConflictChecker::compare($fixture);
        self::assertCount(3, $result['pairs']);
        self::assertCount(2, $result['pairs'][0]['sections']);
        self::assertCount(2, $result['pairs'][0]['sections'][1]['paragraphs']);
    }

    public function testUnsupportedMissingAndIncompatibleSectionsAreNotReportedAsChecked(): void
    {
        $left = $this->section('<p>left</p>');
        $right = $this->section('<p>right</p>', '<p>different baseline</p>');
        $unsupported = new AmendmentConflictSection('Attachment', 'old.pdf', 'new.pdf', supported: false);
        $result = AmendmentConflictChecker::compare([1 => [1 => $left, 2 => $unsupported], 2 => [1 => $right]]);
        self::assertSame([], $result['pairs']);
        self::assertContains('Attachment', $result['warnings'][1]);
        self::assertContains('Text', $result['warnings'][2]);
    }

    public function testSettingsAreOptInAndIsolated(): void
    {
        $first = new Consultation(null);
        $second = new Consultation(null);
        self::assertFalse($first->amendmentConflictCheckEnabled);
        $first->saveConsultationForm(['amendmentConflictCheckEnabled' => '1'], ['amendmentConflictCheckEnabled']);
        self::assertTrue((new Consultation($first->jsonSerialize()))->amendmentConflictCheckEnabled);
        self::assertFalse($second->amendmentConflictCheckEnabled);
        $first->saveConsultationForm([], ['amendmentConflictCheckEnabled']);
        self::assertFalse($first->amendmentConflictCheckEnabled);
    }
}
