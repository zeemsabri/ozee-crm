<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Website Enquiry</title>
</head>
<body style="margin:0;padding:16px;font-family:Arial,Helvetica,sans-serif;background:#f9fafb;color:#111827;">
    <div style="max-width:640px;margin:auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #e5e7eb;background:#f3f4f6;">
            <h2 style="margin:0;font-size:18px;">New Website Enquiry</h2>
        </div>
        <div style="padding:20px;">
            @if(!empty($name))
                <p style="margin:0 0 8px;"><strong>Name:</strong> {{ $name }}</p>
            @endif
            @if(!empty($email))
                <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $email }}</p>
            @endif
            @if(!empty($phone))
                <p style="margin:0 0 8px;"><strong>Phone:</strong> {{ $phone }}</p>
            @endif
            @if(!empty($company))
                <p style="margin:0 0 8px;"><strong>Company:</strong> {{ $company }}</p>
            @endif

            @if(!empty($messageBody))
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;" />
                <p style="margin:0 0 8px;"><strong>Message:</strong></p>
                <div style="white-space:pre-wrap;border:1px solid #e5e7eb;padding:12px;border-radius:6px;background:#f9fafb;">{{ $messageBody }}</div>
            @endif

            @if(!empty($allFields) && is_array($allFields))
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;" />
                <p style="margin:0 0 8px;"><strong>Submitted Fields:</strong></p>
                <table style="border-collapse:collapse;width:100%;">
                    <tbody>
                        @foreach($allFields as $row)
                            <tr>
                                <td style="padding:6px 8px;color:#6b7280;border-bottom:1px solid #f3f4f6;width:35%;vertical-align:top;">{{ $row['key'] }}</td>
                                <td style="padding:6px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;">{{ $row['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <p style="margin-top:16px;color:#9ca3af;font-size:12px;">Submitted at: {{ $submittedAt }}</p>
        </div>
    </div>
</body>
</html>
