<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public $feedbackData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($feedbackData)
    {
        $this->feedbackData = $feedbackData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = 'PlatePicker Feedback: ' . ucfirst($this->feedbackData['type']);

        if ($this->feedbackData['rating'] > 0) {
            $subject .= ' (' . $this->feedbackData['rating'] . ' stars)';
        }

        return $this->subject($subject)
                    ->view('emails.feedback');
    }
}
