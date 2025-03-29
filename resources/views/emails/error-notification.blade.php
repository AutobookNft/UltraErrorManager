<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Error Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background-color: #f44336;
            color: white;
            padding: 10px 20px;
            border-radius: 4px 4px 0 0;
        }
        .content {
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-top: 20px;
        }
        .label {
            font-weight: bold;
            min-width: 150px;
            display: inline-block;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Error Notification: {{ $errorCode }}</h2>
    </div>

    <div class="content">
        <div class="section">
            <div><span class="label">Application:</span> {{ $appName }}</div>
            <div><span class="label">Environment:</span> {{ $environment }}</div>
            <div><span class="label">Time:</span> {{ $timestamp }}</div>
            <div><span class="label">Error Code:</span> {{ $errorCode }}</div>
            <div><span class="label">Type:</span> {{ ucfirst($errorType) }}</div>
        </div>

        <div class="section">
            <h3>Error Message</h3>
            <p>{{ $message }}</p>
        </div>

        @if(!empty($exception))
        <div class="section">
            <h3>Exception Details</h3>
            <div><span class="label">Class:</span> {{ $exception['class'] }}</div>
            <div><span class="label">Message:</span> {{ $exception['message'] }}</div>
            <div><span class="label">File:</span> {{ $exception['file'] }}</div>
            <div><span class="label">Line:</span> {{ $exception['line'] }}</div>

            <h4>Stack Trace</h4>
            <pre>{{ $exception['trace'] }}</pre>
        </div>
        @endif

        <div class="section">
            <h3>Request Information</h3>
            <div><span class="label">URL:</span> {{ $requestUrl }}</div>
            <div><span class="label">Method:</span> {{ $requestMethod }}</div>
            <div><span class="label">User Agent:</span> {{ $userAgent }}</div>
            <div><span class="label">IP Address:</span> {{ $userIp }}</div>
        </div>

        @if(isset($userId))
        <div class="section">
            <h3>User Information</h3>
            <div><span class="label">User ID:</span> {{ $userId }}</div>
            <div><span class="label">Name:</span> {{ $userName }}</div>
            <div><span class="label">Email:</span> {{ $userEmail }}</div>
        </div>
        @endif

        @if(!empty($context))
        <div class="section">
            <h3>Error Context</h3>
            <table>
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($context as $key => $value)
                    <tr>
                        <td>{{ $key }}</td>
                        <td>
                            @if(is_array($value) || is_object($value))
                                <pre>{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</body>
</html>
