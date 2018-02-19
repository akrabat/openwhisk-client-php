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
namespace OpenWhisk;

use GuzzleHttp\Client;
use RuntimeException;

class OpenWhisk
{
    const ACTIONS_PATH = '/api/v1/namespaces/%s/actions/%s';
    const TRIGGERS_PATH = '/api/v1/namespaces/%s/triggers/%s';

    public function __construct(Client $client = null)
    {
        $this->client = $client;
    }

    /**
     * Invoke an OpenWhisk action
     *
     * @param  string       $action     action name (e.g. "/whisk.system/utils/echo")
     * @param  array        $parameters paramters to send to the action
     * @param  bool.        $blocking   Should the invocation block? (default: true)
     * @return array                    Result
     */
    public function invoke(string $action, array $parameters = [], bool $blocking = true) : array
    {
        $path = vsprintf(static::ACTIONS_PATH, $this->parseQualifiedName($action));
        $path .= '?blocking=' . ($blocking ? 'true' : 'false');

        return  $this->post($path, $parameters);
    }

    /**
     * Fire an OpenWhisk trigger event
     *
     * @param  string       $event      event name (e.g. "locationUpdate")
     * @param  array        $parameters paramters to send to the event
     * @param  bool.        $blocking   Should the invocation block? (default: true)
     * @return array                    Result
     */
    public function trigger(string $event, array $parameters = []) : array
    {
        $path = vsprintf(static::TRIGGERS_PATH, $this->parseQualifiedName($event));
        $path .= '?blocking=true';

        return  $this->post($path, $parameters);
    }

    /**
     * Post a message to the API
     */
    public function post(string $path, array $parameters) : array
    {
        $host = $this->getHost();
        $key = base64_encode($this->getKey());

        $client = $this->getClient();

        try {
            $response = $client->request(
                'POST',
                "$host$path",
                [
                    'json' => $parameters,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => "Basic $key",
                    ],
                ]
            );
            return json_decode((string)$response->getBody(), true);
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            if ($e->getCode() == 502) {
                // if we get a 502, then we also get a valid response body
                return json_decode($e->getResponse()->getBody(), true);
            }
            throw new \RuntimeException("It failed", $e->getCode(), $e);
        }
    }

    /**
     * Determine namespace and package name from qualified name
     */
    public function parseQualifiedName(string $qualifiedName) : array
    {
        $DEFAULT_NAMESPACE = '_';
        $DELIMTER = '/';

        $segments = explode($DELIMTER, trim($qualifiedName, $DELIMTER));
        if (count($segments) > 1) {
            $namespace = array_shift($segments);
            return [$namespace, implode($DELIMTER, $segments)];
        }

        return [$DEFAULT_NAMESPACE, $segments[0]];
    }

    /**
     * Instantiate a Guzzle client if one hasn't been provided.
     */
    protected function getClient() : Client
    {
        if (!$this->client) {
            $this->client = new Client();
        }
        return $this->client;
    }

    /**
     * Get the API's URL
     */
    protected function getHost(): string
    {
        $host = $_ENV['__OW_API_HOST'] ?? '';
        if (!$host) {
            throw new RuntimeException("__OW_API_HOST environment variable was not set.");
        }

        return $host;
    }

    /**
     * Get the API's URL and the key to use
     */
    protected function getKey(): string
    {
        $key = $_ENV['__OW_API_KEY'] ?? '';
        if (!$key) {
            throw new RuntimeException("__OW_API_KEY environment variable was not set.");
        }

        return $key;
    }
}
