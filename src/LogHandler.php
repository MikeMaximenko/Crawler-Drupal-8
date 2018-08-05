<?php

namespace Drupal\crawler;

use Drupal\crawler\Entity\CrawlerLog;

/**
 * Debugging class.
 */
class LogHandler {

  /**
   * Inheritdoc.
   */
  public static function logInit() {
    $message = 'Init log.';
    CrawlerLog::create([
      'site' => CrawlerAPI::$siteUrl,
      'message' => $message,
      'status' => 'INIT',
    ])->save();
  }

  /**
   * Inheritdoc.
   */
  public static function log($message) {
    CrawlerLog::create([
      'site' => CrawlerAPI::$siteUrl,
      'message' => $message,
      'status' => 'OK',
    ])->save();
  }

  /**
   * Inheritdoc.
   */
  public static function logError($message) {
    CrawlerLog::create([
      'site' => CrawlerAPI::$siteUrl,
      'message' => $message,
      'status' => 'ERROR',
    ])->save();
  }

}
