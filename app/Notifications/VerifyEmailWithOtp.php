<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class VerifyEmailWithOtp extends Notification
{
    use Queueable;
    
    public $otp; 

    public function __construct($codeFromUserFunction)
    {
        $this->otp = $codeFromUserFunction;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verification Code - iTap')
            ->greeting('Hello ' . $notifiable->fName . ',')
            ->line('Use the following code to verify your account:')
            
            ->line(new HtmlString(
                '<div style="text-align:center; font-size: 36px; font-weight: 800; letter-spacing: 5px; color: #000; margin: 30px 0; background: #f4f4f4; padding: 20px; border-radius: 8px;">' . $this->otp . '</div>'
            )) 
            ->line('This code is valid for 15 minutes.')
            ->line('Do not share this code with anyone.');
    }
}

