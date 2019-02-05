<?php
namespace kuaukutsu\mailgun\transport;

/**
 * Class AbstractHttpTransport
 * @package kuaukutsu\mailgun\transport
 */
abstract class AbstractHttpTransport implements \Swift_Transport
{
    /**
     * Connection status
     * @var bool
     */
    protected $started = false;

    /**
     * The event dispatching layer
     * @var \Swift_Events_EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * AbstractHttpTransport constructor.
     * @param \Swift_Events_EventDispatcher $dispatcher
     */
    public function __construct(\Swift_Events_EventDispatcher $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * @inheritdoc
     */
    public function stop(): void
    {
        if ($this->started) {

            if ($evt = $this->eventDispatcher->createTransportChangeEvent($this)) {
                $this->eventDispatcher->dispatchEvent($evt, 'beforeTransportStopped');
                if ($evt->bubbleCancelled()) {
                    return;
                }
            }

            if ($evt) {
                $this->eventDispatcher->dispatchEvent($evt, 'transportStopped');
            }
        }

        $this->started = false;
    }

    /**
     * @return bool
     */
    public function ping(): bool
    {
        return true;
    }

    /**
     * @param \Swift_Events_EventListener $plugin
     */
    public function registerPlugin(\Swift_Events_EventListener $plugin): void
    {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    /**
     * Throw a TransportException, first sending it to any listeners
     * @param \Swift_TransportException $e
     * @throws \Swift_TransportException
     */
    protected function throwException(\Swift_TransportException $e): void
    {
        if ($evt = $this->eventDispatcher->createTransportExceptionEvent($this, $e)) {
            $this->eventDispatcher->dispatchEvent($evt, 'exceptionThrown');
            if (!$evt->bubbleCancelled()) {
                throw $e;
            }
        } else {
            throw $e;
        }
    }

    /**
     * Determine the best-use reverse path for this message
     * @param \Swift_Mime_SimpleMessage $message
     * @return int|string|null
     */
    protected function getReversePath(\Swift_Mime_SimpleMessage $message)
    {
        $return = $message->getReturnPath();
        $sender = $message->getSender();
        $from = $message->getFrom();
        $path = null;
        if (!empty($return)) {
            $path = $return;
        } elseif (is_array($sender) && \count($sender)) {
            // Don't use array_keys
            reset($sender); // Reset Pointer to first pos
            $path = key($sender); // Get key
        } elseif (is_array($from) && \count($from)) {
            reset($from); // Reset Pointer to first pos
            $path = key($from); // Get key
        }

        return $path;
    }
}