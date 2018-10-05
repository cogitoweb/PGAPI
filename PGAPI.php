<?php

namespace PGAPI;

require_once('vendor/autoload.php');

use DOMDocument;
use DOMXPath;

class crawler {
    protected $base_url = "https://www.paginegialle.it/ricerca/";
    protected $query = "";
    protected $url = "";
    protected $nodexpath = "//section[contains(@class,\"vcard\")]";
    protected $namexpath = ".//h1[contains(@itemprop,\"name\")]/a";
    protected $descriptionxpath = ".//div[@itemprop=\"description\"]";
    protected $imagexpath = ".//img[@itemprop=\"image\"]";
    protected $addressxpath = ".//span[contains(@class,\"street-address\")]";
    protected $postalcodexpath = ".//span[contains(@class,\"postal-code\")]";
    protected $localityxpath = ".//span[contains(@class,\"locality\")]";
    protected $regionxpath = ".//span[contains(@class,\"region\")]";
    protected $statexpath = NULL;
    protected $telephonexpath = ".//span[contains(@class,\"phone-label\")]";
    protected $websitexpath = ".//a[contains(@class,\"icn-sitoWeb\")]/@href";
    protected $categoryxpath = ".//a[@class=\"cat\"]";
    protected $categorynumberxpath = ".//a[@class=\"cat\"]/@href";
    protected $pagination = ".//a[@class=\"paginationBtn arrowBtn rightArrowBtn\"]";
    protected $nodi;
    protected $length = 0;
    protected $page;
    protected $risultato = array();

    public function __construct($query, $page = 1) {
        $this->page = $page;
        $query = trim(str_replace(' ', '%20', $query)) . '/p-' . $this->page;;
        $this->query = $query;

        $this->url = $this->base_url . $query;
    }

    private function getContents($sURL) {

        $sCookie = $this->UpdateCookies(); // Prepare cookies for deliverance

        $aHTTP['http']['method'] = 'GET';
        $aHTTP['http']['header'] = "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36\r\n";
        $aHTTP['http']['header'] .= "Referer: " . $this->url . "\r\n";
        $aHTTP['http']['header'] .= "Content-Type: text/html; charset=utf-8\r\n";
        if ($sCookie !== 0) { // Send cookies back to server (if any)
            $aHTTP['http']['header'] .= "Cookie: $sCookie\r\n";
        }
        $aHTTP['http']['follow_location'] = false;
        $context = stream_context_create($aHTTP);
        $html = mb_convert_encoding(file_get_contents($sURL, false, $context), "HTML-ENTITIES", "UTF-8"); // Send the Request
        $ResponseHeaders = $http_response_header;
        $this->SaveCookies($ResponseHeaders); // Saves cookies to cookie directory (if any).
        return $html;
    }

    private function UpdateCookies() {
        $aCookies = glob(__DIR__ . "/cookie_*.txt");
        $n = count($aCookies);
        $sCombined = "";
        if ($n !== 0) {
            $counter = 0;

            while ($counter < $n) {
                $sCombined .= file_get_contents($aCookies["$counter"]) . ';';
                ++$counter;
            }
            return $sCombined;
        } else {
            return $n;
        }
    }

    private function SaveCookies($aRH) {
        $n = count($aRH); // Number of Pieces
        $counter = 0;
        while ($counter + 1 <= $n) {
            if (preg_match('@Set-Cookie: (([^=]+)=[^;]+)@i', $aRH["$counter"], $matches)) {
                $fp = fopen(__DIR__ . 'cookie_' . $matches["2"] . '.txt', 'w');
                fwrite($fp, $matches["1"]);
                fclose($fp);
            }
            ++$counter;
        }
    }

    private function getResult() {
        $html = $this->getContents($this->url);
        $tempLength = 0;

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        if ($xpath->query("//meta[contains(@http-equiv,'refresh')]")->length == 1) {
            $this->risultato["status"] = "ERROR";
            $this->risultato["errorDescription"] = "The server refused the connection for the amount of request you've made.";
        } else {
            $this->nodi = $xpath->query($this->nodexpath);
            foreach ($this->nodi as $row) {
                $tempLength++;

                $this->risultato["result"][$this->length]["name"] = (($xpath->query($this->namexpath, $row)->length > 0) ? trim($xpath->query($this->namexpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["description"] = (($xpath->query($this->descriptionxpath, $row)->length > 0) ? trim($xpath->query($this->descriptionxpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["image"] = (($xpath->query($this->imagexpath, $row)->length > 0) ? trim($xpath->query($this->imagexpath, $row)[0]->getAttribute('src')) : NULL);
                $this->risultato["result"][$this->length]["place"]["address"] = (($xpath->query($this->addressxpath, $row)->length > 0) ? trim($xpath->query($this->addressxpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["place"]["postal-code"] = (($xpath->query($this->postalcodexpath, $row)->length > 0) ? trim($xpath->query($this->postalcodexpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["place"]["locality"] = (($xpath->query($this->localityxpath, $row)->length > 0) ? trim($xpath->query($this->localityxpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["place"]["region"] = (($xpath->query($this->regionxpath, $row)->length > 0) ? trim($xpath->query($this->regionxpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["place"]["state"] = "IT";
                $this->risultato["result"][$this->length]["telephone"] = (($xpath->query($this->telephonexpath, $row)->length > 0) ? trim($xpath->query($this->telephonexpath, $row)[0]->nodeValue) : NULL);
                $this->risultato["result"][$this->length]["website"] = (($xpath->query($this->websitexpath, $row)->length > 0) ? trim($xpath->query($this->websitexpath, $row)[0]->nodeValue) : NULL);
                $category = $xpath->query($this->categoryxpath, $row)[0];
                $this->risultato["result"][$this->length]["category"] = trim($category->nodeValue);
                $this->risultato["result"][$this->length]["category-number"] = substr($category->getAttribute('href'), strpos($category->getAttribute('href'), '/cat/') + 5);

                $this->length++;
            }
            $this->risultato["status"] = "OK";
        }
        $this->risultato["length"] = $tempLength;
        $this->risultato["totalLength"] = $this->le;
        $this->risultato["source"] = $this->url;
        $this->risultato["query"] = $this->query;

        return $this->risultato;
    }

    public function getSinglePageResults() {
        return json_encode($this->getResult(), JSON_PRETTY_PRINT);
    }

    public function getAllResults() {
        $jsonResults = '';

        while (true) {
            $pageResults = $this->getResult();

            if ($this->page < 10) {
                $this->url = substr($this->url, 0, -1) . ++$this->page;
            } else if ($this->page < 100) {
                $this->url = substr($this->url, 0, -2) . ++$this->page;
            } else {
                $this->url = substr($this->url, 0, -3) . ++$this->page;
            }

            if ($pageResults['length'] === 0) {
                unset($pageResults['length']);
                $jsonResults = $pageResults;
                break;
            }
        }

        return json_encode($jsonResults, JSON_PRETTY_PRINT);
    }

    public static function search($query, $where = null, $page = 1) {
        $query = $query . ($where ? "/$where" : '');
        $service = new static($query, $page);
        return $service;
    }
}