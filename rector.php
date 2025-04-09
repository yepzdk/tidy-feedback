<?php

/**
 * @file
 */

declare(strict_types=1);

use DrupalRector\Set\Drupal10SetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
  ->withPaths([
    __DIR__ . '/src',
  ])
  ->withSets([
    Drupal10SetList::DRUPAL_10,
  ])
  ->withPhpSets(php83: TRUE)
  ->withTypeCoverageLevel(0);
