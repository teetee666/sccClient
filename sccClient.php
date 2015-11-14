<?php

use GuzzleHttp\Client;
use GuzzleHttp\Cookie;

class sccClient
{
    private $username;
    private $password;
    private $passkey;

    public function __construct()
    {
        $this->categories = [
            8 => 'Movies/DVD-R',
            22 => 'Movies/x264',
            7 => 'Movies/XviD',
            27 => 'TV/HD-x264',
            17 => 'TV/SD-x264',
            11 => 'TV/XviD',
            3 => 'Games/PC',
            5 => 'Games/PS3',
            20 => 'Games/PSP',
            28 => 'Games/WII',
            23 => 'Games/XBOX360',
            1 => 'APPS/ISO',
            14 => 'DOX',
            21 => 'MISC',
            41 => 'P2P/Movies/HD-x264',
            42 => 'P2P/Movies/SD-x264',
            43 => 'P2P/Movies/XviD',
            44 => 'P2P/TV/HD',
            45 => 'P2P/TV/SD',
            2 => '0DAY/APPS',
            40 => 'FLAC',
            13 => 'MP3',
            15 => 'MVID',
            4 => 'Movies/Packs',
            26 => 'TV/Packs',
            29 => 'Games/Packs',
            37 => 'XXX/Packs',
            38 => 'Music/Packs',
            31 => 'F/Movies/DVD-R',
            32 => 'F/Movies/x264',
            30 => 'F/Movies/XviD',
            34 => 'F/TV/x264',
            33 => 'F/TV/XviD',
            12 => 'XXX/XviD',
            35 => 'XXX/x264',
            36 => 'XXX/0DAY',
        ];

        $this->db = new mysqli('127.0.0.1', 'root', 'rootpwd', 'scc');
        $this->httpClient = new Client(['cookies' => true]);
        $this->cookieJar = new \GuzzleHttp\Cookie\FileCookieJar('cookies.json', true);
    }

    /**
     * Tries to login and create/update cookie.
     *
     * @param string $username
     * @param string $password
     *
     * @throws Exception
     */
    public function login($username = '', $password = '')
    {
        if (empty($username) || empty($password)) {
            throw new Exception('Missing username and/or password.');
        } else {
            $this->username = $username;
            $this->password = $password;
        }

        if (false === $this->isLoggedIn()) {
            $this->doLogin();
        }
    }

    /**
     * Tries to actually login.
     *
     * @return bool
     *
     * @throws Exception
     */
    private function doLogin()
    {
        $loginResponse = $this->httpRequest(
            'POST',
            'https://sceneaccess.eu/login',
            [
                'form_params' => [
                    'username' => $this->username,
                    'password' => $this->password,
                    'submit' => 'come on in',
                ],
            ]
        );

        if (preg_match('/SceneAccess \| Login/', $loginResponse)) {
            throw new Exception('Username and/or password wrong');
        } else {
            $this->getTorrentsFromHTML($loginResponse);
        }
    }

    /**
     * Checks if we are logged in or not by doing a GET request to /browse.
     *
     * @return bool
     */
    private function isLoggedIn()
    {
        $response = $this->httpRequest(
            'GET',
            'https://sceneaccess.eu/browse'
        );

        $titleElement = $this->DOMDocumentXPathQuery($response, '//html/head/title');

        if (preg_match('/SceneAccess \| Login/', $titleElement->item(0)->nodeValue)) {
            return false;
        } else {
            /* We already have the /browse, might aswell make it useful */
            $this->getTorrentsFromHTML($response);
        }

        return true;
    }

    /**
     * Retrieves page from scc.
     *
     * @param string $method
     * @param string $url
     * @param array  $postBody
     *
     * @throws Exception
     *
     * @return string
     */
    public function httpRequest($method, $url, $postBody = array())
    {
        echo $url.PHP_EOL;
        $postBody['allow_redirects'] = true;
        $postBody['cookies'] = $this->cookieJar;

        $res = $this->httpClient->request(
            $method,
            $url,
            $postBody
        );

        $response = (string) $res->getBody();
        $statuscode = (int) $res->getStatusCode();

        if (preg_match('/<title>Website is offline/', $response)) {
            throw new Exception('Website is offline, try again later');
        }

        return $response;
    }

