<?php

require_once "easyrdf-0.8.0/lib/EasyRdf.php";


class Scraper {

    private $url;
    private $resolvedUrl;
    private $urlType;
    private $label;
    private $comment;
    private $format;
    private $annotableVersionAt;
    private $aggregatedCHO;
    private $domain;
    private $punditContent;
    private $prev = false;
    private $next = false;
    private $dm2ePrev = false;
    private $dm2eNext = false;
    private $stylesheet = false;
    private $dm2eGraph;

    /**
     * Public contructor set URL to be scraped
     * TODO: verify if the url is valid
     * TODO: Test
     * @param type $url 
     */
    public function __construct($url, $urlType=NULL) {
        $this->url = $url;
        $this->resolvedUrl = $this->getFinalUrl($url);

        if (!$this->isUrlValid())
            throw new Exception('Url is not VALID');
        $this->setUrlType($urlType);
        $this->retrievePunditContent();

    }

    public function getFinalUrl($url) {
        $furl = false;
        // First check response headers
        $headers = get_headers($url);
        // Test for 301, 302 or 303
        if(preg_match('/^HTTP\/\d\.\d\s+(301|302|303)/',$headers[0])) {
            foreach($headers as $value) {
                if(substr(strtolower($value), 0, 9) == "location:") {
                    $furl = trim(substr($value, 9, strlen($value)));
                }
            }
        }
        // Set final URL
        $furl = ($furl) ? $furl : $url;
        return $furl;
    }

    private function setUrlType($urlType) {
        $allowed_types = array ('default', 'img', 'dm2e');
        if($urlType==null) $this->urlType = 'default';
        else if (in_array($urlType, $allowed_types))
            $this->urlType=$urlType;
        else throw new Exception('Url Type '.$urlType.' Not allowed');
    }
    
    public function getPunditContent() {
        return $this->punditContent;
    }

    public function getLabel() {
        return $this->label;
    }
    public function getNext($pos = null) {
        $s = $_SERVER['HTTP_HOST'];
        
        if (!$this->next && !$this->dm2eNext)
            return false;
        
        // Single page annotation
        if (isset($_REQUEST['dm2e'])) {
            return 'http://'. $s .'/?dm2e='. $this->dm2eNext .'&conf='. $_REQUEST['conf'];
        } else if (!isset($_REQUEST['lurl']) && !isset($_REQUEST['rurl'])) {
            return 'http://'. $s .'/?url='. $this->next .'&conf='. $_REQUEST['conf'];

        } else if ($pos == "left") {
            return 'http://'. $s .'/?lurl='. $this->next .'&rurl='. $_REQUEST['rurl'] .'&conf='. $_REQUEST['conf'];
        } else if ($pos == "right") {
            return 'http://'. $s .'/?rurl='. $this->next .'&lurl='. $_REQUEST['lurl'] .'&conf='. $_REQUEST['conf'];
        }
    }
    
    public function getPrev($pos = null) {
        $s = $_SERVER['HTTP_HOST'];
        
        if (!$this->prev && !$this->dm2ePrev)
            return false;
        
        // Single page annotation
        if (isset($_REQUEST['dm2e'])) {
            return 'http://'. $s .'/?dm2e='. $this->dm2ePrev .'&conf='. $_REQUEST['conf'];
        } else if (!isset($_REQUEST['lurl']) && !isset($_REQUEST['rurl'])) {
            return 'http://'. $s .'/?url='. $this->prev .'&conf='. $_REQUEST['conf'];
        } else if ($pos == "left") {
            return 'http://'. $s .'/?lurl='. $this->prev .'&rurl='. $_REQUEST['rurl'] .'&conf='. $_REQUEST['conf'];
        } else if ($pos == "right") {
            return 'http://'. $s .'/?rurl='. $this->prev .'&lurl='. $_REQUEST['lurl'] .'&conf='. $_REQUEST['conf'];
        }
    }
    public function getStylesheet() {
        return $this->stylesheet;
    }
    
    public function getComment() {
        return $this->comment;
    }
    public function getAnnotableVersionAt() {
        return $this->annotableVersionAt;
    }
    public function getDomain() {
        return $this->domain;
    }


    private function doCurlRequest($contentType, $requestUrl='') {

        if ($requestUrl == '')
            $requestUrl = $this->url;

        $requestUrl = $this->getFinalUrl($requestUrl);

        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $requestUrl);

