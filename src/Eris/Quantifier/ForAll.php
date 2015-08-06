<?php
namespace Eris\Quantifier;

use Eris\Antecedent;
use Eris\Generator;
use Eris\Shrinker;
use BadMethodCallException;
use PHPUnit_Framework_Constraint;
use PHPUnit_Framework_AssertionFailedError;
use PHPUnit_Framework_ExpectationFailedException;
use Exception;
use RuntimeException;

class ForAll
{
    private $generators;
    private $iterations;
    private $shrinkerFactory;
    private $antecedents = [];
    private $evaluations = 0;
    private $aliases = [
        'andAlso' => 'when',
        'theCondition' => 'when',
        'andTheCondition' => 'when',
        'andAlso' => 'when',
        'then' => '__invoke',
        'implies' => '__invoke',
        'imply' => '__invoke',
    ];

    public function __construct(array $generators, $iterations, $shrinkerFactory)
    {
        $this->generators = $this->generatorsFrom($generators);
        $this->iterations = $iterations;
        $this->shrinkerFactory = $shrinkerFactory;
    }

    /**
     * Examples of calls:
     * when($constraint1, $constraint2, ..., $constraintN)
     * when(callable $takesNArguments)
     * @return self
     */
    public function when(/* see docblock */)
    {
        $arguments = func_get_args();
        if ($arguments[0] instanceof Antecedent) {
            $antecedent = $arguments[0];
        } else if ($arguments[0] instanceof PHPUnit_Framework_Constraint) {
            $antecedent = Antecedent\IndependentConstraintsAntecedent::fromAll($arguments);
        } else if ($arguments && count($arguments) == 1) {
            $antecedent = Antecedent\SingleCallbackAntecedent::from($arguments[0]);
        } else {
            throw new \InvalidArgumentException("Invalid call to when(): " . var_export($arguments, true));
        }
        $this->antecedents[] = $antecedent;
        return $this;
    }

    public function __invoke(callable $assertion)
    {
        try {
            $size = 0;
            for ($i = 0; $i < $this->iterations; $i++) {
                $values = [];
                foreach ($this->generators as $name => $generator) {
                    $value = $generator($size);
                    $values[] = $value;
                }
                if (!$this->antecedentsAreSatisfied($values)) {
                    continue;
                }

                $this->evaluations++;
                Evaluation::of($assertion)
                    ->with($values)
                    ->onFailure(function($values, $exception) use ($assertion) {
                        $shrinking = $this->shrinkerFactory->random($this->generators, $assertion);
                        // MAYBE: put into ShrinkerFactory?
                        $shrinking->addGoodShrinkCondition(function(array $values) {
                            return $this->antecedentsAreSatisfied($values);
                        });
                        $shrinking->from($values, $exception);
                    })
                    ->execute();
                $size++;
            }
        } catch (Exception $e) {
            $wrap = (bool) getenv('ERIS_ORIGINAL_INPUT');
            if ($wrap) {
                $message = "Original input: " . var_export($values, true) . PHP_EOL
                    . "Possibly shrinked input follows." . PHP_EOL;
                throw new RuntimeException($message, -1, $e);
            } else {
                throw $e;
            }
        }
    }

    private function antecedentsAreSatisfied(array $values)
    {
        foreach ($this->antecedents as $antecedentToVerify) {
            if (!call_user_func(
                [$antecedentToVerify, 'evaluate'],
                $values
            )) {
                return false;
            }
        }
        return true;
    }

    /**
     * @see $this->aliases
     * @method then($assertion)
     * @method implies($assertion)
     * @method imply($assertion)
     */
    public function __call($method, $arguments)
    {
        if (isset($this->aliases[$method])) {
            return call_user_func_array(
                [$this, $this->aliases[$method]],
                $arguments
            );
        }
        throw new BadMethodCallException("Method " . __CLASS__ . "::{$method} does not exist");
    }

    public function evaluationRatio()
    {
        return $this->evaluations / $this->iterations;
    }

    private function generatorsFrom($supposedToBeGenerators)
    {
        $generators = [];
        foreach($supposedToBeGenerators as $supposedToBeGenerator) {
            if (!$supposedToBeGenerator instanceof Generator) {
                $generators[] = new Generator\Constant($supposedToBeGenerator);
            } else {
                $generators[] = $supposedToBeGenerator;
            }
        }
        return $generators;
    }
}
