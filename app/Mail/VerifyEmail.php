<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;

    public function __construct(string $token, string $frontendUrl)
    {
        $this->verificationUrl = $frontendUrl . '/verify-email/' . $token;
    }

    public function build(): self
    {
        return $this->subject('Vérifiez votre adresse email - CTS Vote')
                    ->view('emails.verify');
    }
}
