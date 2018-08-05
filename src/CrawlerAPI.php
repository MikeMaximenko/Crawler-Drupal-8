<?php

namespace Drupal\crawler;

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Drupal\content_entity_example\Entity\Contact;
use Exception;

/**
 * API for using GuzzleHttp module.
 */
class CrawlerAPI {
  /**
   * Goutte client which using for parsing sites.
   *
   * @var client
   */
  private $client;

  /**
   * Variable depth of search.
   *
   * @var depth
   */
  private $depth;

  /**
   * Page limit for parsing.
   *
   * @var parsePageLimit
   */
  private $parsePageLimit;

  /**
   * Site url.
   *
   * @var siteUrl
   */
  private static $siteUrl;

  /**
   * Default time limit of execute.
   *
   * @var timeExecuteException
   */
  private $timeExecuteException = 5;

  /**
   * Default time limit for search, triggered after $timeExecuteException.
   *
   * @var timeParseEmails
   */
  private $timeParseEmails = 5;

  /**
   * Timestamp of start batch operation.
   *
   * @var timeStamp
   */
  private $timeStamp;

  /**
   * Crawler module logger.
   *
   * @var logHandler
   */
  private $logHandler;

  /**
   * Number of parsed pages.
   *
   * @var passPages
   */
  private $passPages;

  /**
   * Redirecting entered url.
   *
   * @param string $siteUrl
   *   Site's url.
   */
  public function __construct($siteUrl) {
    self::$siteUrl = $siteUrl;
    $config = \Drupal::config('crawler.settings');
    $this->depth = $config->get('depthSearch');
    $this->parsePageLimit = $config->get('parsePageLimit');

    /* Set up Goutte/Client settings */
    $goutteClient = new Client();
    $goutteClient->setMaxRedirects(10);
    $goutteClient->insulate(FALSE);

    /* Set up Guzzle/Client settings */
    $guzzleClient = new GuzzleClient([
      'allow_redirects' => TRUE,
      'timeout' => 20,
    ]);

    /* Create Guzzle/Client inside Goutte/Client */
    $goutteClient->setClient($guzzleClient);

    $this->client = $goutteClient;

    $this->timeStamp = time();

    $this->logHandler = new LogHandler();

    $this->passPages = 0;
  }

  /**
   * Check site connection and redirect main url.
   *
   * @return bool
   *   Code of status.
   */
  public function redirectUrl() {
    LogHandler::logInit();
    $scheme = parse_url(self::$siteUrl, PHP_URL_SCHEME);
    $hostname = parse_url(self::$siteUrl, PHP_URL_HOST);

    $old_url = $scheme . '://' . $hostname;
    $codeStatus = NULL;
    $newUrl = NULL;
    try {
      $newUrl = $this->client->request('GET', $old_url);
      $newUrl = $newUrl->getUri();

      if ($old_url != $newUrl) {

        $message = t('"@old_url" now redirect on "@newUrl"', [
          '@newUrl' => $newUrl,
          '@old_url' => self::$siteUrl,
        ]);
        LogHandler::log($message);

        self::$siteUrl = $newUrl;
      }
      $response = $this->client->getResponse();
      $codeStatus = $response->getStatus();

    }
    catch (Exception $e) {
      $message = t('"@site" don\'t response. or connection too long. or reach max redirection', [
        '@site' => self::$siteUrl,
      ]);
      LogHandler::logError($message);
    }
    return $codeStatus;
  }

  /**
   * Parsing all pages.
   *
   * @param string $url
   *   Array all links from front site page.
   * @param array $allUrls
   *   Passing urls.
   *
   * @return array
   *   Array of contact urls.
   */
  public function parsePage($url, array &$allUrls) {
    /* Local variable for check deep of parsing */
    static $tempParsePage = 0;

    $newUrls = $this->getAllLinksUrl($url);
    $allUrls = array_merge($allUrls, $newUrls);

    if ($tempParsePage + 1 < $this->depth) {

      foreach ($newUrls as $newUrl) {
        $tempParsePage++;
        $this->parsePage($newUrl, $allUrls);

        if ($this->parsePageLimit <= count($allUrls)) {
          $allUrls = array_slice($allUrls, 0, $this->parsePageLimit);
          return NULL;
        }

        if (time() - $this->timeStamp > $this->timeExecuteException) {
          return NULL;
        }
      }
    }
    return NULL;
  }

  /**
   * Finds all links on the given site that have same base URL.
   *
   * @param string $url
   *   Verifiable URI.
   *
   * @throws Exception
   *
   * @return array
   *   Array of link's URL.
   */
  public function getAllLinksUrl($url) {
    try {
      $crawler = $this->client->request('GET', $url);
    }
    catch (Exception $e) {
      LogHandler::logError('getAllLinksUrl error');
      throw new Exception();
    }

    $allLinks = $crawler->filter("a")->links();
    $links = [];

    foreach ($allLinks as $link) {
      $linkUri = $link->getUri();

      if (stristr($linkUri, "#")) {
        $linkUri = stristr($linkUri, "#", TRUE);
      }

      if (strstr($linkUri, self::$siteUrl)) {
        $links[] = $linkUri;
      }
    }

    return array_unique($links);
  }

