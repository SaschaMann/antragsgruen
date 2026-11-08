<?php

declare(strict_types=1);

namespace Tests\Unit;

use app\components\diff\AmendmentConflictChecker;
use app\components\UrlHelper;
use app\controllers\MotionController;
use app\models\db\{Amendment, AmendmentSection, Consultation, ConsultationSettingsMotionSection, Motion, MotionSection, Site, User};
use app\models\http\{HtmlErrorResponse, HtmlResponse};
use app\models\sectionTypes\ISectionType;
use app\models\settings\Consultation as ConsultationSettings;
use app\models\settings\Privileges;
use Codeception\Test\Unit;

final class AmendmentConflictControllerTest extends Unit
{
    private Consultation $consultation;
    private ConsultationSettings $settings;
    private Motion $motion;
    private MotionController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        require dirname(__DIR__) . '/Support/ConflictCheck/bootstrap.php';
        \Yii::$app->set('user', ['class' => \yii\web\User::class, 'identityClass' => User::class, 'enableSession' => false]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->settings = new ConsultationSettings(null);
        $this->settings->amendmentConflictCheckEnabled = true;
        $this->consultation = $this->getMockBuilder(Consultation::class)->onlyMethods(['attributes', 'getSettings'])->getMock();
        $this->consultation->method('attributes')->willReturn(['id', 'urlPath', 'amendmentNumbering']);
        $this->consultation->method('getSettings')->willReturn($this->settings);
        $this->consultation->id = 1;
        $this->consultation->urlPath = 'test';
        $this->consultation->amendmentNumbering = 'prefix';
        $site = $this->getMockBuilder(Site::class)->onlyMethods(['attributes'])->getMock();
        $site->method('attributes')->willReturn(['id', 'subdomain']);
        $site->id = 1;
        $site->subdomain = 'test';
        $this->consultation->populateRelation('site', $site);
        UrlHelper::setCurrentSite($site);
        UrlHelper::setCurrentConsultation($this->consultation);
        $this->motion = $this->getMockBuilder(Motion::class)->onlyMethods(['attributes', 'getMyConsultation', 'getMotionSlug', 'getBreadcrumbTitle'])->getMock();
        $this->motion->method('attributes')->willReturn(['id', 'status']);
        $this->motion->method('getMyConsultation')->willReturn($this->consultation);
        $this->motion->method('getMotionSlug')->willReturn('motion-1');
        $this->motion->method('getBreadcrumbTitle')->willReturn('Motion');
        $this->motion->id = 1;
        $this->motion->status = Motion::STATUS_SUBMITTED_SCREENED;
        $this->motion->populateRelation('amendments', [$this->amendment(1, 'left'), $this->amendment(2, 'right')]);
        $this->controller = $this->getMockBuilder(MotionController::class)->setConstructorArgs(['motion', \Yii::$app])->onlyMethods(['getMotionWithCheck', 'render'])->getMock();
        $this->controller->method('getMotionWithCheck')->willReturn($this->motion);
        $this->controller->method('render')->willReturnCallback(fn ($view, $params) => $this->controller->renderPartial('@app/views/motion/' . $view, $params));
        $this->controller->consultation = $this->consultation;
        \Yii::$app->controller = $this->controller;
    }

    protected function tearDown(): void
    {
        \Yii::$app = null;
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    private function amendment(int $id, string $replacement): Amendment
    {
        $amendment = $this->getMockBuilder(Amendment::class)->onlyMethods(['attributes', 'getMyConsultation', 'getMyMotion', 'getTitleWithPrefix', 'getActiveSections'])->getMock();
        $amendment->method('attributes')->willReturn(['id', 'motionId', 'amendingAmendmentId', 'status', 'titlePrefix']);
        $amendment->id = $id;
        $amendment->motionId = 1;
        $amendment->status = Amendment::STATUS_SUBMITTED_SCREENED;
        $amendment->titlePrefix = 'A-' . $id;
        $amendment->method('getMyConsultation')->willReturn($this->consultation);
        $amendment->method('getMyMotion')->willReturn($this->motion);
        $amendment->method('getTitleWithPrefix')->willReturn('Amendment ' . $id);
        $settings = $this->getMockBuilder(ConsultationSettingsMotionSection::class)->onlyMethods(['attributes'])->getMock();
        $settings->method('attributes')->willReturn(['type', 'title', 'fixedWidth']);
        $settings->type = ISectionType::TYPE_TEXT_SIMPLE;
        $settings->title = 'Motion text';
        $settings->fixedWidth = false;
        $original = $this->getMockBuilder(MotionSection::class)->onlyMethods(['getData'])->getMock();
        $original->method('getData')->willReturn('<p>one two three</p>');
        $section = $this->getMockBuilder(AmendmentSection::class)->onlyMethods(['attributes', 'getSettings', 'getOriginalMotionSection', 'getData', 'getParagraphLineNumberHelper'])->getMock();
        $section->method('attributes')->willReturn(['sectionId']);
        $section->sectionId = 1;
        $section->method('getSettings')->willReturn($settings);
        $section->method('getOriginalMotionSection')->willReturn($original);
        $section->method('getData')->willReturn('<p>one ' . $replacement . ' three</p>');
        $section->method('getParagraphLineNumberHelper')->willReturn([1, 4]);
        $amendment->method('getActiveSections')->willReturn([$section]);
        return $amendment;
    }

    private function post(mixed $ids): HtmlResponse|HtmlErrorResponse
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        \Yii::$app->request->setBodyParams(['amendments' => $ids]);
        return $this->controller->actionAmendmentConflicts('motion-1');
    }

