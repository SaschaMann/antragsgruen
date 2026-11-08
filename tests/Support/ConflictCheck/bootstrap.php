<?php

// Minimal application for deterministic, database-free service benchmarks and tests.
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', dirname(__DIR__, 3));
new yii\web\Application([
    'id' => 'conflict-check',
    'basePath' => Yii::getAlias('@app'),
    'runtimePath' => sys_get_temp_dir() . '/antragsgruen-conflict-check',
    'language' => 'en',
    'components' => [
        'request' => ['cookieValidationKey' => 'benchmark-only', 'scriptUrl' => '/index.php', 'scriptFile' => __FILE__],
        'cache' => ['class' => yii\caching\ArrayCache::class],
        'i18n' => ['translations' => ['*' => [
            'class' => Tests\Support\ConflictCheck\MessageSource::class,
            'basePath' => '@app/messages',
            'sourceLanguage' => 'xx',
        ]]],
    ],
    'params' => new app\models\settings\AntragsgruenApp(['plugins' => []]),
]);
