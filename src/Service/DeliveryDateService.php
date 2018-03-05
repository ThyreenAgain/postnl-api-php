<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2017 Thirty Development, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software
 * is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author    Michael Dekker <michael@thirtybees.com>
 * @copyright 2017-2018 Thirty Development, LLC
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

namespace ThirtyBees\PostNL\Service;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemInterface;
use Sabre\Xml\Reader;
use Sabre\Xml\Service as XmlService;
use ThirtyBees\PostNL\Entity\AbstractEntity;
use ThirtyBees\PostNL\Entity\CutOffTime;
use ThirtyBees\PostNL\Entity\Request\GetDeliveryDate;
use ThirtyBees\PostNL\Entity\Request\GetSentDate;
use ThirtyBees\PostNL\Entity\Response\GetDeliveryDateResponse;
use ThirtyBees\PostNL\Entity\Response\GetSentDateResponse;
use ThirtyBees\PostNL\Entity\SOAP\Security;
use ThirtyBees\PostNL\Exception\ApiException;
use ThirtyBees\PostNL\Exception\CifDownException;
use ThirtyBees\PostNL\Exception\CifException;
use ThirtyBees\PostNL\PostNL;

/**
 * Class DeliveryDateService
 *
 * @package ThirtyBees\PostNL\Service
 *
 * @method GetDeliveryDateResponse getDeliveryDate(GetDeliveryDate $getDeliveryDate)
 * @method GetSentDateResponse     getSentDate(GetSentDate $getSentDate)
 */
class DeliveryDateService extends AbstractService
{
    // API Version
    const VERSION = '2.2';

    // Endpoints
    const LIVE_ENDPOINT = 'https://api.postnl.nl/shipment/v2_2/calculate/date';
    const SANDBOX_ENDPOINT = 'https://api-sandbox.postnl.nl/shipment/v2_2/calculate/date/';
    const LEGACY_SANDBOX_ENDPOINT = 'https://testservice.postnl.com/CIF_SB/DeliveryDateWebService/2_1/DeliveryDateWebService.svc';
    const LEGACY_LIVE_ENDPOINT = 'https://service.postnl.com/CIF/DeliveryDateWebService/2_1/DeliveryDateWebService.svc';

    // SOAP API
    const SOAP_ACTION = 'http://postnl.nl/cif/services/DeliveryDateWebService/IDeliveryDateWebService/GetDeliveryDate';
    const SOAP_ACTION_SENT = 'http://postnl.nl/cif/services/DeliveryDateWebService/IDeliveryDateWebService/GetSentDate';
    const SERVICES_NAMESPACE = 'http://postnl.nl/cif/services/DeliveryDateWebService/';
    const DOMAIN_NAMESPACE = 'http://postnl.nl/cif/domain/DeliveryDateWebService/';

    /**
     * Namespaces uses for the SOAP version of this service
     *
     * @var array $namespaces
     */
    public static $namespaces = [
        self::ENVELOPE_NAMESPACE     => 'soap',
        self::OLD_ENVELOPE_NAMESPACE => 'env',
        self::SERVICES_NAMESPACE     => 'services',
        self::DOMAIN_NAMESPACE       => 'domain',
        Security::SECURITY_NAMESPACE => 'wsse',
        self::XML_SCHEMA_NAMESPACE   => 'schema',
        self::COMMON_NAMESPACE       => 'common',
    ];

    /**
     * Get a delivery date via REST
     *
     * @param GetDeliveryDate $getDeliveryDate
     *
     * @return GetDeliveryDateResponse
     *
     * @throws ApiException
     * @throws CifDownException
     * @throws CifException
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ThirtyBees\PostNL\Exception\ResponseException
     */
    public function getDeliveryDateREST(GetDeliveryDate $getDeliveryDate)
    {
        $item = $this->retrieveCachedItem($getDeliveryDate->getId());
        $response = null;
        if ($item instanceof CacheItemInterface) {
            $response = $item->get();
            try {
                $response = \GuzzleHttp\Psr7\parse_response($response);
            } catch (\InvalidArgumentException $e) {
            }
        }
        if (!$response instanceof Response) {
            $response = $this->postnl->getHttpClient()->doRequest($this->buildGetDeliveryDateRESTRequest($getDeliveryDate));
            static::validateRESTResponse($response);
        }
        $body = json_decode(static::getResponseText($response), true);
        if (isset($body['DeliveryDate'])) {
            if ($item instanceof CacheItemInterface
                && $response instanceof Response
                && $response->getStatusCode() === 200
            ) {
                $item->set(\GuzzleHttp\Psr7\str($response));
                $this->cacheItem($item);
            }

            return AbstractEntity::jsonDeserialize(['GetDeliveryDateResponse' => $body]);
        }

        throw new ApiException('Unable to generate label');
    }

