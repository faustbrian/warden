<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\CodingStandard\Rector\Factory;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\DeadCode\Rector\MethodCall\RemoveNullArgOnNullDefaultParamRector;
use Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromBooleanStrictReturnsRector;

return Factory::create(
    paths: [__DIR__.'/src', __DIR__.'/tests'],
    skip: [
        NewlineBetweenClassLikeStmtsRector::class,
        RemoveUnreachableStatementRector::class => [__DIR__.'/tests'],
        BoolReturnTypeFromBooleanStrictReturnsRector::class => [__DIR__.'/tests/BeforePoliciesTest.php'],
        RemoveNullArgOnNullDefaultParamRector::class => [__DIR__.'/tests/BoundaryScopedPermissionsTest.php'],
    ],
);
