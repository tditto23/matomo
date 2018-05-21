<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\SEO\Metric;

use Piwik\Http;
use Piwik\Metrics\Formatter;
use Psr\Log\LoggerInterface;

/**
 * Fetches the domain age using archive.org, who.is and whois.com.
 */
class DomainAge implements MetricsProvider
{
    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Formatter $formatter, LoggerInterface $logger)
    {
        $this->formatter = $formatter;
        $this->logger = $logger;
    }

    public function getMetrics($domain)
    {
        $domain  = str_replace('www.', '', $domain);

        $ages = array();

        $age = $this->getAgeArchiveOrg($domain);
        if ($age > 0) {
            $ages[] = $age;
        }

        $age = $this->getAgeWhoIs($domain);
        if ($age > 0) {
            $ages[] = $age;
        }

        $age = $this->getAgeWhoisCom($domain);
        if ($age > 0) {
            $ages[] = $age;
        }

        if (count($ages) > 0) {
            $value = min($ages);
            $value = $this->formatter->getPrettyTimeFromSeconds(time() - $value, true);
        } else {
            $value = null;
        }

        return array(
            new Metric('domain-age', 'SEO_DomainAge', $value, 'plugins/Morpheus/icons/dist/SEO/whois.png')
        );
    }

    /**
     * Returns the domain age archive.org lists for the current url
     *
     * @param string $domain
     * @return int
     */
    private function getAgeArchiveOrg($domain)
    {
        $data = $this->getUrl('https://wayback.archive.org/web/*/' . urlencode($domain));
        preg_match('#<a href=\"([^>]*)' . preg_quote($domain) . '/\">([^<]*)<\/a>#', $data, $p);
        if (!empty($p[2])) {
            $value = strtotime($p[2]);
            if ($value === false) {
                return 0;
            }
            return $value;
        }
        return 0;
    }

    /**
     * Returns the domain age who.is lists for the current url
     *
     * @param string $domain
     * @return int
     */
    private function getAgeWhoIs($domain)
    {
        $data = $this->getUrl('https://www.who.is/whois/' . urlencode($domain));
        preg_match('#(?:Creation Date|Created On|created|Registered on)\.*:\s*([ \ta-z0-9\/\-:\.]+)#si', $data, $p);
        if (!empty($p[1])) {
            $value = strtotime(trim($p[1]));
            if ($value === false) {
                return 0;
            }
            return $value;
        }
        return 0;
    }

    /**
     * Returns the domain age whois.com lists for the current url
     *
     * @param string $domain
     * @return int
     */
    private function getAgeWhoisCom($domain)
    {
        $data = $this->getUrl('https://www.whois.com/whois/' . urlencode($domain));
        preg_match('#(?:Creation Date|Created On|created|Registration Date):\s*([ \ta-z0-9\/\-:\.]+)#si', $data, $p);
        if (!empty($p[1])) {
            $value = strtotime(trim($p[1]));
            if ($value === false) {
                return 0;
            }
            return $value;
        }
        return 0;
    }

    private function getUrl($url)
    {
        try {
            return $this->getHttpResponse($url);
        } catch (\Exception $e) {
        }

        $httpUrl = str_replace('https://', 'http://', $url);
        try {
            return $this->getHttpResponse($httpUrl);
        } catch (\Exception $e) {
            $this->logger->warning('Error while getting SEO stats (domain age): {message}', array('message' => $e->getMessage()));
            return '';
        }
    }

    private function getHttpResponse($url)
    {
        return str_replace('&nbsp;', ' ', Http::sendHttpRequest($url, $timeout = 10, @$_SERVER['HTTP_USER_AGENT']));
    }
}
