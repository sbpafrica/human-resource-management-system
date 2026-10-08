<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reformatted Resume Summary</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white !important; /* Force white background */
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: auto;
        }
        h1 {
            text-align: center;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h2 {
            color: #0056b3;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        ul {
            list-style-type: disc;
            padding-left: 20px;
            margin: 10px 0;
        }
        li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        .section {
            page-break-inside: avoid;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Resume Summary</h1>

    @if (!empty($summaryHtml))
        <div class="section">
            <h2>Professional Summary</h2>
            {!! $summaryHtml !!}
        </div>
    @endif

    @if (!empty($expHtml))
        <div class="section">
            <h2>Experience</h2>
            {!! $expHtml !!}
        </div>
    @endif

    @if (!empty($skillsHtml))
        <div class="section">
            <h2>Skills</h2>
            {!! $skillsHtml !!}
        </div>
    @endif

    @if (!empty($educationHtml))
        <div class="section">
            <h2>Education</h2>
            {!! $educationHtml !!}
        </div>
    @endif
</div>
</body>
</html>
