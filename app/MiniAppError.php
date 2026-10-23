<?php
/**
 * Safe, user-facing error for mini app API requests.
 */
class MiniAppError extends RuntimeException
{
    private $status;
    private $errorCode;
    private $extra;

    public function __construct($message, $status = 400, $errorCode = 'bad_request', array $extra = array())
    {
        parent::__construct((string) $message, 0);
        $this->status = (int) $status;
        $this->errorCode = (string) $errorCode;
        $this->extra = $extra;
    }

    public function status()
    {
        return $this->status;
    }

    public function errorCode()
    {
        return $this->errorCode;
    }

    public function extra()
    {
        return $this->extra;
    }
}
