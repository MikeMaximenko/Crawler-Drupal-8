<?php

namespace Drupal\crawler;

use Exception;

/**
 * Class for batch operations.
 */
class BatchOperations {

  /**
   * Main batch operation, using for parsing one site.
   */
  public function crawlerCallback($siteUrl, $category, $country, $language, &$context) {

    $emails = [];
    $drupalVersion = NULL;

    /* Create handler for Goutte/Client and check his available */
    $crawler = new CrawlerAPI($siteUrl);

    /* Redirect uri site on right path */
    $codeStatus = $crawler->redirectUrl();
    if ($codeStatus == '200') {
      $allUrls = [];
      $contact_urls = [];

      try {
        /* Looking for emails on base page */
        $emails = array_merge($emails, $crawler->findEmails(CrawlerAPI::$siteUrl));

        $contact_urls += $crawler->findContactUrl(CrawlerAPI::$siteUrl);
        if (!$contact_urls) {
          $crawler->parsePage(CrawlerAPI::$siteUrl, $allUrls);
          foreach ($allUrls as $url) {
            $contact_urls = array_merge($contact_urls, $crawler->findContactUrl($url));
          }
        }

        if ($contact_urls) {
          array_unique($contact_urls);
          foreach ($contact_urls as $contact_url) {
            $emails = array_merge($emails, $crawler->findEmails($contact_url));
          }
        }

      }
      catch (Exception $e) {
        $message = t('"@site" time exception error', [
          '@site' => CrawlerAPI::$siteUrl,
        ]);
        LogHandler::logError($message);
      }

      $drupalVersion = $crawler->checkDrupalVersion();
    }
    else {
      /* Logging of error if site's response don't have '200' code */
      if ($codeStatus) {
        $message = t('"@site" response with @codeStatus code', [
          '@site' => CrawlerAPI::$siteUrl,
          '@codeStatus' => $codeStatus,
        ]);
      }
      else {
        $message = t('"@site" does\'t response.', [
          '@site' => CrawlerAPI::$siteUrl,
        ]);
      }
      LogHandler::logError($message);
    }

    $crawler->createNode($emails, $codeStatus, $drupalVersion, $category, $country, $language);
    $context['message'] = 'ok';
  }

  /**
   * Final function for batch operation.
   */
  public static function crawlerFinishedCallback($success, $results) {
    if ($success) {
      $message = \Drupal::translation()->formatPlural(count($results),
        'One site parsed.',
        '@count sites parsed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
