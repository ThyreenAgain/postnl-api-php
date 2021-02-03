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

namespace Firstred\PostNL\DTO\Response;

use ArrayAccess;
use Countable;
use Firstred\PostNL\Attribute\ResponseProp;
use Firstred\PostNL\Exception\InvalidArgumentException;
use Firstred\PostNL\Service\BarcodeServiceInterface;
use Iterator;
use JetBrains\PhpStorm\Pure;
use function is_int;
use function is_string;

class GenerateBarcodesResponseDTO implements ArrayAccess, Countable, Iterator
{
    private int $idx = 0;

    public function __construct(
        private array $responses = [],
    ) {
        foreach ($this->responses as $idx => $response) {
            if (!$response instanceof GenerateBarcodeResponseDTO) {
                $this->responses[$idx] = new GenerateBarcodeResponseDTO(
                    service: BarcodeServiceInterface::class,
                    propType: ResponseProp::class,

                    Barcode: $response,
                );
            }
        }
    }

    public function current(): ?GenerateBarcodeResponseDTO
    {
        return array_values(array: $this->responses)[$this->idx] ?? null;
    }

    public function next(): void
    {
        ++$this->idx;
    }

    public function key(): string|null
    {
        return array_keys(array: $this->responses)[$this->idx] ?? null;
    }

    public function valid(): bool
    {
        return isset(array_values(array: $this->responses)[$this->idx]);
    }

    public function rewind(): void
    {
        $this->idx = 0;
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_int(value: $offset) && !is_string(value: $offset)) {
            return false;
        }

        return isset($this->responses[$offset]);
    }

    public function offsetGet(mixed $offset): ?GenerateBarcodeResponseDTO
    {
        if (!$this->offsetExists(offset: $offset)) {
            return null;
        }

        return $this->responses[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_int(value: $offset) && !is_string(value: $offset)) {
            throw new InvalidArgumentException('Invalid offset given');
        }

        if (!$value instanceof GenerateBarcodeResponseDTO) {
            throw new InvalidArgumentException('Invalid `GenerateBarcodeResponse` given');
        }

        $this->responses[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        if (!$this->offsetExists(offset: $offset)) {
            return;
        }

        unset($this->responses[$offset]);
    }

    public function add(GenerateBarcodeResponseDTO $generateBarcodeResponseDTO): void
    {
        $this->responses[] = $generateBarcodeResponseDTO;
    }

    #[Pure]
    public function count(): int
    {
        return count(value: $this->responses);
    }
}
