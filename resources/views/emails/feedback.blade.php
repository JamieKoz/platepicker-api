<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PlatePicker Feedback</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .feedback-header {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .rating {
            font-size: 24px;
            color: #ffd700;
        }
        .message {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #ddd;
            margin-bottom: 20px;
        }
        .user-info {
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="feedback-header">
        <h2>New {{ ucfirst($feedbackData['type']) }} Feedback</h2>
        @if($feedbackData['rating'] > 0)
        <div class="rating">
            @for($i = 0; $i < $feedbackData['rating']; $i++)
                ★
            @endfor
            @for($i = $feedbackData['rating']; $i < 5; $i++)
                ☆
            @endfor
            ({{ $feedbackData['rating'] }}/5)
        </div>
        @endif
    </div>

    <div class="message">
        <h3>Feedback Message:</h3>
        <p>{{ $feedbackData['message'] }}</p>
    </div>

    @if(!empty($feedbackData['email']))
    <div>
        <strong>Contact Email:</strong> {{ $feedbackData['email'] }}
    </div>
    @endif

    <div class="user-info">
        <p>
            <strong>Submitted:</strong> {{ $feedbackData['submitted_at'] }}<br>
            @if(!empty($feedbackData['user_id']))
            <strong>User ID:</strong> {{ $feedbackData['user_id'] }}<br>
            @endif
        </p>
    </div>
</body>
</html>
