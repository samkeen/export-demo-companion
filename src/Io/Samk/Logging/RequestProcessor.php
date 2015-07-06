<?php

namespace Io\Samk\Logging;

use Silex\Application;

/**
 * Class RequestProcessor
 * @package Io\Samk\Logging
 */
class RequestProcessor
{
    /**
     * @var Application
     */
    private $app;
    /**
     * @var TracingRequest
     */
    private $requestTrace;

    /**
     * @param Application $app
     * @param null $previousToken
     */
    public function __construct(Application $app, $previousToken = null)
    {
        $this->app = $app;
        $this->requestTrace = TracingRequest::getInstance();
    }

    /**
     * @param array $record The Logging Record
     * @return array
     */
    public function __invoke(array $record)
    {
        /**
         * Look for #TRACE#({.*}) in log messages
         *   if found, record, and clean out of original log message
         */
        preg_match('/#TRACE#({.*})/', $record['message'],$match);
        if($match) {
            $record['extra'] += json_decode($match[1], true);
            $record['message'] = trim(preg_replace("/{$match[0]}/", '', $record['message']));
        }
        $record['extra']['token'] = $this->requestTrace->getTraceId();
        $record['extra']['inboundToken'] = $this->requestTrace->getInboundTraceChain();
        $record['extra']['time'] = microtime(true);

        return $record;
    }
}
