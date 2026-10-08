<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Page Not Found | SBP HRMS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Instrument Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .container {
            max-width: 28rem;
            width: 100%;
            text-align: center;
            background: #ffffff;
            border-radius: 0.75rem;
            padding: 2.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        .code {
            font-size: 4rem;
            font-weight: 800;
            color: #0284c7;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .message {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0369a1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">404</div>
        <h1 class="title">Page Not Found</h1>
        <p class="message">The page you are looking for does not exist or has been moved.</p>
        <a href="{{ url('/') }}" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
