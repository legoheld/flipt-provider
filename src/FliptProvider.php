<?php

namespace OpenFeature\Providers\Flipt;

use Flipt\Client\FliptClient;
use Flipt\Models\BooleanEvaluationResult;
use Flipt\Models\VariantEvaluationResult;
use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\implementation\provider\ResolutionDetailsFactory;
use OpenFeature\implementation\provider\ResolutionError;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\flags\FlagValueType;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Provider;
use OpenFeature\interfaces\provider\ResolutionDetails;


class FliptProvider extends AbstractProvider implements Provider
{
    protected const NAME = 'FliptProvider';

    protected $client;

    public function __construct( mixed $hostOrClient, string $apiToken = '', string $namespace = '' ) {
        $this->client = ( is_string( $hostOrClient ) ) ? new FliptClient( $hostOrClient, $apiToken, $namespace ) : $hostOrClient;
    }


    public function resolveBooleanValue(string $flagKey, bool $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveValue($flagKey, FlagValueType::BOOLEAN, $defaultValue, $context);
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveValue($flagKey, FlagValueType::STRING, $defaultValue, $context);
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveValue($flagKey, FlagValueType::INTEGER, $defaultValue, $context);
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveValue($flagKey, FlagValueType::FLOAT, $defaultValue, $context);
    }

    /**
     * @param mixed[] $defaultValue
     */
    public function resolveObjectValue(string $flagKey, array $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {
        return $this->resolveValue($flagKey, FlagValueType::OBJECT, $defaultValue, $context);
    }

    /**
     * @param bool|string|int|float|mixed[] $defaultValue
     */
    private function resolveValue(string $flagKey, string $flagType, mixed $defaultValue, ?EvaluationContext $context = null): ResolutionDetails
    {

        // booleans need a dedicated function
        if( $flagType == FlagValueType::BOOLEAN ) {
            $result = $this->client->boolean( $flagKey, $context->getAttributes()->toArray(), $context->getTargetingKey() );
        } else {
            $result = $this->client->variant( $flagKey, $context->getAttributes()->toArray(), $context->getTargetingKey() );
        }

        // there is a match
        if( $result->getReason() == 'MATCH_EVALUATION_REASON' || $result->getReason() == "DEFAULT_EVALUATION_REASON" ) {
            return ResolutionDetailsFactory::fromSuccess( $this->castResult( $result, $flagType ) );
        } else {
            return (new ResolutionDetailsBuilder())
                    ->withValue( $defaultValue )
                    ->withError(
                        // not sure if thie reason to error mapping is correct
                        new ResolutionError(ErrorCode::GENERAL(), $result->getReason() ),
                    )
                    ->build();
        }
    }



    private function castResult( VariantEvaluationResult|BooleanEvaluationResult $result, string $type ) {
        switch ($type) {
            case FlagValueType::BOOLEAN:
                return filter_var($result->getEnabled(), FILTER_VALIDATE_BOOLEAN);
            case FlagValueType::FLOAT:
                return (float) $result->getVariantKey();
            case FlagValueType::INTEGER:
                return (int) $result->getVariantKey();
            case FlagValueType::OBJECT:
                return json_decode( $result->getVariantAttachment(), true);
            case FlagValueType::STRING:
                return $result->getVariantKey();
            default:
                return null;
        }
    }

}