    public function testDisabledGetAndPostAndConsultationIsolation(): void
    {
        $this->settings->amendmentConflictCheckEnabled = false;
        self::assertInstanceOf(HtmlErrorResponse::class, $this->controller->actionAmendmentConflicts('motion-1'));
        self::assertInstanceOf(HtmlErrorResponse::class, $this->post(['1', '2']));
        $this->settings->amendmentConflictCheckEnabled = true;
        self::assertInstanceOf(HtmlResponse::class, $this->post(['1', '2']));
        $this->settings->amendmentConflictCheckEnabled = false;
        self::assertInstanceOf(HtmlErrorResponse::class, $this->post(['1', '2']));
    }

    public function testGuestCanSelectAndSeeConflictsWithoutProcedures(): void
    {
        $get = $this->controller->actionAmendmentConflicts('motion-1');
        self::assertInstanceOf(HtmlResponse::class, $get);
        self::assertStringContainsString('checked', $get->getHtml());
        self::assertStringNotContainsString('class="conflictPair"', $get->getHtml());
        $before = array_map(fn ($a) => $a->getAttributes(), $this->motion->amendments);
        $post = $this->post(['1', '2', '1']);
        self::assertInstanceOf(HtmlResponse::class, $post);
        self::assertSame(1, substr_count($post->getHtml(), 'class="conflictPair"'));
        self::assertStringContainsString('left', $post->getHtml());
        self::assertStringContainsString('right', $post->getHtml());
        self::assertStringContainsString('<details>', $post->getHtml());
        self::assertSame($before, array_map(fn ($a) => $a->getAttributes(), $this->motion->amendments));
    }

    public function testInvalidHiddenAndForeignSelectionsAreRejected(): void
    {
        foreach (['1', [['1']], ['1', '999'], ['1', '-2']] as $ids) {
            self::assertInstanceOf(HtmlErrorResponse::class, $this->post($ids));
        }
        $hidden = $this->motion->amendments[1];
        $hidden->status = Amendment::STATUS_SUBMITTED_UNSCREENED;
        self::assertInstanceOf(HtmlErrorResponse::class, $this->post(['1', '2']));
        $hidden->status = Amendment::STATUS_SUBMITTED_SCREENED;
        $hidden->motionId = 99;
        self::assertInstanceOf(HtmlErrorResponse::class, $this->post(['1', '2']));
    }

    public function testStatusesAndChildAmendments(): void
    {
        $amendment = $this->motion->amendments[0];
        $amendment->status = Amendment::STATUS_WITHDRAWN;
        self::assertFalse(AmendmentConflictChecker::selectedByDefault($amendment));
        self::assertCount(2, AmendmentConflictChecker::eligibleAmendments($this->motion));
        $amendment->amendingAmendmentId = 2;
        self::assertCount(1, AmendmentConflictChecker::eligibleAmendments($this->motion));
        $amendment->amendingAmendmentId = null;
        foreach ([Amendment::STATUS_DRAFT, Amendment::STATUS_DELETED, Amendment::STATUS_PROPOSED_MODIFIED_AMENDMENT] as $status) {
            $amendment->status = $status;
            self::assertCount(1, AmendmentConflictChecker::eligibleAmendments($this->motion));
        }
    }

    public function testSelectionLimitReturnsVisibleMessage(): void
    {
        $more = $this->motion->amendments;
        for ($id = 3; $id <= AmendmentConflictChecker::MAX_SELECTED + 1; $id++) {
            $more[] = $this->amendment($id, 'replacement ' . $id);
        }
        $this->motion->populateRelation('amendments', $more);
        $result = $this->post(array_map(static fn (Amendment $amendment): string => (string)$amendment->id, $more));
        self::assertInstanceOf(HtmlResponse::class, $result);
        self::assertStringContainsString('Select no more than 50', $result->getHtml());
        self::assertStringNotContainsString('class="conflictPair"', $result->getHtml());
    }

    public function testUnpublishedRequiresRelevantPrivilege(): void
    {
        $hidden = $this->motion->amendments[1];
        $hidden->status = Amendment::STATUS_SUBMITTED_UNSCREENED;
        $user = $this->getMockBuilder(User::class)->onlyMethods(['hasPrivilege'])->getMock();
        $user->method('hasPrivilege')->willReturnCallback(static fn ($consultation, $privilege, $context) => $privilege === Privileges::PRIVILEGE_MOTION_SEE_UNPUBLISHED && $context !== null);
        \Yii::$app->user->setIdentity($user);
        self::assertCount(2, AmendmentConflictChecker::eligibleAmendments($this->motion));
        self::assertInstanceOf(HtmlResponse::class, $this->post(['1', '2']));
    }

    public function testEmptySelectionAndUnreadableMotion(): void
    {
        self::assertStringContainsString('Select at least two', $this->post([])->getHtml());
        self::assertStringContainsString('Select at least two', $this->post(['1'])->getHtml());
        $this->motion->status = Motion::STATUS_SUBMITTED_UNSCREENED;
        self::assertInstanceOf(HtmlErrorResponse::class, $this->post(['1', '2']));
    }
}
