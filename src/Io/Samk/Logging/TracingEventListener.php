<?php

namespace Io\Samk\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class TracingEventListener
 * @package Io\Samk\Logging
 *
 * We use our own event listener so that we can initialize the TracingRequest early in onKernelRequest
 */
class TracingEventListener implements EventSubscriberInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logs master requests on event KernelEvents::REQUEST.
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $inboundTraceToken = null;
        $request = $event->getRequest();
        if($request->headers->has('trace-token')) {
            $inboundTraceToken = $request->headers->get('trace-token');
        }
        TracingRequest::getInstance()->init($inboundTraceToken);
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $this->logRequest($event->getRequest());
    }

    /**
     * Logs master response on event KernelEvents::RESPONSE.
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $this->logResponse($event->getResponse());
    }

    /**
     * Logs uncaught exceptions on event KernelEvents::EXCEPTION.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $this->logException($event->getException());
    }

    /**
     * Logs a request.
     *
     * @param Request $request
     */
    protected function logRequest(Request $request)
    {
        $this->logger->info($request->getMethod() . ' ' . $request->getRequestUri()
            . ' #TRACE#{"event":"request.start"}');
    }

    /**
     * Logs a response.
     *
     * @param Response $response
     */
    protected function logResponse(Response $response)
    {
        $response->headers->set('trace-token', TracingRequest::getInstance()->getTraceId());
        if ($response instanceof RedirectResponse) {
            $this->logger->info('< ' . $response->getStatusCode() . ' ' . $response->getTargetUrl());
        } else {
            $this->logger->info('HTTP response: ' . $response->getStatusCode() . ' #TRACE#{"event":"response.send"}');
        }
    }

    /**
     * Logs an exception.
     *
     * @param \Exception $e
     */
    protected function logException(\Exception $e)
    {
        $message = sprintf('%s: %s (uncaught exception) at %s line %s', get_class($e), $e->getMessage(), $e->getFile(),
            $e->getLine());

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            $this->logger->error($message, array('exception' => $e));
        } else {
            $this->logger->critical($message, array('exception' => $e));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 255),
            KernelEvents::RESPONSE => array('onKernelResponse', -255),
            /*
             * Priority -4 is used to come after those from SecurityServiceProvider (0)
             * but before the error handlers added with Silex\Application::error (defaults to -8)
             */
            KernelEvents::EXCEPTION => array('onKernelException', -4),
        );
    }


}