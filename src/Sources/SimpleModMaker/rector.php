<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '*',
	])
	->withSkip([
		__DIR__ . '/vendor/*',
		RecastingRemovalRector::class,
	])
	->withParallel(360)
	->withIndent(indentChar: "\t")
	->withImportNames(removeUnusedImports: true)
	->withPreparedSets(deadCode: true)
	->withPhpSets();
