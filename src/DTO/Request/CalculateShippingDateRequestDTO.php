<?php
/**
 * The MIT License (MIT).
 *
 * Copyright (c) 2017-2021 Michael Dekker (https://github.com/firstred)
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
 * @author    Michael Dekker <git@michaeldekker.nl>
 * @copyright 2017-2021 Michael Dekker
 * @license   https://opensource.org/licenses/MIT The MIT License
 */

declare(strict_types=1);

namespace Firstred\PostNL\DTO\Request;

use Firstred\PostNL\Attribute\PropInterface;
use Firstred\PostNL\Attribute\RequestProp;
use Firstred\PostNL\Exception\InvalidArgumentException;
use Firstred\PostNL\Misc\SerializableObject;
use Firstred\PostNL\Service\DeliveryDateServiceInterface;
use Firstred\PostNL\Service\ServiceInterface;
use function is_numeric;
use JetBrains\PhpStorm\ExpectedValues;

class CalculateShippingDateRequestDTO extends SerializableObject
{
    #[RequestProp(requiredFor: [DeliveryDateServiceInterface::class])]
    protected string|null $DeliveryDate = null;

    #[RequestProp(requiredFor: [DeliveryDateServiceInterface::class])]
    protected int|null $ShippingDuration = null;

    #[RequestProp(requiredFor: [DeliveryDateServiceInterface::class])]
    protected string|null $PostalCode = null;

    #[RequestProp(optionalFor: [DeliveryDateServiceInterface::class])]
    protected string|null $CountryCode = null;

    #[RequestProp(optionalFor: [DeliveryDateServiceInterface::class])]
    protected string|null $OriginCountryCode = null;

    #[RequestProp(optionalFor: [DeliveryDateServiceInterface::class])]
    protected string|null $City = null;

    #[RequestProp(optionalFor: [DeliveryDateServiceInterface::class])]
    protected string|null $Street = null;

    #[RequestProp(optionalFor: [DeliveryDateServiceInterface::class])]
    protected int|null $HouseNumber = null;

    #[RequestProp(optionalFor: [DeliveryDateServiceInterface::class])]
    protected string|null $HouseNrExt = null;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(
        #[ExpectedValues(values: ServiceInterface::SERVICES)]
        string $service,
        #[ExpectedValues(values: PropInterface::PROP_TYPES)]
        string $propType,

        string|null $DeliveryDate = null,
        int|string|null $ShippingDuration = null,
        string|null $PostalCode = null,
        string|null $CountryCode = null,
        string|null $OriginCountryCode = null,
        string|null $City = null,
        string|null $Street = null,
        int|string|null $HouseNumber = null,
        string|null $HouseNrExt = null,
    ) {
        parent::__construct(service: $service, propType: $propType);

        $this->setDeliveryDate(DeliveryDate: $DeliveryDate);
        $this->setShippingDuration(ShippingDuration: $ShippingDuration);
        $this->setPostalCode(PostalCode: $PostalCode);
        $this->setCountryCode(CountryCode: $CountryCode);
        $this->setOriginCountryCode(OriginCountryCode: $OriginCountryCode);
        $this->setCity(City: $City);
        $this->setStreet(Street: $Street);
        $this->setHouseNumber(HouseNumber: $HouseNumber);
        $this->setHouseNrExt(HouseNrExt: $HouseNrExt);
    }

    public function getDeliveryDate(): string|null
    {
        return $this->DeliveryDate;
    }

    public function setDeliveryDate(string|null $DeliveryDate = null): static
    {
        $this->DeliveryDate = $DeliveryDate;

        return $this;
    }

    public function getShippingDuration(): int|null
    {
        return $this->ShippingDuration;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setShippingDuration(int|string|null $ShippingDuration = null): static
    {
        if (is_string(value: $ShippingDuration)) {
            if (!is_numeric(value: $ShippingDuration)) {
                throw new InvalidArgumentException("Invalid `ShippingDuration` value passed: $ShippingDuration");
            }

            $ShippingDuration = (int) $ShippingDuration;
        }

        $this->ShippingDuration = $ShippingDuration;

        return $this;
    }

    public function getPostalCode(): string|null
    {
        return $this->PostalCode;
    }

    public function setPostalCode(string|null $PostalCode = null): static
    {
        $this->PostalCode = $PostalCode;

        return $this;
    }

    public function getCountryCode(): string|null
    {
        return $this->CountryCode;
    }

    public function setCountryCode(string|null $CountryCode = null): static
    {
        $this->CountryCode = $CountryCode;

        return $this;
    }

    public function getOriginCountryCode(): string|null
    {
        return $this->OriginCountryCode;
    }

    public function setOriginCountryCode(string|null $OriginCountryCode = null): static
    {
        $this->OriginCountryCode = $OriginCountryCode;

        return $this;
    }

    public function getCity(): string|null
    {
        return $this->City;
    }

    public function setCity(string|null $City = null): static
    {
        $this->City = $City;

        return $this;
    }

    public function getStreet(): string|null
    {
        return $this->Street;
    }

    public function setStreet(string|null $Street = null): static
    {
        $this->Street = $Street;

        return $this;
    }

    public function getHouseNumber(): int|null
    {
        return $this->HouseNumber;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setHouseNumber(int|string|null $HouseNumber = null): static
    {
        if (is_string(value: $HouseNumber)) {
            if (!is_numeric(value: $HouseNumber)) {
                throw new InvalidArgumentException("Invalid `HouseNumber` value passed: $HouseNumber");
            }

            $HouseNumber = (int) $HouseNumber;
        }

        $this->HouseNumber = $HouseNumber;

        return $this;
    }

    public function getHouseNrExt(): string|null
    {
        return $this->HouseNrExt;
    }

    public function setHouseNrExt(string|null $HouseNrExt = null): static
    {
        $this->HouseNrExt = $HouseNrExt;

        return $this;
    }
}
