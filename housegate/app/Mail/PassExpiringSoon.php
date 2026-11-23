<?php

namespace App\Mail;

use App\Models\VisitorPass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PassExpiringSoon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VisitorPass $pass)
    {
    }

    public function build()
    {
        return $this->subject('Your visitor pass expires soon')
            ->view('emails.expiring-soon');
    }
}