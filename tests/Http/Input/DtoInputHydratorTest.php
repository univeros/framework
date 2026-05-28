<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Input;

use Altair\Http\Exception\InputValidationException;
use Altair\Http\Input\DtoInputHydrator;
use Altair\Tests\Http\Fixtures\Action\EmptyInput;
use Altair\Tests\Http\Fixtures\Action\GreetInput;
use Altair\Tests\Http\Fixtures\Action\ProfileInput;
use Altair\Tests\Http\Fixtures\Action\Status;
use Altair\Tests\Http\Fixtures\Action\StatusInput;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DtoInputHydrator::class)]
#[CoversClass(InputValidationException::class)]
final class DtoInputHydratorTest extends TestCase
{
    private function request(array $query = [], ?array $body = null, array $attributes = []): ServerRequest
    {
        $request = (new ServerRequest(uri: '/', method: 'POST'))->withQueryParams($query);
        if ($body !== null) {
            $request = $request->withParsedBody($body);
        }

        foreach ($attributes as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    public function testHydratesNoArgDto(): void
    {
        $dto = (new DtoInputHydrator())->hydrate(EmptyInput::class, $this->request());

        self::assertInstanceOf(EmptyInput::class, $dto);
    }

    public function testHydratesRequiredAndDefaultedFieldsFromQuery(): void
    {
        $dto = (new DtoInputHydrator())->hydrate(GreetInput::class, $this->request(['name' => 'Ada', 'times' => '3']));

        self::assertInstanceOf(GreetInput::class, $dto);
        self::assertSame('Ada', $dto->name);
        self::assertSame(3, $dto->times);
    }

    public function testUsesDefaultWhenFieldAbsent(): void
    {
        $dto = (new DtoInputHydrator())->hydrate(GreetInput::class, $this->request(['name' => 'Ada']));

        self::assertSame(1, $dto->times);
    }

    public function testCoercesScalarsAndHonoursNullableDefault(): void
    {
        $dto = (new DtoInputHydrator())->hydrate(
            ProfileInput::class,
            $this->request(body: ['email' => 'a@b.c', 'age' => '30', 'active' => 'true']),
        );

        self::assertInstanceOf(ProfileInput::class, $dto);
        self::assertSame(30, $dto->age);
        self::assertTrue($dto->active);
        self::assertNull($dto->note);
    }

    public function testRouteAttributesWinOverQuery(): void
    {
        $dto = (new DtoInputHydrator())->hydrate(
            GreetInput::class,
            $this->request(query: ['name' => 'fromquery'], attributes: ['name' => 'fromroute']),
        );

        self::assertSame('fromroute', $dto->name);
    }

    public function testMissingRequiredFieldThrowsWithPerFieldErrors(): void
    {
        try {
            (new DtoInputHydrator())->hydrate(GreetInput::class, $this->request());
            self::fail('Expected InputValidationException.');
        } catch (InputValidationException $inputValidationException) {
            self::assertArrayHasKey('name', $inputValidationException->errors);
        }
    }

    public function testWrongTypedScalarBecomes422NotTypeError(): void
    {
        try {
            (new DtoInputHydrator())->hydrate(GreetInput::class, $this->request(['name' => 'Ada', 'times' => 'lots']));
            self::fail('Expected InputValidationException.');
        } catch (InputValidationException $inputValidationException) {
            self::assertSame('must be an integer', $inputValidationException->errors['times']);
        }
    }

    public function testResolvesBackedEnumField(): void
    {
        $dto = (new DtoInputHydrator())->hydrate(StatusInput::class, $this->request(['status' => 'active']));

        self::assertInstanceOf(StatusInput::class, $dto);
        self::assertSame(Status::Active, $dto->status);
    }

    public function testUnknownEnumCaseBecomes422(): void
    {
        try {
            (new DtoInputHydrator())->hydrate(StatusInput::class, $this->request(['status' => 'bogus']));
            self::fail('Expected InputValidationException.');
        } catch (InputValidationException $inputValidationException) {
            self::assertArrayHasKey('status', $inputValidationException->errors);
        }
    }
}
