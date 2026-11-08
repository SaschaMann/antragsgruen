<?php

use app\components\Html;

/**
 * @var \yii\web\View $this
 * @var array $result
 * @var array<int, string> $titles
 * @var array<int, string> $urls
 */
?>
    <section class="amendmentConflictResults" aria-labelledby="conflictResultsTitle">
        <h2 class="green" id="conflictResultsTitle"><?= Html::encode(Yii::t('motion', 'conflicts_results')) ?></h2>
        <div class="content">
            <?php foreach ($result['warnings'] as $id => $sections): ?>
                <p class="alert alert-warning"><?= Html::encode(str_replace(
                    ['%AMEND%', '%SECTIONS%'],
                    [$titles[$id], implode(', ', $sections)],
                    Yii::t('motion', 'conflicts_incomplete')
                )) ?></p>
            <?php endforeach; ?>
            <?php if (!$result['pairs']): ?>
                <p class="alert alert-info"><?= Html::encode(Yii::t('motion', 'conflicts_none')) ?></p>
            <?php endif; ?>
            <?php foreach ($result['pairs'] as $pair): ?>
                <article class="conflictPair" data-left="<?= $pair['left'] ?>" data-right="<?= $pair['right'] ?>">
                    <h3>
                        <?= Html::a(Html::encode($titles[$pair['left']]), $urls[$pair['left']]) ?>
                        ↔
                        <?= Html::a(Html::encode($titles[$pair['right']]), $urls[$pair['right']]) ?>
                    </h3>
                    <details>
                        <summary><?= Html::encode(Yii::t('motion', 'conflicts_details')) ?></summary>
                        <?php foreach ($pair['sections'] as $section): ?>
                            <h4><?= Html::encode($section['title']) ?></h4>
                            <?php foreach ($section['paragraphs'] as $paragraph): ?>
                                <?php if (isset($paragraph['lineFrom'], $paragraph['lineTo'])): ?>
                                    <p><?= Html::encode(str_replace(
                                        ['%LINEFROM%', '%LINETO%'],
                                        [$paragraph['lineFrom'], $paragraph['lineTo']],
                                        Yii::t('amend', 'merge1_changein_x')
                                    )) ?></p>
                                <?php endif; ?>
                                <?php foreach (['left' => 'motionNewDiff', 'right' => 'amendmentDiff'] as $side => $diffKey): ?>
                                    <h5><?= Html::encode($titles[$pair[$side]]) ?></h5>
                                    <div class="motionTextHolder"><div class="paragraph"><div class="text motionTextFormattings<?= $section['fixedWidth'] ? ' fixedWidthFont' : '' ?>">
                                        <?= $paragraph[$diffKey] ?>
                                    </div></div></div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
