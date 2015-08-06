<?php
namespace Eris\Generator;

use Eris\Generator;
use InvalidArgumentException;
use DomainException;

/**
 * Generates a positive or negative integer (with absolute value bounded by
 * the generation size).
 */
function int()
{
    return new Integer();
}

/**
 * Generates a positive integer (bounded by the generation size).
 */
function pos()
{
    $mustBePositive = function($n) {
        return abs($n);
    };
    return new Integer($mustBePositive);
}

/**
 * Generates a negative integer (bounded by the generation size).
 */
function neg()
{
    $mustBeNegative = function($n) {
        if ($n > 0) {
            return $n * (-1);
        }
        return $n;
    };
    return new Integer($mustBeNegative);
}

// We need the Generator\choose to make this generator.
//function byte()
//{
//    return new Integer(0, 255);
//}

class Integer implements Generator
{
    private $mapFn;

    public function __construct(callable $mapFn = null)
    {
        if (is_null($mapFn)) {
            $this->mapFn = $this->identity();
        } else {
            $this->mapFn = $mapFn;
        }
    }

    public function __invoke()
    {
        // TODO: Generator interface must receive the size in the __invoke
        $size = func_get_arg(0);
        $value = rand(0, $size);
        $mapFn = $this->mapFn;

        return rand(0, 1) === 0
                          ? $mapFn($value)
                          : $mapFn($value * (-1));
    }

    public function shrink($element)
    {
        $this->checkValueToShrink($element);

        if ($element > 0) {
            return $element - 1;
        }
        if ($element < 0) {
            return $element + 1;
        }

        return $element;
    }

    public function contains($element)
    {
        return is_int($element);
    }

    private function checkValueToShrink($value)
    {
        if (!$this->contains($value)) {
            throw new DomainException(
                'Cannot shrink ' . $value . ' because it does not belong to ' .
                'the domain of Integers'
            );
        }
    }

    private function identity()
    {
        return function($n) {
            return $n;
        };
    }
}
