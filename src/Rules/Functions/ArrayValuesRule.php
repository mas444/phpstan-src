<?php declare(strict_types = 1);

namespace PHPStan\Rules\Functions;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\VerbosityLevel;
use function count;
use function sprintf;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
class ArrayValuesRule implements Rule
{

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly bool $treatPhpDocTypesAsCertain,
	)
	{
	}

	public function getNodeType(): string
	{
		return FuncCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!($node->name instanceof Node\Name)) {
			return [];
		}

		if (AccessoryArrayListType::isListTypeEnabled() === false) {
			return [];
		}

		if (!$this->reflectionProvider->hasFunction($node->name, $scope)) {
			return [];
		}

		$functionReflection = $this->reflectionProvider->getFunction($node->name, $scope);
		if ($functionReflection->getName() !== 'array_values') {
			return [];
		}

		$parametersAcceptor = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$node->getArgs(),
			$functionReflection->getVariants(),
			$functionReflection->getNamedArgumentsVariants(),
		);

		$normalizedFuncCall = ArgumentsNormalizer::reorderFuncArguments($parametersAcceptor, $node);

		if ($normalizedFuncCall === null) {
			return [];
		}

		$args = $normalizedFuncCall->getArgs();
		if (count($args) === 0) {
			return [];
		}

		if ($this->treatPhpDocTypesAsCertain) {
			$arrayType = $scope->getType($args[0]->value);
		} else {
			$arrayType = $scope->getNativeType($args[0]->value);
		}

		if ($arrayType->isIterableAtLeastOnce()->no()) {
			$message = 'Parameter #1 $array (%s) to function array_values is empty, call has no effect.';
			$errorBuilder = RuleErrorBuilder::message(sprintf(
				$message,
				$arrayType->describe(VerbosityLevel::value()),
			));
			if ($this->treatPhpDocTypesAsCertain) {
				$nativeArrayType = $scope->getNativeType($args[0]->value);
				if (!$nativeArrayType->isIterableAtLeastOnce()->no()) {
					$errorBuilder->tip('Because the type is coming from a PHPDoc, you can turn off this check by setting <fg=cyan>treatPhpDocTypesAsCertain: false</> in your <fg=cyan>%configurationFile%</>.');
				}
			}

			return [
				$errorBuilder->build(),
			];
		}

		if ($arrayType->isList()->yes()) {
			$message = 'Parameter #1 $array (%s) of array_values is already a list, call has no effect.';
			$errorBuilder = RuleErrorBuilder::message(sprintf(
				$message,
				$arrayType->describe(VerbosityLevel::value()),
			));
			if ($this->treatPhpDocTypesAsCertain) {
				$nativeArrayType = $scope->getNativeType($args[0]->value);
				if (!$nativeArrayType->isList()->yes()) {
					$errorBuilder->tip('Because the type is coming from a PHPDoc, you can turn off this check by setting <fg=cyan>treatPhpDocTypesAsCertain: false</> in your <fg=cyan>%configurationFile%</>.');
				}
			}

			return [
				$errorBuilder->build(),
			];
		}

		return [];
	}

}