  /**
   * Get all Emails from the given url.
   *
   * @param string $url
   *   Verifiable URI.
   *
   * @throws Exception
   *
   * @return array
   *   Array of emails.
   */
  public function findEmails($url) {

    if (time() - $this->timeStamp > $this->timeExecuteException + $this->timeParseEmails) {
      return NULL;
    }

    try {
      $this->client->request('GET', $url);
    }
    catch (Exception $e) {
      LogHandler::logError('getEmails error');
      throw $e;
    }

    $crawler = $this->client->getCrawler();

    $allHref = $crawler->filter('a')->each(function ($node) {
      return $node->attr('href');
    });
    $pattern = "/^(mailto:).*/";
    $result = preg_grep($pattern, $allHref);

    $emails = [];
    foreach ($result as $href) {
      $emails[] = str_replace('mailto:', '', $href);
    }

    $pattern = '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD';
    $emails = preg_grep($pattern, $emails);

    $page = $this->client->getCrawler()->text();

    /* Split html in array by any symbol (except "." and "@")*/
    $splitedPageText = preg_split("/[(\?\|\\\,\"\;\:\№\(\)\[\]\<\>)*\s,]+/", $page);
    $splitedPageText = array_map("StrToLower", $splitedPageText);

    /* Looking for emails in array */

    $emails = array_merge($emails, preg_grep($pattern, $splitedPageText));
    $emails = array_unique($emails);

    return $emails;
  }

  /**
   * Finds all links that contain 'contact' text (case insensitive).
   *
   * @param string $url
   *   Uri on which looking for contact's uri.
   *
   * @throws Exception
   *
   * @return array
   *   Array of link's URL.
   */
  public function findContactUrl($url) {
    if (time() - $this->timeStamp > $this->timeExecuteException) {
      return NULL;
    }
    try {
      $crawler = $this->client->request('GET', $url);
    }
    catch (Exception $e) {
      LogHandler::logError('getContactUrl error');
      throw new Exception();
    }
    $contactLinks = $crawler->filter("a")->links();

    $links = [];
    foreach ($contactLinks as $link) {
      $menuLink = $link->getNode()->nodeValue;
      if (preg_grep("/сontact/i", [$menuLink]) || preg_grep("/contact/i", [$link->getUri()])) {
        $linkUri = $link->getUri() . '/';
        if (strstr($linkUri, self::$siteUrl)) {
          $links[] = $linkUri;
        }
      }
    }
    $this->passPages++;
    return array_unique($links);
  }

  /**
   * Check drupal site version.
   *
   * @return string
   *   Drupal version.
   */
  public function checkDrupalVersion() {
    $pattern = "/(Drupal)+\s*[0-9]*(\.[0-9]{2}|\s)/";
    $site = self::$siteUrl;
    if (substr($site, -1) != '/') {
      $site = $site . '/';
    }
    $version = NULL;

    /*First try find drupal version is check CHANGELOG.txt page*/
    try {
      $this->client->request('GET', $site . 'CHANGELOG.txt');
      $response = $this->client->getResponse()->getContent();
      preg_match($pattern, $response, $result);
      $version = $result[0];
    }
    catch (Exception $e) {
      LogHandler::log('First try find drupal version was ended with error');
    }
    if (!$version) {
      /*Second try it is check metadata in head request*/
      try {
        $this->client->request('HEAD', $site);
        $header = $this->client->getResponse()->getHeaders();
        if ($header['X-Generator'][0]) {
          preg_match($pattern, $header['X-Generator'][0], $result);
          $version = $result[0];
        }
      }
      catch (Exception $e) {
        LogHandler::log('Second try find drupal version was ended with error');
      }
    }
    if (!$version) {
      try {
        $this->client->request('HEAD', $site);
        ksm($this->client);
      }
      catch (Exception $e) {
        LogHandler::log('Second try find drupal version was ended with error');
      }
    }
    if (!$version) {
      LogHandler::log('Drupal version are did not find');
    }

    return $version;
  }

  /**
   * Create nodes with searching information about site.
   *
   * @param array $emails
   *   Set emails by site.
   * @param string $available
   *   Site status code.
   * @param string $drupalVersion
   *   Site's drupal version.
   * @param string $category
   *   Category of site.
   * @param string $country
   *   Country of site.
   * @param string $language
   *   Language of site.
   */
  public function createNode(array $emails, $available, $drupalVersion, $category, $country, $language) {
    $emails = array_unique($emails);
    $emails = implode('; ', $emails);
    $parseTime = time() - $this->timeStamp;
    $minorVersion = NULL;

    $result = preg_split('/[\s]|[.]/', $drupalVersion);

    $majorVersion = $result[1];
    if ($result[2]) {
      $minorVersion = $result[2];
    };

    $contact = Contact::create([
      'site_name' => self::$siteUrl,
      'category' => $category,
      'code' => $available,
      'emails' => $emails,
      'majorVersion' => $majorVersion,
      'minorVersion' => $minorVersion,
      'passPages' => $this->passPages,
      'execute_time' => $parseTime,
      'country' => $country,
      'language' => $language,
    ]);

    $message = t('"@uri" was created with @status code', [
      '@uri' => self::$siteUrl,
      '@status' => $available,
    ]);
    LogHandler::log($message);

    $contact->save();
  }

}