    /**
     * Does search and returns found items in array.
     *
     * @param string $search
     *
     * @return array
     */
    public function search($search)
    {
        return $this->getTorrentsFromHTML(
            $this->httpRequest(
                'GET',
                $this->getSearchUrl($search)
            )
        );
    }

    /**
     * Creates download url for torrent.
     *
     * @param int $sccId
     *
     * @throws Exception
     *
     * @return string
     */
    private function getTorrentDownloadUrlById($sccId)
    {
        $prepare = $this->db->prepare('
            SELECT
                `name`
            FROM
                `torrents`
            WHERE
                `sccId` = ?;
        ');

        $prepare->bind_param('i', $sccId);
        $prepare->execute();
        $result = $prepare->get_result();

        if ($row = $result->fetch_array(MYSQLI_NUM)) {
            $torrentName = $row[0];
        } else {
            throw new Exception('No name found for '.$sccId);
        }

        $prepare->close();

        if (!$this->passkey) {
            if (file_exists(__DIR__.'/.passkey')) {
                $this->passkey = file_get_contents(__DIR__.'/.passkey');
            } else {
                throw new Exception('No passkey found');
            }
        }

        return 'https://sceneaccess.eu/download/'.$sccId.'/'.$this->passkey.'/'.$torrentName.'.torrent';
    }

    /**
     * Download torrentfile to folder.
     *
     * @param int    $sccId
     * @param string $saveFolder
     *
     * @return mixed
     */
    public function downloadTorrentById($sccId, $saveFolder)
    {
        $torrentData = $this->httpRequest(
            'GET',
            $this->getTorrentDownloadUrlById($sccId)
        );

        if (false === $saveFolder) {
            return base64_encode($torrentData);
        } else {
            return file_put_contents($saveFolder.'/'.md5(time().rand(1, 99)).'.torrent', $torrentData);
        }
    }

    /**
     * Get search url for scc.
     *
     * @param string $search
     *
     * @return string
     */
    private function getSearchUrl($search)
    {
        $categories = '';

        foreach (array_keys($this->categories) as $categoryId) {
            $categories .= '&c'.$categoryId.'=1';
        }

        return 'https://sceneaccess.eu/all?search='.urlencode($search).'&method=2'.$categories;
    }

    /**
     * Checks if the rls exists in our db.
     *
     * @param int $sccId
     *
     * @return bool
     */
    private function torrentExists($sccId = 0)
    {
        $res = $this->db->prepare('
            SELECT
                `id`
            FROM
                `torrents`
            WHERE
                `sccId` = ?;
        ');

        $res->bind_param('i', $sccId);
        $e = $res->execute();

        if (true !== $res->fetch()) {
            return false;
        }

        return true;
    }

    /**
     * Adds torrent to db for later use.
     *
     * @param array $torrentInfo
     *
     * @throws Exception
     *
     * @return bool
     */
    private function addTorrent(array $torrentInfo = array())
    {
        $neededKeys = ['name', 'category', 'sccId', 'size', 'files', 'added'];
        foreach ($neededKeys as $neededKey) {
            if (!array_key_exists($neededKey, $torrentInfo)) {
                throw new Exception('Missing key: '.$neededKey);
            } else {
                ${$neededKey} = $torrentInfo[$neededKey];
            }
        }

        $ins = $this->db->prepare('
            INSERT INTO
                `torrents`
            (
                `id`,
                `name`,
                `category`,
                `files`,
                `size`,
                `added`,
                `sccId`
            ) VALUES (
                NULL,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            );
        ');

        $ins->bind_param(
            'siidsi',
            $name,
            $category,
            $files,
            $size,
            $added,
            $sccId
        );
        $ins->execute();

        if (1 == $ins->affected_rows) {
            return true;
        }

        return false;
    }

    /**
     * Returns the name of category.
     *
     * @param string|int $categoryId
     *
     * @throws Exception
     *
     * @return string
     */
    private function getCategoryNameById($categoryId)
    {
        if (!isset($this->categories[$categoryId])) {
            throw new Exception('No such category found.');
        }

        return $this->categories[$categoryId];
    }

    /**
     * Converts sizes used in site to mb.
     *
     * @param string $source
     * @param float  $sizeInSource
     *
     * @return float
     */
    private function sizeToMB($source, $sizeInSource)
    {
        $source = strtoupper($source);

        switch ($source) {
            case 'KB':
                return round($sizeInSource / 1024, 2);
                break;
            case 'MB':
                return round($sizeInSource, 2);
                break;
            case 'GB':
                return round($sizeInSource * 1024, 2);
                break;
            case 'TB':
                return round($sizeInSource * 1024 * 1024, 2);
                break;
            default:
                return round($sizeInSource, 2);
        }
    }

    /**
     * @param string $passkey
     */
    private function setPasskey($passkey)
    {
        $this->passkey = $passkey;
        /* I am more than sorry about this "hack" */
        file_put_contents('.passkey', $passkey);
    }

    /**
     * Parses given HTML string and returns array of torrents with id, name.. etc.
     *
     * @param string $html
     *
     * @return array
     */
    private function getTorrentsFromHTML($html)
    {
        $doc = new DOMDocument();
        @$doc->loadHTML($html);

        $xpath = new DOMXpath($doc);
        $elements = $xpath->query('//html/body/div/div/div/table/tr[*]');

        $i = 0;
        $torrents = array();

        foreach ($elements as $e) {
            ++$i;

            /* We dont want the header info ("Name Size Added Snatched") */
            if (1 == $i) {
                continue;
            }

            $nodeDom = new DOMDocument();
            $nodeDom->loadHTML($doc->saveHTML($e));
            $nodeXpath = new DOMXpath($nodeDom);
            $nodeElements = $nodeXpath->query('//tr/td');

            foreach ($nodeElements as $nodeElement) {
                $nodeHtml = $nodeDom->saveHTML($nodeElement);

                if (preg_match('/ttr_type/', $nodeHtml)) {
                    $categoryElement = $this->DOMDocumentXPathQuery($nodeHtml, '//td/a/@href');
                    preg_match('/([0-9]+)$/', $categoryElement->item(0)->nodeValue, $category);
                    /*
                      [1]=> string(1) "1"
                    */

                    $torrents[$i]['category'] = $category[1];
                } elseif (preg_match('/td_dl/', $nodeHtml)) {
                    $aHrefElement = $this->DOMDocumentXPathQuery($nodeHtml, '//td/a/@href');
                    preg_match('/^download\/([0-9]+)\/([a-zA-Z0-9]+)\/(.*)\.torrent$/', $aHrefElement->item(0)->nodeValue, $aHrefRes);
                    /*
                      [1]=> string(7) "1231128"
                      [2]=> string(32) "asdf"
                      [3]=> string(53) "Walkabout.1971.REAL.PROPER.1080p.BluRay.x264-SADPANDA"
                    */

                    $torrents[$i]['sccId'] = $aHrefRes[1];
                    $torrents[$i]['name'] = $aHrefRes[3];

                    if (!isset($this->passkey)) {
                        $this->setPasskey($aHrefRes[2]);
                    }
                } elseif (preg_match('/ttr_size/', $nodeHtml)) {
                    preg_match('/<td class="ttr_size">([0-9.]+) ([a-zA-Z]+)<br><a class="small" href="details[?]id=[0-9]+[&]amp[;]filelist=1[#]filelist">([0-9]+) Files<\/a>/', $nodeHtml, $fileAndSize);
                    /*
                      [1]=> string(4) "8.84"
                      [2]=> string(2) "GB"
                      [3]=> string(2) "99"
                    */

                    $torrents[$i]['size'] = $this->sizeToMB($fileAndSize[2], $fileAndSize[1]);
                    $torrents[$i]['files'] = $fileAndSize[3];
                } elseif (preg_match('/ttr_added/', $nodeHtml)) {
                    preg_match('/^([0-9-]{10})([0-9:]{8})$/', strip_tags($nodeHtml), $timestamp);
                    /*
                      [1]=> string(10) "2015-11-13"
                      [2]=> string(8) "23:34:18"
                    */

                    $torrents[$i]['added'] = $timestamp[1].' '.$timestamp[2];
                }
            }
        }

        foreach ($torrents as $torrent) {
            if (!$this->torrentExists($torrent['sccId'])) {
                $this->addTorrent($torrent);
            }
        }

        return $torrents;
    }

    private function DOMDocumentXPathQuery($html, $query)
    {
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXpath($doc);
        $elements = $xpath->query($query);

        return $elements;
    }

    public function __desctruct()
    {
        $this->mysqli->close();
    }
}
