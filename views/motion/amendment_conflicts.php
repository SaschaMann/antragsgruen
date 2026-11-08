<?php

use app\components\{Html, UrlHelper};
use app\models\db\{Amendment, Motion};

/**
 * @var \yii\web\View $this
 * @var Motion $motion
 * @var Amendment[] $amendments
 * @var int[] $selectedIds
 * @var array|null $result
 * @var string|null $error
 */

/** @var \app\controllers\MotionController $controller */
$controller = $this->context;
$controller->layoutParams->addBreadcrumb($motion->getBreadcrumbTitle(), UrlHelper::createMotionUrl($motion));
$controller->layoutParams->addBreadcrumb(Yii::t('motion', 'conflicts_title'));
$this->title = Yii::t('motion', 'conflicts_title');
$titles = $urls = [];
foreach ($amendments as $amendment) {
    $titles[$amendment->id] = $amendment->getTitleWithPrefix();
    $urls[$amendment->id] = UrlHelper::createAmendmentUrl($amendment);
}
?>
<h1><?= Html::encode($this->title) ?></h1>
<div class="content amendmentConflictCheck">
    <p><?= Html::encode(Yii::t('motion', 'conflicts_intro')) ?></p>
    <p class="help-block"><?= Html::encode(Yii::t('motion', 'conflicts_performance')) ?></p>
    <?php if ($error !== null): ?>
        <p class="alert alert-danger" role="alert"><?= Html::encode($error) ?></p>
    <?php endif; ?>
    <?php if (!$amendments): ?>
        <p><?= Html::encode(Yii::t('motion', 'conflicts_no_amendments')) ?></p>
    <?php else: ?>
        <?= Html::beginForm(UrlHelper::createMotionUrl($motion, 'amendment-conflicts'), 'post', ['id' => 'amendmentConflictForm']) ?>
        <fieldset>
            <legend><?= Html::encode(Yii::t('amend', 'amendments')) ?></legend>
            <?php foreach ($amendments as $amendment): ?>
                <div class="checkbox">
                    <label>
                        <?= Html::checkbox('amendments[]', in_array($amendment->id, $selectedIds, true), ['value' => $amendment->id]) ?>
                        <?= Html::encode($amendment->getTitleWithPrefix()) ?>
                        (<?= Html::encode($controller->consultation->getStatuses()->getStatusName($amendment->status)) ?>)
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit"><?= Html::encode(Yii::t('motion', 'conflicts_check')) ?></button>
        <?= Html::endForm() ?>
    <?php endif; ?>
</div>
<?php if ($result !== null): ?>
    <?= $this->render('_amendment_conflict_results', ['result' => $result, 'titles' => $titles, 'urls' => $urls]) ?>
<?php endif; ?>
