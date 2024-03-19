<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use SlevomatCodingStandard\Sniffs\Commenting\InlineDocCommentDeclarationSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests/Behat',
        __DIR__ . '/ecs.php',
    ]);

    $config->import('vendor/sylius-labs/coding-standard/ecs.php');

    $config->skip([
        VisibilityRequiredFixer::class => ['*Spec.php'],
        InlineDocCommentDeclarationSniff::class . '.MissingVariable',
    ]);
};