    /**
     * Generate a single label via SOAP
     *
     * @param GetDeliveryDate $getDeliveryDate
     *
     * @return GetDeliveryDateResponse
     * @throws CifDownException
     * @throws CifException
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Sabre\Xml\LibXMLException
     * @throws \ThirtyBees\PostNL\Exception\ResponseException
     */
    public function getDeliveryDateSOAP(GetDeliveryDate $getDeliveryDate)
    {
        $item = $this->retrieveCachedItem($getDeliveryDate->getId());
        $response = null;
        if ($item instanceof CacheItemInterface) {
            $response = $item->get();
            try {
                $response = \GuzzleHttp\Psr7\parse_response($response);
            } catch (\InvalidArgumentException $e) {
            }
        }
        if (!$response instanceof Response) {
            $response = $this->postnl->getHttpClient()->doRequest($this->buildGetDeliveryDateSOAPRequest($getDeliveryDate));
        }
        $xml = simplexml_load_string(static::getResponseText($response));

        static::registerNamespaces($xml);
        static::validateSOAPResponse($xml);

        if ($item instanceof CacheItemInterface
            && $response instanceof Response
            && $response->getStatusCode() === 200
        ) {
            $item->set(\GuzzleHttp\Psr7\str($response));
            $this->cacheItem($item);
        }

        $reader = new Reader();
        $reader->xml(static::getResponseText($response));
        $array = array_values($reader->parse()['value'][0]['value']);
        $array = $array[0];

        return AbstractEntity::xmlDeserialize($array);
    }

    /**
     * Get the sent date via REST
     *
     * @param GetSentDate $getSentDate
     *
     * @return GetDeliveryDateResponse
     *
     * @throws ApiException
     * @throws CifDownException
     * @throws CifException
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ThirtyBees\PostNL\Exception\ResponseException
     */
    public function getSentDateREST(GetSentDate $getSentDate)
    {
        $item = $this->retrieveCachedItem($getSentDate->getId());
        $response = null;
        if ($item instanceof CacheItemInterface) {
            $response = $item->get();
            try {
                $response = \GuzzleHttp\Psr7\parse_response($response);
            } catch (\InvalidArgumentException $e) {
            }
        }
        if (!$response instanceof Response) {
            $response = $this->postnl->getHttpClient()->doRequest($this->buildGetDeliveryDateRESTRequest($getSentDate));
            static::validateRESTResponse($response);
        }
        $body = json_decode(static::getResponseText($response), true);
        if (isset($body['DeliveryDate'])) {
            if ($item instanceof CacheItemInterface
                && $response instanceof Response
                && $response->getStatusCode() === 200
            ) {
                $item->set(\GuzzleHttp\Psr7\str($response));
                $this->cacheItem($item);
            }

            return AbstractEntity::jsonDeserialize(['GetSentDateResponse' => $body]);
        }

        throw new ApiException('Unable to generate label');
    }

    /**
     * Generate a single label via SOAP
     *
     * @param GetSentDate $GetDeliveryDate
     * @param bool        $confirm
     *
     * @return GetDeliveryDateResponse
     * @throws CifDownException
     * @throws CifException
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Sabre\Xml\LibXMLException
     * @throws \ThirtyBees\PostNL\Exception\ResponseException
     */
    public function getSentDateSOAP(GetSentDate $GetDeliveryDate, $confirm = false)
    {
        $item = $this->retrieveCachedItem($GetDeliveryDate->getId());
        $response = null;
        if ($item instanceof CacheItemInterface) {
            $response = $item->get();
            try {
                $response = \GuzzleHttp\Psr7\parse_response($response);
            } catch (\InvalidArgumentException $e) {
            }
        }
        if (!$response instanceof Response) {
            $response = $this->postnl->getHttpClient()->doRequest($this->buildGetDeliveryDateSOAPRequest($GetDeliveryDate, $confirm));
        }
        $xml = simplexml_load_string(static::getResponseText($response));

        static::registerNamespaces($xml);
        static::validateSOAPResponse($xml);

        if ($item instanceof CacheItemInterface
            && $response instanceof Response
            && $response->getStatusCode() === 200
        ) {
            $item->set(\GuzzleHttp\Psr7\str($response));
            $this->cacheItem($item);
        }

        $reader = new Reader();
        $reader->xml(static::getResponseText($response));
        $array = array_values($reader->parse()['value'][0]['value']);
        $array = $array[0];

        return AbstractEntity::xmlDeserialize($array);
    }

