<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        h1 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h2 { color: #2980b9; margin-top: 20px; }
        ul { list-style-type: disc; padding-left: 20px; }
        .section { margin-bottom: 20px; }
        .job { border-left: 4px solid #3498db; padding-left: 10px; margin-bottom: 15px; }
        .bullets { margin: 5px 0; }
    </style>
</head>
<body>
    <h1>{{ $data['name'] }}</h1>
    <p>Email: {{ $data['email'] }} | Phone: {{ $data['mobile_number'] }}</p>

    <div class="section">
        <h2>Skills</h2>
        <ul>
            @foreach($data['skills'] as $skill)
                <li>{{ $skill }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Experience</h2>
        @foreach($data['experiences'] as $job)
            <div class="job">
                <strong>{{ $job['title'] }} at {{ $job['company'] }}</strong> ({{ $job['dates'] }})
                <ul class="bullets">
                    @foreach($job['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</body>
</html>
