<?php

namespace Io\Samk\Logging;

/**
 * Class TracingRequest
 * @package Igniter\TracingLogBundle
 *
 * Singleton to maintain the Trace state for a given Request
 */
class TracingRequest
{

    /**
     * @var string
     */
    private $requestTraceId = '----';
    private $inboundTraceChain = '';

    /**
     * @return TracingRequest
     */
    public static function getInstance()
    {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
        }

        return $instance;
    }

    /**
     * Create to trace identifier for this request
     * @param string $inboundTraceChain This is intended to be a trace id from the inbound request, if one exists.
     */
    public function init($inboundTraceChain = '')
    {
        $this->inboundTraceChain = $inboundTraceChain;
        $this->requestTraceId = $this->generateTraceToken();
    }

    /**
     * @return string
     */
    public function getInboundTraceChain()
    {
        return $this->inboundTraceChain;
    }

    /**
     * Retrieve the the token string to send with sub-requests from this service.
     * @return string
     */
    public function getTokenForRequest()
    {
        $inboundTraceChain = $this->getInboundTraceChain() ? ".{$this->getInboundTraceChain()}" : '';
        return "{$this->getTraceId()}{$inboundTraceChain}";
    }

    /**
     * @return string
     * @todo : determine an easy way for users of bundle to supply/override the token create algorithm.
     */
    protected function generateTraceToken()
    {
        return uniqid() . bin2hex(openssl_random_pseudo_bytes(2));
    }

    /**
     * @return string
     */
    public function getTraceId()
    {
        return $this->requestTraceId;
    }

    /**
     * Reduce scope to Enforce Singleton
     */
    protected function __construct()
    {
    }

    /**
     * Reduce scope to Enforce Singleton
     */
    private function __clone()
    {
    }

    /**
     * Reduce scope to Enforce Singleton
     */
    private function __wakeup()
    {
    }
}