<?php

namespace Symbio\WebtoolsBundle\Service;

use Goutte\Client;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler {
    const USER_AGENT_PARAM = 'user_agent';

    protected $baseUrl;
    protected $protocol;
    protected $host;

    protected $container;

    protected $logger;

    protected $parameters;

    protected $pages;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;

        $this->parameters = array(
            self::USER_AGENT_PARAM => $container->getParameter('symbio_webtools.'.self::USER_AGENT_PARAM),
        );
    }

    /**
     * crawl url and return pages
     * @param string $baseUrl URL to crawl
     * @return array
     */
    public function extractPages($baseUrl) {
        \error_reporting(E_ALL & ~E_NOTICE);

        // http protocol not included, prepend it to the base url
        if (strpos($baseUrl, 'http') === false) {
            $baseUrl = 'http://' . $baseUrl;
        }

        $this->baseUrl = $baseUrl;
        $this->protocol = substr($baseUrl, 0, strpos($baseUrl, ':'));

        $host = substr($baseUrl, strlen($this->protocol.'://'));
        $this->host = strpos($host, '/') !== false ? substr($host, 0, strpos($host, '/')) : $host;

        if ($this->baseUrl == $this->protocol.'://'.$this->host) {
            $this->baseUrl .= '/';
        }

        $this->pages = array();

        // initialize first element in the pages
        $this->pages[$this->baseUrl] = array(
            'links_text' => array('BASE_URL'),
            'absolute_url' => $this->baseUrl,
            'visited' => false,
            'external_link' => false,
            'original_urls' => array($this->baseUrl),
        );

        // crawl website into pages array
        $this->log(sprintf('Crawling started from "%s" at %s', $baseUrl, date('d.m.Y H:i:s')));

        $this->crawlPages($this->baseUrl, false);

        $this->log(sprintf('Crawling finished at %s', date('d.m.Y H:i:s')));

        return $this->pages;
    }

    /**
     * crawling single url after checking the depth value
     * @param string $url
     * @param int $depth
     */
    protected function crawlPages($url, $depth) {
        if (!$url || (isset($this->pages[$url]) && isset($this->pages[$url]['visited']) && $this->pages[$url]['visited'])) return;

        $client = new Client();
        $client->setHeader('User-Agent', $this->parameters['user_agent']);

        try {
            $call = $client->request('GET', $url);
            $statusCode = $client->getResponse()->getStatus();
            $this->log(sprintf("%s: %s", $statusCode, $url));
        } catch(\Exception $e) {
            $statusCode = 400;
            $this->log(sprintf("%s: %s", $statusCode, $url));
            $this->log(sprintf("Error page retrieving (%s)", $e->getMessage()));
        }

        if ($statusCode >= 400) {
            return;
        }

        if (!isset($this->pages[$url])) $this->pages[$url] = array();

        $this->pages[$url]['status_code'] = $statusCode;

        $contentType = $client->getResponse()->getHeader('Content-Type');
        if (strpos($contentType, ';') !== false) {
            $contentType = substr($contentType, 0, strpos($contentType, ';'));
        }

        switch($contentType) {
            case 'text/html':
                $this->pages[$url]['visited'] = true; // mark current url as visited
                if (!isset($this->pages[$url]['external_link']) || !$this->pages[$url]['external_link']) { // for internal uris, get all links inside
                    $links = $this->extractLinks($call, $url);
                    if (count($links)) {
                        $this->crawlChildLinks($links, $depth !== false ? $depth - 1 : false);
                    }
                }
                break;
        }
    }

    /**
     * extracting all <a> tags in the crawled document,
     * and return an array containing information about links like: uri, absolute_url, frequency in document
     * @param DomCrawler $dom
     * @param string $url
     * @return array
     */
    protected function extractLinks(DomCrawler &$dom, $ancestorUrl) {
        $links = array();

        $dom->filterXPath('html/body')->each(function(DomCrawler $node, $i) use (&$links) {
            $nodeText = trim($node->text());
            $nodeUrl = trim($node->attr('href'));

            if (strpos($nodeUrl, 'mailto:') !== false || strpos($nodeUrl, 'tel:') !== false || strpos($nodeUrl, 'phone:') !== false) return;

            $url = $this->normalizeLink($nodeUrl);

            if (!isset($this->pages[$url])) {
                if (!isset($links[$url])) {
                    $links[$url] = array(
                        'original_url' => array(),
                        'links_text' => array(),
                        'frequency' => 0,
                    );
                }

                $links[$url]['original_url'][$nodeUrl] = $nodeUrl;
                $links[$url]['links_text'][$nodeText] = $nodeText;

                if ($this->checkIfCrawlable($nodeUrl)) {
                    $links[$url]['absolute_url'] = $nodeUrl;
                    $links[$url]['external_link'] = $this->isPageExternal($url);
                } else {
                    $links[$url]['dont_visit'] = true;
                    $links[$url]['external_link'] = false;
                }

                $links[$url]['visited'] = false;
                $links[$url]['frequency']++;
            }
        });

        if (isset($links[$ancestorUrl])) { // if page is linked to itself, ex. homepage
            $links[$ancestorUrl]['visited'] = true; // avoid cyclic loop
        }

        return $links;
    }

    /**
     * after checking the depth limit of the links array passed
     * check if the link if the link is not visited/traversed yet, in order to traverse
     * @param array $links
     * @param int $depth
     */
    protected function crawlChildLinks($links, $depth) {
        if ($depth !== false && $depth == 0) return;

        foreach ($links as $url => $info) {
            if ($this->isPageExternal($url) || !$this->checkIfCrawlable($url)) continue;

            if (!isset($this->pages[$url])) {
                $this->pages[$url] = $info;
            } else {
                $this->pages[$url]['original_urls'] = isset($this->pages[$url]['original_urls'])?array_merge($this->pages[$url]['original_urls'], $info['original_urls']):$info['original_urls'];
                $this->pages[$url]['links_text'] = isset($this->pages[$url]['links_text'])?array_merge($this->pages[$url]['links_text'], $info['links_text']):$info['links_text'];
                if (@$this->pages[$url]['visited']) { //already visited link)
                    $this->pages[$url]['frequency'] = @$this->pages[$url]['frequency'] + @$info['frequency'];
                }
            }

            if (!$this->pages[$url]['visited'] && (!isset($this->pages[$url]['dont_visit']) || !$this->pages[$url]['dont_visit'])) { //traverse those that not visited yet
                $this->crawlPages($url, $depth);
            }
        }
    }

    /**
     * normalize link before visiting it
     * currently just remove url hash from the string
     * @param string $uri
     * @return string
     */
    protected function normalizeLink($url) {
        // URL without protocol
        if (strpos($url, '//') === 0) {
            $url = sprintf('%s:%s', $this->protocol, $url);
        }

        // replace doubled slashes
        $url = preg_replace('/([^:])\/\/(\S)/i', '$1/$2', $url);

        // remove anchor
        $url = preg_replace('/#.*$/', '', $url);

        // relative link
        if (!preg_match("/^http(s)?/", $url)) {
            $url = sprintf('%s://$s%s', $this->protocol, $this->host, $url);
        }

        return $url;
    }

    /**
     * check if the link leads to external site or not
     * @param string $url
     * @return boolean
     */
    protected function isPageExternal($url) {
        return preg_match("@^http(s)?@", $url) && strpos($url, $this->protocol.'://'.$this->host) !== 0;
    }

    /**
     * checks the uri if can be crawled or not
     * in order to prevent links like "javascript:void(0)" or "#something" from being crawled again
     * @param string $uri
     * @return boolean
     */
    protected function checkIfCrawlable($url) {
        if (empty($url)) return false;

        $stop_links = array(//returned deadlinks
            '@^javascript\:void\(0\)$@',
            '@^#.*@',
        );

        foreach ($stop_links as $ptrn) {
            if (preg_match($ptrn, $url)) {
                return false;
            }
        }

        return true;
    }

    /**
     * set logger
     * @param OutputInterface $logger
     */
    public function setLogger(OutputInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * log message
     * @param string $message
     * @param boolean $newLine Print new line at the message end
     */
    protected function log($message, $newLine = true) {
        if ($this->logger) {
            switch(get_class($this->logger)) {
                case 'Symfony\Component\Console\Output\BufferedOutput':
                case 'Symfony\Component\Console\Output\ConsoleOutput':
                    $this->logger->{$newLine?'writeln':'write'}($message);
                    break;
            }
        }
    }
}