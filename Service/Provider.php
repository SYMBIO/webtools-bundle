<?php

namespace Symbio\WebtoolsBundle\Service;

use Symbio\WebtoolsBundle\DependencyInjection\Configuration;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Provider extends BaseService
{
    protected $container;
    protected $crawler;

    /**
     * Provider constructor.
     * @param ContainerInterface $container
     * @param Crawler $crawler
     */
    public function __construct(ContainerInterface $container, Crawler $crawler)
    {
        $this->container = $container;
        $this->crawler = $crawler;
    }

    /**
     * Touch website links and images
     *
     * @param string $url Website URL
     * @param bool $printStatusCodes Print status codes to the output
     */
    public function touch($url, $printStatusCodes = false)
    {
        $crawlerParameters = $this->crawler->getParameters();

        $crawlerParameters[Crawler::EXTRACT_SELECTORS_PARAM] = array_merge(
            $crawlerParameters[Crawler::EXTRACT_SELECTORS_PARAM],
            array('html/body//img')
        );

        $this->crawler->setParameters($crawlerParameters);

        $pages = $this->crawler->extractPages($url, $printStatusCodes);

        if (!$pages) {
            $this->logError('Website touch: no pages crawled');
        }
    }

    /**
     * Generate website sitemap to defined output
     *
     * @param string $url Website URL
     * @param string $sitemapPath Absolute or relative path to sitemap.xml
     * @param bool $printStatusCodes Print status codes to the output
     */
    public function generateSitemap($url, $sitemapPath, $printStatusCodes = false)
    {
        $pages = $this->crawler->extractPages($url, $printStatusCodes);

        // generate XML
        if ($pages && is_array($pages) && count($pages)) {
            $this->log(sprintf('Generate sitemap to "%s"', $sitemapPath));

            $sitemapContent = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
';
            foreach($pages as $pageUrl => $pageInfo) {
                $sitemapContent .= sprintf("<url><loc>%s</loc></url>\r\n", $pageUrl);
            }
            $sitemapContent .= '</urlset>';

            // store XML
            file_put_contents($sitemapPath, $sitemapContent);

            $this->log(sprintf('Generating finished at %s', date('d.m.Y H:i:s')));
        } else {
            $this->logError('Sitemap generate: no pages crawled');
        }
    }

    /**
     * Scrape website URLs on Facebook
     *
     * @param string $url Website URL
     * @param bool $printStatusCodes Print status codes to the output
     * @return array
     */
    public function facebookScrape($url, $printStatusCodes = false)
    {
        $pages = $this->crawler->extractPages($url, $printStatusCodes);

        $scrapedPages = array();

        // generate XML
        if ($pages && is_array($pages) && count($pages)) {
            $this->log('Scrape pages...');

            $token = strtr(file_get_contents(sprintf(
                'https://graph.facebook.com/oauth/access_token?type=client_cred&client_id=%s&client_secret=%s',
                $this->container->getParameter('symbio_webtools.'.Configuration::FACEBOOK_CLIENT_ID_PARAM),
                $this->container->getParameter('symbio_webtools.'.Configuration::FACEBOOK_CLIENT_SECRET_PARAM)
            )), array('access_token=' => ''));

            if ($token) {
                foreach($pages as $pageUrl => $pageInfo) {
                    if ($pageInfo['content_type'] == 'text/html') {
                        $ch = curl_init();
                        $curlParams = array(
                            CURLOPT_URL            => 'https://graph.facebook.com',
                            CURLOPT_FOLLOWLOCATION => 1,
                            CURLOPT_POST           => 1,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_MAXREDIRS      => 3,
                            CURLOPT_POSTFIELDS     => http_build_query(array(
                                'id' => $pageUrl,
                                'scrape' => true,
                                'access_token' => $token
                            )),
                            CURLOPT_RETURNTRANSFER => 1,
                        );
                        curl_setopt_array($ch, $curlParams);

                        $result = curl_exec($ch);
                        curl_close($ch);

                        $response = @json_decode($result);
                        $success = $response && is_object($response) && $response->id && isset($response->title) && $response->title;

                        $scrapedPages[$pageUrl] = array(
                            'success' => $success,
                            'result' => $response
                        );

                        $this->log(sprintf('%s: %s', $success ? 'ok' : 'KO', $pageUrl));
                    }
                }
            } else {
                $this->logError('Facebook scrape: access token didn\'t fetched');
            }

            $this->log(sprintf('Scraping finished at %s', date('d.m.Y H:i:s')));
        } else {
            $this->logError('Facebook scrape: no pages crawled');
        }

        return $scrapedPages;
    }

    /**
     * @param OutputInterface $logger
     */
    public function setLogger(OutputInterface $logger) {
        $this->logger = $logger;
        $this->crawler->setLogger($logger);
    }
}