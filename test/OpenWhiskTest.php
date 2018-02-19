<?php declare(strict_types=1);
/**
 * Copyright 2017  Rob Allen
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace OpenWhiskTest;

use OpenWhisk\OpenWhisk;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class OpenWhiskTest extends TestCase
{
    public function setUp()
    {
        $this->historyContainer = [];

        $_ENV["__OW_API_HOST"] = "http://192.168.33.13:10001";
        $_ENV["__OW_API_KEY"] = "user:password";
    }

    protected function getMockClient(Response $response)
    {
        $history = Middleware::history($this->historyContainer);
        $stack = MockHandler::createWithMiddleware([$response]);
        $stack->push($history);

        return new Client(['handler' => $stack]);
    }


    /**
     * Provider for testTrigger
     *
     * For these trigger, parameter & blocking settings, ensure we POST the correct URL
     * with the correct headers
     *
     * @return  array
     */
    public function triggers()
    {
        return [
            [
                '/guest/demo/hi', // /guest/envphp
                ['place' => 'Paris'],
                'http://192.168.33.13:10001/api/v1/namespaces/guest/triggers/demo/hi?blocking=true'
            ],
            [
                '/guest/hello',
                [],
                'http://192.168.33.13:10001/api/v1/namespaces/guest/triggers/hello?blocking=true'
            ],
        ];
    }

    /**
     * @dataProvider triggers
     */
    public function testTrigger(string $action, array $parameters, string $expectedUri)
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(["response" => ['success' => true]])
        );
        $client = $this->getMockClient($response);

        $whisk = new OpenWhisk($client);

        $result = $whisk->trigger($action, $parameters);

        // check the request sent is correct
        $sentRequest = $this->historyContainer[0]['request'];
        static::assertEquals('POST', $sentRequest->getMethod());

        static::assertEquals($expectedUri, (string)$sentRequest->getUri());

        $authHeader = 'Basic ' . base64_encode($_ENV["__OW_API_KEY"]);
        static::assertEquals($authHeader, $sentRequest->getHeaderLine('Authorization'));
        static::assertEquals('application/json', $sentRequest->getHeaderLine('Accept'));
        static::assertEquals('application/json', $sentRequest->getHeaderLine('Content-Type'));

        static::assertEquals(json_encode($parameters), (string)$sentRequest->getBody());
    }

    /**
     * Provider for testInvoke
     *
     * For these action, parameter & blocking settings, ensure we POST the correct URL
     * with the correct headers
     *
     * @return  array
     */
    public function invocations()
    {
        return [
            [
                '/guest/demo/hi', // /guest/envphp
                ['place' => 'Paris'],
                true,
                'http://192.168.33.13:10001/api/v1/namespaces/guest/actions/demo/hi?blocking=true'
            ],
            [
                '/guest/hello',
                [],
                true,
                'http://192.168.33.13:10001/api/v1/namespaces/guest/actions/hello?blocking=true'
            ],
            [
                '/guest/hello',
                [],
                false,
                'http://192.168.33.13:10001/api/v1/namespaces/guest/actions/hello?blocking=false'
            ],
        ];
    }

    /**
     * @dataProvider invocations
     */
    public function testInvoke(string $action, array $parameters, bool $blocking, string $expectedUri)
    {
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(["response" => ['success' => true]])
        );
        $client = $this->getMockClient($response);

        $whisk = new OpenWhisk($client);

        $result = $whisk->invoke($action, $parameters, $blocking);

        // check the request sent is correct
        $sentRequest = $this->historyContainer[0]['request'];
        static::assertEquals('POST', $sentRequest->getMethod());

        static::assertEquals($expectedUri, (string)$sentRequest->getUri());

        $authHeader = 'Basic ' . base64_encode($_ENV["__OW_API_KEY"]);
        static::assertEquals($authHeader, $sentRequest->getHeaderLine('Authorization'));
        static::assertEquals('application/json', $sentRequest->getHeaderLine('Accept'));
        static::assertEquals('application/json', $sentRequest->getHeaderLine('Content-Type'));

        static::assertEquals(json_encode($parameters), (string)$sentRequest->getBody());
    }

    /**
     * Provider for test that look in $_ENV
     *
     * For these __OW_API_HOST & __OW_API_KEY variables, extract the scheme, host, port and auth key
     *
     * @return  array
     */
    public function env()
    {
        return [
            [
                [
                    '__OW_API_HOST' => 'https://192.168.33.13:10001',
                    '__OW_API_KEY' => '1234567890',
                ],
            ],
            [
                [
                    '__OW_API_HOST' => 'https://192.168.33.13',
                    '__OW_API_KEY' => '1234567890',
                ],
            ],
            [
                [
                    '__OW_API_HOST' => 'http://192.168.33.13',
                    '__OW_API_KEY' => '1234567890',
                ],
            ],
            [
                [
                    '__OW_API_HOST' => 'http://192.168.33.13:8080',
                    '__OW_API_KEY' => '1234567890',
                ],
            ],
        ];
    }

    /**
     * @dataProvider env
     */
    public function testGetHost($env)
    {
        $object = new OpenWhisk();
        $reflector = new \ReflectionObject($object);
        $method = $reflector->getMethod('getHost');
        $method->setAccessible(true);

        $originalEnv = $_ENV;
        $_ENV = $env;
        $result = $method->invoke($object);
        $_ENV = $originalEnv;

        self::assertEquals($env['__OW_API_HOST'], $result);
    }

    /**
     * @dataProvider env
     */
    public function testGetKey($env)
    {
        $object = new OpenWhisk();
        $reflector = new \ReflectionObject($object);
        $method = $reflector->getMethod('getKey');
        $method->setAccessible(true);

        $originalEnv = $_ENV;
        $_ENV = $env;
        $result = $method->invoke($object);
        $_ENV = $originalEnv;

        self::assertEquals($env['__OW_API_KEY'], $result);
    }

    /**
     * Provider for testCanParseQualifiedNames
     *
     * For this action name, split into namespace and name (including package)
     *
     * @return array
     */
    public function qualifiedNames()
    {
        return [
            ['Foo', ['_', 'Foo']],
            ['/Foo', ['_', 'Foo']],
            ['Bar/Foo', ['Bar', 'Foo']],
            ['Bar/Foo/Baz', ['Bar', 'Foo/Baz']],
        ];
    }

    /**
     * @dataProvider qualifiedNames
     */
    public function testCanParseQualifiedNames($name, $expected)
    {
        $object = new OpenWhisk();
        $reflector = new \ReflectionObject($object);
        $method = $reflector->getMethod('parseQualifiedName');
        $method->setAccessible(true);

        $result = $method->invoke($object, $name);

        self::assertEquals($expected, $result);
    }
}
