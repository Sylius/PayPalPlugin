<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSeparationFixer;
use SlevomatCodingStandard\Sniffs\Commenting\InlineDocCommentDeclarationSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->paths([
        'src',
        'spec',
        'tests/Behat',
        'tests/Functional',
        'tests/Service',
        'tests/Unit',
    ]);

    $config->import('vendor/sylius-labs/coding-standard/ecs.php');
    $config->ruleWithConfiguration(PhpdocSeparationFixer::class, ['groups' => [['ORM\\*'], ['Given', 'When', 'Then']]]);

    $config->skip([
        VisibilityRequiredFixer::class => ['*Spec.php'],
        InlineDocCommentDeclarationSniff::class . '.MissingVariable',
    ]);
};
