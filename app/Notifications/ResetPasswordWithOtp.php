<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;
class ResetPasswordWithOtp extends Notification
{
    use Queueable;

    public $otp; 

    public function __construct($code)
    {
        $this->otp = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Password Request - iTap')
            ->greeting('Hello ' . ($notifiable->fName ?? $notifiable->name) . ',') 
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->line('Use the following code to reset your password:')
            
            ->line(new HtmlString(
                '<div style="text-align:center; font-size: 36px; font-weight: 800; letter-spacing: 5px; color: #000; margin: 30px 0; background: #f4f4f4; padding: 20px; border-radius: 8px;">' . $this->otp . '</div>'
            ))
            
            ->line('This code is valid for 15 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}