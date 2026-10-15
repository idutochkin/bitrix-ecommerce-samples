<?
namespace Westpower\Fiji\Repository\Client;

use Exception;
use DOMDocument;
use DOMNode;
use Bitrix\Main\Web\HttpClient;
use Westpower\Fiji\Repository\OptionRepository;

class RKeeperClient
{
    /**
     * @param string $action
     * @param DOMDocument|null $body
     * @param array $attributes
     * @param bool $returnResult
     * @return DOMNode
     */
    public static function call(string $action, DOMDocument $body = null, array $attributes = [], bool $returnResult = true): DOMNode
    {
        $xml = new DOMDocument;
        $xml->formatOutput = true;

        $rK7Query = $xml->createElement('RK7Query');

        $rK7CMD = $xml->createElement('RK7CMD');
        $rK7CMD->setAttribute('CMD', $action);
        foreach ($attributes as $key => $value)
            $rK7CMD->setAttribute($key, $value);

        if ($body) {
            foreach ($body->childNodes as $element)
                $rK7CMD->appendChild($xml->importNode($element, true));
        }

        $rK7Query->appendChild($rK7CMD);
        $xml->appendChild($rK7Query);

        return self::send($xml->saveXML(), $returnResult);
    }

    /**
     * @param string $body
     * @param bool $returnResult
     * @return DOMNode
     * @throws Exception
     */
    private static function send(string $body, bool $returnResult = true): DOMNode
    {
        $result = null;

        $httpClient = new HttpClient();
        // у кассы в локальной сети может быть самоподписанный сертификат
        if (OptionRepository::get('rkeeper_ssl_verify', 'Y') !== 'Y')
            $httpClient->disableSslVerification();
        $httpClient->setHeader('Content-Type', 'text/xml');
        $httpClient->setAuthorization(OptionRepository::get('rkeeper_login'), OptionRepository::get('rkeeper_password'));
        $httpClient->post(OptionRepository::get('rkeeper_url') . '/rk7api/v0/xmlinterface.xml', $body);

        if ($httpClient->getError())
            throw new Exception(implode(', ', $httpClient->getError()));
        else {
            $xml = new DOMDocument;
            $xml->formatOutput = true;
            $xml->loadXML((string) $httpClient->getResult());
            $result = $xml->getElementsByTagName('RK7QueryResult')->item(0);

            if ($result->getAttribute('Status') !== 'Ok')
                throw new Exception('Status: ' . $result->getAttribute('Status') . '; ' . 'Description: ' . $result->getAttribute('ErrorText'));
        }

        return $returnResult ? $result : $xml;
    }
}