        // TODO: shouldnt it be "Accept" instead of "Content-type" ??!
        // curl_setopt($request, CURLOPT_HTTPHEADER, array("Content-Type: {$contentType}"));
        curl_setopt($request, CURLOPT_HTTPHEADER, array("Accept: {$contentType}"));
        curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, 1);

        $response = curl_exec($request);
        $error = curl_error($request);
        $http_code = curl_getinfo($request, CURLINFO_HTTP_CODE);

        if (!curl_errno($request)) {
            $result = $response;
        } else {
            $result = $error;
        }

        curl_close($request);

        return $result;
    }

    /**
     * Retrieve pundit content through the content neg
     * // TODO: ALL!!!!!
     */
    private function retrievePunditContent() {
        switch ($this->urlType) {
            case 'default':
                $this->retrievePunditContentDefault();
                break;
            case 'img':
                $this->retrievePunditContentImg();
                break;
            case 'dm2e':
                $this->retrievePunditContentDm2e();
                break;
            default:
                throw new Exception('Url type '.$this->urlType.' not supported.');
                break;
        }
    }

    private function retrievePunditContentDm2e() {
        
        $url = $this->url;
        $this->punditContent = $url;

        $this->dm2eGraph = EasyRdf_Graph::newAndLoad($url);

        EasyRdf_Namespace::set('edm','http://www.europeana.eu/schemas/edm/');
        EasyRdf_Namespace::set('dm2e','http://onto.dm2e.eu/schemas/dm2e/1.1/');
        $this->nsDc = EasyRdf_Namespace::prefixOfUri('http://purl.org/dc/elements/1.1/');
        $this->nsDct = EasyRdf_Namespace::prefixOfUri('http://purl.org/dc/terms/');



        $this->annotableVersionAt = $this->extractDm2eAnnotableVersionByDom();
        $this->aggregatedCHO = $this->extractDm2eAggregatedCHO();
        $this->format = $this->extractDm2eAnnotableFormatByDom();
        $this->label = $this->extractDm2eTitleByDom();
        $this->comment = $this->extractDm2eCommentByDom();
        $this->dm2eNext = $this->extractDm2eNextByDom();
        $this->dm2ePrev = $this->extractDm2ePrevByDom();

        /*  TODO uncomment this
        $rdf = $this->doCurlRequest('application/rdf+xml');
        $dom = new DOMDocument();
        $dom->loadXML($rdf);


        $this->aggregatedCHO = $this->extractDm2eAggregatedCHO($dom);
        $this->annotableVersionAt = $this->extractDm2eAnnotableVersionByDom($dom);
        $this->format = $this->extractDm2eAnnotableFormatByDom($dom);
        $this->label = $this->extractDm2eTitleByDom($dom);
        $this->comment = $this->extractDm2eCommentByDom($dom);
        $this->dm2eNext = $this->extractDm2eNextByDom($dom);
        $this->dm2ePrev = $this->extractDm2ePrevByDom($dom);

        */
        // TODO: get next and prev, comment?
        // http://lelystad.informatik.uni-mannheim.de:3000/direct/html/ingested/item/onb/codices/AL00070711-31
        
        /*
        // TODO: debug what is happening with this:
        $this->punditContent = 
            "url = ". $url . "<br/>" .
            "CHO = ".$this->aggregatedCHO . "<br/>" .
            "Annotable = ".$this->annotableVersionAt . "<br/>" .
            "Title = ".$this->label . "<br/>" .
            "Comment = ".$this->comment . "<br/>" .
            "Format = ".$this->format . "<br/>" .
            "Next = ".$this->next . "<br/>" .
            "Prev = ".$this->prev . "<br/>" .
            "<pre>". htmlentities($rdf) . "<pre><br/>";
        */
        
        // TODO: get the type of the resource from the RDF, and not with a string match!
        if ($this->format == "image/jpeg") {
            $this->punditContent = '
               <div class="pundit-content" about="'.$this->url.'">
                 <div class="pundit-content" about="'.$this->annotableVersionAt.'">
                   <img src="'.$this->annotableVersionAt.'" class="annotable-image" />
                 </div>
               </div>
             ';
        } else {
            $this->punditContent = 'TODO: get the content from ' . $this->annotableVersionAt;
        }

    }
    
    private function retrievePunditContentDefault() {
        $rdf = $this->doCurlRequest('application/rdf+xml');
        $dom = new DOMDocument();
        $dom->loadXML($rdf);

        $this->label = $this->extractLabelByDom($dom);
        $this->next = $this->extractNextResourceByDom($dom);
        $this->prev = $this->extractPrevResourceByDom($dom);
        $this->stylesheet = $this->extractStylesheetByDom($dom);

        $this->comment = $this->extractCommentByDom($dom);
        $this->annotableVersionAt = $this->extractAnnotableVersionByDom($dom);        
        
        $url_info = parse_url($this->annotableVersionAt);
        $this->domain=$url_info['host'];
        // We assume that there is only html and body element and only one pundit content
        $content = $this->doCurlRequest('text/html', $this->annotableVersionAt);
        $content = preg_replace('@<body>@', '', $content);
        $content = preg_replace('@</body>@', '', $content);
        $content = preg_replace('@<html>@', '', $content);
        $content = preg_replace('@</html>@', '', $content);
        $this->punditContent = $content;
    }

    private function retrievePunditContentImg() {
        $this->label = 'Web image : '.$this->url;
        $this->comment = 'Single image pundit annotator';
        $url_info = parse_url($this->url);
        $this->domain = $url_info['host'];
        $this->punditContent = '
           <div class="pundit-content" about="'.$this->url.'">
           <img src="'.$this->url.'" />
           </div>
         ';
    }

    // TODO: use namespace rdfs::label
    private function extractLabelByDom(DOMDocument $dom) {
        return $this->extractTagValueByDom($dom, 'label');
    }

    private function extractDm2eTitleByDom() {
        return $this->dm2eGraph->get($this->url, $this->nsDct . ':title');
    }

    private function extractDm2eCommentByDom() {
        return $this->dm2eGraph->get($this->url, $this->nsDct . ':description');
    }
    
    private function extractCommentByDom(DOMDocument $dom) {
        return $this->extractTagValueByDom($dom, 'comment');
    }

    private function extractDm2eAnnotableVersionByDom() {
        $aggs = $this->dm2eGraph->resourcesMatching('edm:aggregatedCHO');

        return  $aggs[0]->get('dm2e:hasAnnotatableVersionAt');
    }

    private function extractDm2eAnnotableFormatByDom() {
        $aggs = $this->dm2eGraph->resourcesMatching('edm:aggregatedCHO');
        $annotableVersion = $aggs[0]->get('dm2e:hasAnnotatableVersionAt');

        return $annotableVersion->get($this->nsDc . ':format');
    }

    private function extractDm2eAggregatedCHO() {
        $aggs = $this->dm2eGraph->resourcesMatching('edm:aggregatedCHO');

        return $aggs[0];
    }
    
    private function extractDm2eNextByDom() {
        $next = false;
        $nexts = $this->dm2eGraph->resourcesMatching('edm:isNextInSequence');

        foreach ($nexts as $n) {
            if ($n != $this->url) {
                $next = $n;
                break;
            }
        }
        return $next;
    }

    private function extractDm2ePrevByDom() {
        return $this->dm2eGraph->get($this->url, 'edm:isNextInSequence');
    }

    
    private function extractAnnotableVersionByDom(DOMDocument $dom) {
        return $this->extractAttributeValueByDom($dom, 'hasAnnotableVersionAt', 'rdf:resource');
    }
    private function extractNextResourceByDom(DOMDocument $dom) {
        return $this->extractAttributeValueByDom($dom, 'nextResource', 'rdf:resource');
    }
    private function extractPrevResourceByDom(DOMDocument $dom) {
        return $this->extractAttributeValueByDom($dom, 'prevResource', 'rdf:resource');
    }
    private function extractStylesheetByDom(DOMDocument $dom) {
        return $this->extractAttributeValueByDom($dom, 'hasStyleSheet', 'rdf:resource');
    }
    
    private function extractTagValueByDom(DOMDocument $dom, $tagname) {
        $values = $dom->getElementsByTagName($tagname);
        $val='';
        foreach ($values as $value){
           $val = $value->nodeValue;
        }
        return $val;
    }

    private function extractAttributeValueByDom(DOMDocument $dom, $tagname, $attributename) {
        $val = false;
        
        $values = $dom->getElementsByTagName($tagname);
        foreach ($values as $value){
           $val = $value->getAttribute($attributename);
        }
        return $val;
    }
    
    /**
     * Verify if a URL is VALID
     * TODO: Test
     */
    private function isUrlValid() {
        return filter_var($this->url, FILTER_VALIDATE_URL);
    }

    public function getUrl() {
        return $this->url;
    }

}