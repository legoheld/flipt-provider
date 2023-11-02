<?php

namespace Tests;

use Flipt\Client\FliptClient;
use Flipt\Models\DefaultBooleanEvaluationResult;
use Flipt\Models\DefaultVariantEvaluationResult;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\App;
use Mockery;
use OpenFeature\OpenFeature;
use OpenFeature\interfaces\provider\Provider as OpenFeatureProvider;
use OpenFeature\OpenFeatureAPI;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\implementation\provider\ResolutionDetails;
use OpenFeature\implementation\provider\ResolutionDetailsFactory;
use OpenFeature\Mappers\ContextMapper;
use OpenFeature\Providers\Flipt\FliptProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FliptProviderTest extends TestCase
{

    protected MockInterface $mockClient;
    protected FliptProvider $provider;

    protected function setUp(): void
    {
        $this->mockClient = Mockery::mock();
        $this->provider = new FliptProvider( $this->mockClient );
    }


    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testBoolean() 
    {
        $this->mockClient->shouldReceive( 'boolean')
            ->withArgs( function( $flag, $context, $entityId ) {
                $this->assertEquals( $flag, 'flag' );
                $this->assertEquals( $context, [ 'context' => 'demo' ] );
                $this->assertEquals( $entityId, 'id' );
                return true;
            })
            ->andReturn( new DefaultBooleanEvaluationResult( true, 'MATCH_EVALUATION_REASON', 0.1, 'rid', '13245' ) );

        $result = $this->provider->resolveBooleanValue( 'flag', false, new EvaluationContext( 'id', new Attributes( [ 'context' => 'demo' ] ) ) );

        $this->assertInstanceOf( ResolutionDetails::class, $result );
        $this->assertEquals( $result->getValue(), true );

    }

    public function testInteger() 
    {
        $this->mockClient->shouldReceive( 'variant')
            ->withArgs( function( $flag, $contextRecv, $entityId ) {
                $this->assertEquals( $flag, 'flag' );
                $this->assertEquals( $contextRecv, [ 'context' => 'demo' ] );
                $this->assertEquals( $entityId, 'id' );
                return true;
            })
            ->andReturn( new DefaultVariantEvaluationResult( true, 'MATCH_EVALUATION_REASON', 0.1, 'rid', '13245', [], '20', '{"json":1}' ) );

        $result = $this->provider->resolveIntegerValue( 'flag', 10, new EvaluationContext( 'id', new Attributes( [ 'context' => 'demo' ] ) ) );

        $this->assertInstanceOf( ResolutionDetails::class, $result );
        $this->assertEquals( $result->getValue(), 20 );

    }

    public function testFloat() 
    {
        $this->mockClient->shouldReceive( 'variant')
            ->withArgs( function( $flag, $contextRecv, $entityId ) {
                $this->assertEquals( $flag, 'flag' );
                $this->assertEquals( $contextRecv, [ 'context' => 'demo' ] );
                $this->assertEquals( $entityId, 'id' );
                return true;
            })
            ->andReturn( new DefaultVariantEvaluationResult( true, 'MATCH_EVALUATION_REASON', 0.1, 'rid', '13245', [], '0.2345', '{"json":1}' ) );

        $result = $this->provider->resolveFloatValue( 'flag', 0.1111, new EvaluationContext( 'id', new Attributes( [ 'context' => 'demo' ] ) ) );

        $this->assertInstanceOf( ResolutionDetails::class, $result );
        $this->assertEquals( $result->getValue(), 0.2345 );

    }

    public function testString() 
    {
        $this->mockClient->shouldReceive( 'variant')
            ->withArgs( function( $flag, $contextRecv, $entityId ) {
                $this->assertEquals( $flag, 'flag' );
                $this->assertEquals( $contextRecv, [ 'context' => 'demo' ] );
                $this->assertEquals( $entityId, 'id' );
                return true;
            })
            ->andReturn( new DefaultVariantEvaluationResult( true, 'MATCH_EVALUATION_REASON', 0.1, 'rid', '13245', [], 'My string', '{"json":1}' ) );

        $result = $this->provider->resolveStringValue( 'flag', 'base', new EvaluationContext( 'id', new Attributes( [ 'context' => 'demo' ] ) ) );

        $this->assertInstanceOf( ResolutionDetails::class, $result );
        $this->assertEquals( $result->getValue(), 'My string' );

    }


    public function testObject() 
    {
        $this->mockClient->shouldReceive( 'variant')
            ->withArgs( function( $flag, $contextRecv, $entityId ) {
                $this->assertEquals( $flag, 'flag' );
                $this->assertEquals( $contextRecv, [ 'context' => 'demo' ] );
                $this->assertEquals( $entityId, 'id' );
                return true;
            })
            ->andReturn( new DefaultVariantEvaluationResult( true, 'MATCH_EVALUATION_REASON', 0.1, 'rid', '13245', [], 'My string', '{"json":1}' ) );

        $result = $this->provider->resolveObjectValue( 'flag', [], new EvaluationContext( 'id', new Attributes( [ 'context' => 'demo' ] ) ) );

        $this->assertInstanceOf( ResolutionDetails::class, $result );
        $this->assertEquals( $result->getValue(), [ "json" => 1 ] );

    }


    /*
    public function testConfigMapperWithScope() 
    {
        $demoContext = [ 'environment' => 'test' ];
        $mockScope = Mockery::mock();

        $mockMapper = Mockery::mock(ContextMapper::class);
        $mockMapper->shouldReceive( 'map')
            ->with( $demoContext, NULL )
            ->andReturn( new EvaluationContext( '', new Attributes( [] ) ) );

        $mockMapper->shouldReceive( 'map')
            ->with( $demoContext, $mockScope )
            ->andReturn( new EvaluationContext( 'id', new Attributes( $demoContext ) ) );

        App::shouldReceive( 'make' )->andReturn( $mockMapper );
        Config::shouldReceive('has')->andReturn(true);

        Config::shouldReceive('get')
            ->with( 'openfeature.clients.demo.mapper' )
            ->andReturn( 'my-mapper' );

        Config::shouldReceive('get')
            ->with( 'openfeature.clients.demo.context', [] )
            ->andReturn([ 'environment' => 'test' ]);

        $capturedContext = null;

        $this->mockProvider->shouldReceive('resolveBooleanValue')
            ->once()  // expecting the method to be called once
            ->with( 'flag', false, Mockery::capture($capturedContext) )
            ->andReturn( ResolutionDetailsFactory::fromSuccess( true ) );

        $features = OpenFeature::fromConfig( 'demo' )->for( $mockScope );
        $result = $features->boolean( 'flag', false );

        $this->assertInstanceOf( MutableEvaluationContext::class, $capturedContext );
        $this->assertEquals( [ 'environment' => 'test' ], $capturedContext->getAttributes()->toArray() );
        $this->assertEquals( $capturedContext->getTargetingKey(), 'id' );

    }

    // ... You can continue writing tests for other methods in a similar fashion



    public function testBooleanMethod()
    {
        $this->mockProvider->shouldReceive('resolveBooleanValue')
            ->once()  // expecting the method to be called once
            ->with( 'someFlag', false, Mockery::type(MutableEvaluationContext::class) )
            ->andReturn( ResolutionDetailsFactory::fromSuccess( true ) );

        $openFeature = new OpenFeature( OpenFeatureAPI::getInstance()->getClient() );

        $result = $openFeature->boolean('someFlag', false);

        $this->assertTrue($result);
    }
    


    public function testIntegerMethod()
    {
        $this->mockProvider->shouldReceive('resolveIntegerValue')
            ->once()  // expecting the method to be called once
            ->with( 'someFlag', 20, Mockery::type(MutableEvaluationContext::class) )
            ->andReturn( ResolutionDetailsFactory::fromSuccess( 30 ) );

        $openFeature = new OpenFeature( OpenFeatureAPI::getInstance()->getClient() );

        $result = $openFeature->integer('someFlag', 20);

        $this->assertEquals( 30, $result);
    }



    public function testStringMethod()
    {
        $this->mockProvider->shouldReceive('resolveStringValue')
            ->once()  // expecting the method to be called once
            ->with( 'someFlag', 'fallback', Mockery::type(MutableEvaluationContext::class) )
            ->andReturn( ResolutionDetailsFactory::fromSuccess( 'flagvalue' ) );

        $openFeature = new OpenFeature( OpenFeatureAPI::getInstance()->getClient() );

        $result = $openFeature->string('someFlag', 'fallback');

        $this->assertEquals( 'flagvalue', $result);
    }



    public function testFloatMethod()
    {
        $this->mockProvider->shouldReceive('resolveFloatValue')
            ->once()  // expecting the method to be called once
            ->with( 'someFlag', 0.244, Mockery::type(MutableEvaluationContext::class) )
            ->andReturn( ResolutionDetailsFactory::fromSuccess( 3.81 ) );

        $openFeature = new OpenFeature( OpenFeatureAPI::getInstance()->getClient() );

        $result = $openFeature->float('someFlag', 0.244);

        $this->assertEquals( 3.81, $result);
    }
    


    public function testObjectMethod()
    {
        $this->mockProvider->shouldReceive('resolveObjectValue')
            ->once()  // expecting the method to be called once
            ->with( 'someFlag', [ 'demo' => 12 ], Mockery::type(MutableEvaluationContext::class) )
            ->andReturn( ResolutionDetailsFactory::fromSuccess( [ 'demo' => 'test '] ) );

        $openFeature = new OpenFeature( OpenFeatureAPI::getInstance()->getClient() );

        $result = $openFeature->object('someFlag', [ 'demo' => 12 ]);

        $this->assertEquals( [ 'demo' => 'test '], $result);
    }
    */

    
}