    /**
     * Build the GetDeliveryDate request for the REST API
     *
     * @param GetDeliveryDate $getDeliveryDate
     *
     * @return Request
     */
    public function buildGetDeliveryDateRESTRequest(GetDeliveryDate $getDeliveryDate)
    {
        $apiKey = $this->postnl->getRestApiKey();
        $this->setService($getDeliveryDate);

        $query = [
            'ShippingDate' => $getDeliveryDate->getShippingDate(),
            'Options'      => 'DayTime',
        ];
        if ($shippingDuration = $getDeliveryDate->getShippingDuration()) {
            $query['ShippingDuration'] = $shippingDuration;
        }
        if ($times = $cutOffTime = $getDeliveryDate->getCutOffTimes()) {
            foreach ($times as $time) {
                /** @var CutOffTime $time */
                switch ($time->getDay()) {
                    case '00':
                        $query['CutOffTime'] = date('H:i:s', strtotime($time->getTime()));
                        break;
                    case '01':
                        $query['CutOffTimeMonday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableMonday'] = $time->getAvailable() ? 'true' : false;
                        break;
                    case '02':
                        $query['CutOffTimeTuesday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableTuesday'] = $time->getAvailable() ? 'true' : false;
                        break;
                    case '03':
                        $query['CutOffTimeWednesday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableWednesday'] = $time->getAvailable() ? 'true' : false;
                        break;
                    case '04':
                        $query['CutOffTimeThursday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableThursday'] = $time->getAvailable() ? 'true' : false;
                        break;
                    case '05':
                        $query['CutOffTimeFriday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableFriday'] = $time->getAvailable() ? 'true' : false;
                        break;
                    case '06':
                        $query['CutOffTimeSaturday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableSaturday'] = $time->getAvailable() ? 'true' : false;
                        break;
                    case '07':
                        $query['CutOffTimeSunday'] = date('H:i:s', strtotime($time->getTime()));
                        $query['AvailableSunday'] = $time->getAvailable() ? 'true' : false;
                        break;
                }
            }
        }
        if ($postcode = $getDeliveryDate->getPostalCode()) {
            $query['PostalCode'] = $postcode;
        }
        $query['CountryCode'] = $getDeliveryDate->getCountryCode();
        if ($originCountryCode = $getDeliveryDate->getOriginCountryCode()) {
            $query['OriginCountryCode'] = $originCountryCode;
        }
        if ($city = $getDeliveryDate->getCity()) {
            $query['City'] = $city;
        }
        if ($houseNr = $getDeliveryDate->getHouseNr()) {
            $query['HouseNr'] = $houseNr;
        }
        if ($houseNrExt = $getDeliveryDate->getHouseNrExt()) {
            $query['HouseNrExt'] = $houseNrExt;
        }
        foreach ($getDeliveryDate->getOptions() as $option) {
            if ($option === 'DayTime') {
                continue;
            }

            $query['Options'] .= ",$option";
        }

        $endpoint = '/delivery?'.http_build_query($query);

        return new Request(
            'POST',
            $this->postnl->getSandbox() ? static::SANDBOX_ENDPOINT : static::LIVE_ENDPOINT.$endpoint,
            [
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]
        );
    }

