<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $minutes;

    public function __construct($code, $minutes = 5)
    {
        $this->code = $code;
        $this->minutes = $minutes;
    }

    public function build()
    {
        return $this->subject("Your one-time login code")
                    ->view('emails.otp')
                    ->with([
                        'code' => $this->code,
                        'minutes' => $this->minutes,
                    ]);
    }
}
