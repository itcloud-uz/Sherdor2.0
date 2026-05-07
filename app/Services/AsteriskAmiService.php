<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AsteriskAmiService
{
    private $host;
    private $port;
    private $username;
    private $secret;
    private $timeout;

    public function __construct()
    {
        $this->host = env('ASTERISK_AMI_HOST', '127.0.0.1');
        $this->port = env('ASTERISK_AMI_PORT', 5038);
        $this->username = env('ASTERISK_AMI_USER', 'laravel_ami');
        $this->secret = env('ASTERISK_AMI_SECRET', 'ITCloud_Super_Secret_2026');
        $this->timeout = 10;
    }

    public function originateCall($targetNumber, $workerExtension)
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$socket) {
            Log::error("Asterisk AMI Connection Failed: $errstr ($errno)");
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        // Login
        fputs($socket, "Action: Login\r\n");
        fputs($socket, "Username: {$this->username}\r\n");
        fputs($socket, "Secret: {$this->secret}\r\n\r\n");

        // Originate Call
        fputs($socket, "Action: Originate\r\n");
        fputs($socket, "Channel: PJSIP/{$workerExtension}\r\n");
        fputs($socket, "Context: from-internal\r\n");
        fputs($socket, "Exten: {$targetNumber}\r\n");
        fputs($socket, "Priority: 1\r\n");
        fputs($socket, "Callerid: $targetNumber\r\n");
        fputs($socket, "Timeout: 30000\r\n");
        fputs($socket, "Async: yes\r\n\r\n");

        // Logoff
        fputs($socket, "Action: Logoff\r\n\r\n");

        $response = "";
        while (!feof($socket)) {
            $response .= fread($socket, 8192);
            $info = stream_get_meta_data($socket);
            if ($info['timed_out']) {
                break;
            }
        }

        fclose($socket);

        Log::info("Asterisk AMI Originate Response for $targetNumber: " . $response);

        return str_contains($response, 'Success') || str_contains($response, 'Message: Originate successfully queued');
    }
}