    /**
     * Build the GetDeliveryDate request for the SOAP API
     *
     * @param GetDeliveryDate $getDeliveryDate
     *
     * @return Request
     */
    public function buildGetDeliveryDateSOAPRequest(GetDeliveryDate $getDeliveryDate)
    {
        $soapAction = static::SOAP_ACTION;
        $xmlService = new XmlService();
        foreach (static::$namespaces as $namespace => $prefix) {
            $xmlService->namespaceMap[$namespace] = $prefix;
        }
        $security = new Security($this->postnl->getToken());

        $this->setService($security);
        $this->setService($getDeliveryDate);

        $request = $xmlService->write(
            '{'.static::ENVELOPE_NAMESPACE.'}Envelope',
            [
                '{'.static::ENVELOPE_NAMESPACE.'}Header' => [
                    ['{'.Security::SECURITY_NAMESPACE.'}Security' => $security],
                ],
                '{'.static::ENVELOPE_NAMESPACE.'}Body'   => [
                    '{'.static::SERVICES_NAMESPACE.'}GetDeliveryDate' => $getDeliveryDate,
                ],
            ]
        );

        $endpoint = $this->postnl->getSandbox()
            ? ($this->postnl->getMode() === PostNL::MODE_LEGACY ? static::LEGACY_SANDBOX_ENDPOINT : static::SANDBOX_ENDPOINT)
            : ($this->postnl->getMode() === PostNL::MODE_LEGACY ? static::LEGACY_LIVE_ENDPOINT : static::LIVE_ENDPOINT);

        return new Request(
            'POST',
            $endpoint,
            [
                'SOAPAction'   => "\"$soapAction\"",
                'Content-Type' => 'text/xml',
                'Accept'       => 'text/xml',
            ],
            $request
        );
    }

    /**
     * Build the GetSentDate request for the REST API
     *
     * @param GetSentDate $getSentDate
     *
     * @return Request
     */
    public function buildGetSentDateRESTRequest(GetSentDate $getSentDate)
    {
        $apiKey = $this->postnl->getRestApiKey();
        $this->setService($getSentDate);

        $query = [
            'ShippingDate' => $getSentDate->getDeliveryDate(),
        ];
        $query['CountryCode'] = $getSentDate->getCountryCode();
        if ($originCountryCode = $getSentDate->getOriginCountryCode()) {
            $query['OriginCountryCode'] = $originCountryCode;
        }
        if ($duration = $getSentDate->getShippingDuration()) {
            $query['ShippingDuration'] = $duration;
        }
        if ($postcode = $getSentDate->getPostalCode()) {
            $query['PostalCode'] = $postcode;
        }
        if ($city = $getSentDate->getCity()) {
            $query['City'] = $city;
        }
        if ($houseNr = $getSentDate->getHouseNr()) {
            $query['HouseNr'] = $houseNr;
        }
        if ($houseNrExt = $getSentDate->getHouseNrExt()) {
            $query['HouseNrExt'] = $houseNrExt;
        }

        $endpoint = '/shipping?'.http_build_query($query);

        return new Request(
            'POST',
            $this->postnl->getSandbox() ? static::SANDBOX_ENDPOINT : static::LIVE_ENDPOINT.$endpoint,
            [
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]
        );
    }

    /**
     * Build the GetSentDate request for the SOAP API
     *
     * @param GetSentDate $getSentDate
     *
     * @return Request
     */
    public function buildGetSentDateSOAPRequest(GetSentDate $getSentDate)
    {
        $soapAction = static::SOAP_ACTION;
        $xmlService = new XmlService();
        foreach (static::$namespaces as $namespace => $prefix) {
            $xmlService->namespaceMap[$namespace] = $prefix;
        }
        $security = new Security($this->postnl->getToken());

        $this->setService($security);
        $this->setService($getSentDate);

        $request = $xmlService->write(
            '{'.static::ENVELOPE_NAMESPACE.'}Envelope',
            [
                '{'.static::ENVELOPE_NAMESPACE.'}Header' => [
                    ['{'.Security::SECURITY_NAMESPACE.'}Security' => $security],
                ],
                '{'.static::ENVELOPE_NAMESPACE.'}Body'   => [
                    '{'.static::SERVICES_NAMESPACE.'}GetSentDate' => $getSentDate,
                ],
            ]
        );

        $endpoint = $this->postnl->getSandbox()
            ? ($this->postnl->getMode() === PostNL::MODE_LEGACY ? static::LEGACY_SANDBOX_ENDPOINT : static::SANDBOX_ENDPOINT)
            : ($this->postnl->getMode() === PostNL::MODE_LEGACY ? static::LEGACY_LIVE_ENDPOINT : static::LIVE_ENDPOINT);

        return new Request(
            'POST',
            $endpoint,
            [
                'SOAPAction'   => "\"$soapAction\"",
                'Content-Type' => 'text/xml',
                'Accept'       => 'text/xml',
            ],
            $request
        );
    }
}
