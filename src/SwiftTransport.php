<?php
declare(strict_types=1);

namespace kuaukutsu\mailgun\transport;

use Mailgun\Mailgun;
use Mailgun\Model\Message\SendResponse;

class SwiftTransport extends AbstractHttpTransport
{
    /**
     * @var Mailgun
     */
    private $mailgun;

    /**
     * SwiftTransport constructor.
     */
    public function __construct()
    {
        parent::__construct(new \Swift_Events_SimpleEventDispatcher());
    }

    /**
     * @param string $key
     */
    public function setApikey(string $key): void
    {
        $this->params['apikey'] = $key;
    }

    /**
     * @param string $endpoint
     */
    public function setEndpoint(string $endpoint): void
    {
        $this->params['endpoint'] = $endpoint;
    }

    /**
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->params['endpoint'];
    }

    /**
     * @param string $domain
     */
    public function setDomain(string $domain): void
    {
        $this->params['domain'] = $domain;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->params['domain'];
    }

    /**
     * @inheritdoc
     * @throws \Swift_TransportException
     */
    public function start(): void
    {
        if (!$this->started) {

            if ($evt = $this->eventDispatcher->createTransportChangeEvent($this)) {
                $this->eventDispatcher->dispatchEvent($evt, 'beforeTransportStarted');
                if ($evt->bubbleCancelled()) {
                    return;
                }
            }

            $this->mailgun = Mailgun::create($this->params['apikey'], $this->getEndpoint());
            $response = $this->mailgun->domains()->connection($this->getDomain());
            if (!$response) {
                $this->throwException(new \Swift_TransportException('Connection failed'));
            }

            if ($evt) {
                $this->eventDispatcher->dispatchEvent($evt, 'transportStarted');
            }

            $this->started = true;
        }
    }

    /**
     * @param \Swift_Mime_SimpleMessage $message
     * @param null $failedRecipients
     * @return int
     * @throws \Swift_TransportException
     */
    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        if (!$this->isStarted()) {
            $this->start();
        }

        $sent = 0;
        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->eventDispatcher->createSendEvent($this, $message)) {
            $this->eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        if (!$this->getReversePath($message)) {
            $this->throwException(new \Swift_TransportException('Cannot send message without a sender address'));
        }

        $to = $this->getRecipients($message);
        if (count($to) === 0) {
            $this->throwException(new \Swift_TransportException('Cannot send message without a recipient address'));
        }

        if ($evt) {
            /** @var SendResponse $response */
            $response = $this->mailgun->messages()->sendMime($this->getDomain(), $to, $message->toString(), []);
            if ($response->getId()) {
                ++$sent;
                $message->setId($response->getId());
                $evt->setResult(\Swift_Events_SendEvent::RESULT_SUCCESS);
            } else {
                $message->generateId(); //Make sure a new Message ID is used
                $evt->setResult(\Swift_Events_SendEvent::RESULT_FAILED);
            }

            $this->eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        }

        return $sent;
    }

    /**
     * @param \Swift_Mime_SimpleMessage $message
     * @return array
     */
    private function getRecipients(\Swift_Mime_SimpleMessage $message): array
    {
        $tos = [];

        $to = $message->getTo();
        if (is_array($to) && \count($to)) {
            $tos = array_merge($tos, array_keys($to));
        }

        $bcc = $message->getBcc();
        if (is_array($bcc) && \count($bcc)) {
            $tos = array_merge($tos, array_keys($bcc));
        }

        $cc = $message->getCc();
        if (is_array($cc) && \count($cc)) {
            $tos = array_merge($tos, array_keys($cc));
        }

        return $tos;
    }
}
