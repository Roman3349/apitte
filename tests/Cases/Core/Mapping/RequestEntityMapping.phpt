<?php declare(strict_types = 1);

require_once __DIR__ . '/../../../bootstrap.php';

use Apitte\Core\Exception\Api\ClientErrorException;
use Apitte\Core\Exception\Logical\InvalidStateException;
use Apitte\Core\Http\ApiRequest;
use Apitte\Core\Http\ApiResponse;
use Apitte\Core\Http\RequestAttributes;
use Apitte\Core\Mapping\RequestEntityMapping;
use Apitte\Core\Schema\Endpoint;
use Apitte\Core\Schema\EndpointHandler;
use Apitte\Core\Schema\EndpointRequestBody;
use Contributte\Psr7\Psr7ResponseFactory;
use Contributte\Psr7\Psr7ServerRequestFactory;
use Contributte\Tester\Toolkit;
use GuzzleHttp\Psr7\Utils;
use Tester\Assert;
use Tests\Fixtures\Mapping\Request\FooEntity;
use Tests\Fixtures\Mapping\Request\NotEmptyEntity;

// Add entity to request
Toolkit::test(function (): void {
	$request = new ApiRequest(Psr7ServerRequestFactory::fromSuperGlobal());
	$response = new ApiResponse(Psr7ResponseFactory::fromGlobal());
	$mapping = new RequestEntityMapping();

	$handler = new EndpointHandler('class', 'method');
	$endpoint = new Endpoint($handler);
	$requestBody = new EndpointRequestBody();
	$requestBody->setEntity(FooEntity::class);
	$endpoint->setRequestBody($requestBody);

	$request = $request->withAttribute(RequestAttributes::ATTR_ENDPOINT, $endpoint);

	Assert::equal(
		$request->withAttribute(RequestAttributes::ATTR_REQUEST_ENTITY, new FooEntity()),
		$mapping->map($request, $response)
	);
});

// Don't modify request by entity - method foo is not supported
Toolkit::test(function (): void {
	$request = new ApiRequest(Psr7ServerRequestFactory::fromSuperGlobal());
	$response = new ApiResponse(Psr7ResponseFactory::fromGlobal());
	$mapping = new RequestEntityMapping();

	$handler = new EndpointHandler('class', 'method');
	$endpoint = new Endpoint($handler);
	$requestBody = new EndpointRequestBody();
	$requestBody->setEntity(FooEntity::class);
	$endpoint->setRequestBody($requestBody);

	$request = $request->withAttribute(RequestAttributes::ATTR_ENDPOINT, $endpoint);
	$request = $request->withMethod('foo');

	Assert::same($request, $mapping->map($request, $response));
});

// No request mapper, return request
Toolkit::test(function (): void {
	$request = new ApiRequest(Psr7ServerRequestFactory::fromSuperGlobal());
	$response = new ApiResponse(Psr7ResponseFactory::fromGlobal());
	$mapping = new RequestEntityMapping();

	$handler = new EndpointHandler('class', 'method');
	$request = $request->withAttribute(RequestAttributes::ATTR_ENDPOINT, new Endpoint($handler));

	Assert::same($request, $mapping->map($request, $response));
});

// Exception - missing attribute
Toolkit::test(function (): void {
	$request = new ApiRequest(Psr7ServerRequestFactory::fromSuperGlobal());
	$response = new ApiResponse(Psr7ResponseFactory::fromGlobal());
	$mapping = new RequestEntityMapping();

	Assert::exception(function () use ($mapping, $request, $response): void {
		$request = $mapping->map($request, $response);
	}, InvalidStateException::class, sprintf('Attribute "%s" is required', RequestAttributes::ATTR_ENDPOINT));
});

// Mapping from query or body
Toolkit::test(function (): void {
	$request = new ApiRequest(Psr7ServerRequestFactory::fromSuperGlobal());
	$entity = new NotEmptyEntity();

	foreach ([Endpoint::METHOD_GET, Endpoint::METHOD_DELETE, Endpoint::METHOD_HEAD] as $method) {
		$queryRequest = $request
			->withQueryParams(['foo' => 1])
			->withMethod($method);

		$entity = $entity->fromRequest($queryRequest);

		Assert::same(1, $entity->foo);
	}

	foreach ([Endpoint::METHOD_POST, Endpoint::METHOD_PUT, Endpoint::METHOD_PATCH] as $method) {
		$bodyRequest = $request
			->withBody(Utils::streamFor(json_encode(['foo' => 1])))
			->withMethod($method);

		$entity = $entity->fromRequest($bodyRequest);

		Assert::same(1, $entity->foo);
	}
});

// Try mapping invalid json body
Toolkit::test(function (): void {
	$request = new ApiRequest(Psr7ServerRequestFactory::fromSuperGlobal());
	$entity = new NotEmptyEntity();

	$bodyRequest = $request
		->withBody(Utils::streamFor('invalid-json'));

	Assert::exception(function () use ($entity, $bodyRequest): void {
		$entity = $entity->fromRequest($bodyRequest->withMethod('POST'));
	}, ClientErrorException::class, 'Invalid json data');
});
