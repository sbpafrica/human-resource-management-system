<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $contract ? $contract['employee_name'] . ' - Contract Signing | SBP Africa' : 'Contract Portal | SBP Africa' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f88210;
            --primary-dark: #d66b04;
            --primary-light: #fff5eb;
            --secondary: #1e293b;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --success: #10b981;
            --success-light: #ecfdf5;
            --danger: #ef4444;
            --danger-light: #fef2f2;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: var(--bg); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; }
        header { background: #ffffff; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem; }
        .header-container { max-width: 1000px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
        .logo { height: 44px; object-fit: contain; }
        .badge-client { background: var(--primary-light); color: var(--primary-dark); font-weight: 700; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.85rem; border: 1px solid rgba(248,130,16,0.25); text-transform: uppercase; }
        
        main { max-width: 1000px; width: 100%; margin: 2rem auto; padding: 0 1rem; flex: 1; }
        .card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 20px -2px rgba(0,0,0,0.05); padding: 2rem; margin-bottom: 2rem; }
        
        .hero-title { font-size: 1.65rem; font-weight: 800; color: var(--secondary); margin-bottom: 0.5rem; }
        .hero-subtitle { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; background: var(--bg); padding: 1.25rem; border-radius: 12px; margin-bottom: 1.75rem; border: 1px solid var(--border); }
        .meta-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .meta-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 600; }
        .meta-value { font-size: 0.95rem; font-weight: 700; color: var(--secondary); }
        
        .tabs { display: flex; gap: 0.5rem; border-bottom: 2px solid var(--border); margin-bottom: 1.5rem; }
        .tab-btn { padding: 0.75rem 1.25rem; background: transparent; border: none; font-size: 0.95rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        
        .preview-box { width: 100%; height: 550px; border-radius: 12px; border: 1px solid var(--border); overflow: hidden; background: #525659; }
        .preview-box iframe { width: 100%; height: 100%; border: none; }
        
        .alert { padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.95rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-error { background: var(--danger-light); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-success { background: var(--success-light); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }

        .search-container { max-width: 500px; margin: 2rem auto; text-align: center; }
        .form-group { margin-bottom: 1.25rem; text-align: left; }
        .form-label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--secondary); }
        .form-input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(248,130,16,0.15); }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; }
        .btn-primary { background: var(--primary); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #e2e8f0; color: var(--secondary); }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-success { background: var(--success); color: #ffffff; }
        .btn-success:hover { background: #059669; }
        
        .sig-section { margin-top: 2rem; border-top: 1px solid var(--border); padding-top: 2rem; }
        .sig-canvas-wrapper { border: 2px dashed #cbd5e1; border-radius: 12px; background: #ffffff; position: relative; width: 100%; max-width: 600px; margin: 0 auto 1rem; cursor: crosshair; }
        .sig-canvas { width: 100%; height: 200px; display: block; touch-action: none; border-radius: 10px; }
        .sig-hint { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #94a3b8; font-size: 0.9rem; pointer-events: none; user-select: none; }
        
        .signed-seal { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; background: var(--success-light); border: 2px solid var(--success); border-radius: 12px; text-align: center; }
        .signed-sig-img { max-height: 90px; max-width: 250px; object-fit: contain; margin: 1rem 0; border-bottom: 2px solid #0f172a; padding-bottom: 0.5rem; }
        
        footer { background: #ffffff; border-top: 1px solid var(--border); padding: 1.5rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); }
        
        @media (max-width: 640px) {
            .hero-title { font-size: 1.35rem; }
            .card { padding: 1.25rem; }
            .meta-grid { grid-template-columns: 1fr; }
            .preview-box { height: 400px; }
        }
    </style>
</head>
<body>

    <header>
        <div class="header-container">
            <img src="https://mlnyao5byika.i.optimole.com/8LJnj5c-lhFjjhu0/w:1576/h:662/q:auto/https://sbpafrica.group/wp-content/uploads/2020/05/cropped-sbplogo-africa.png" alt="SBP Africa" class="logo">
            @if ($contract)
                <span class="badge-client">{{ $contract['client'] }}</span>
            @endif
        </div>
    </header>

    <main>
        @if ($error)
            <div class="alert alert-error">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <span>{{ $error }}</span>
            </div>
        @endif

        @if (!$contract)
            <div class="card search-container">
                <h1 class="hero-title">Staff Contract Portal</h1>
                <p class="hero-subtitle">Enter your Staff ID below to review and digitally sign your contract.</p>
                <form action="{{ route('contracts.public.sign') }}" method="GET">
                    <div class="form-group">
                        <label for="staff_id" class="form-label">Staff ID</label>
                        <input type="text" id="staff_id" name="staff_id" class="form-input" placeholder="e.g. SBP1029 or OFI0045" value="{{ $staffId ?? '' }}" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Find My Contract</button>
                </form>
            </div>
        @else
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <h1 class="hero-title">{{ $contract['employee_name'] }}</h1>
                        <p class="hero-subtitle">
                            {{ $contract['contract_type'] === 'renewal' ? 'Contract Renewal / Addendum' : 'Official Employment Contract' }}
                            &bull; <strong>{{ $contract['client'] }}</strong>
                        </p>
                    </div>
                    <div>
                        @if ($contract['is_signed'])
                            <span class="btn btn-success" style="cursor: default; pointer-events: none;">
                                &check; Contract Signed & Activated
                            </span>
                        @else
                            <span class="badge-client" style="background: #fef3c7; color: #92400e; border-color: #fde68a;">
                                Pending Your Signature
                            </span>
                        @endif
                    </div>
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Staff ID</span>
                        <span class="meta-value">{{ $contract['staff_id'] }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Client Company</span>
                        <span class="meta-value">{{ $contract['client'] }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Position / Role</span>
                        <span class="meta-value">{{ $contract['view_data']['position'] ?? 'Staff Member' }}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Contract Period</span>
                        <span class="meta-value">
                            {{ $contract['view_data']['startdate'] ?? 'N/A' }} &rarr; {{ $contract['view_data']['enddate'] ?? 'N/A' }}
                        </span>
                    </div>
                </div>

                <div class="tabs">
                    <button type="button" class="tab-btn active" onclick="switchTab('doc')">Contract Document</button>
                    <button type="button" class="tab-btn" onclick="switchTab('info')">Terms Summary</button>
                </div>

                <div id="tab-doc" class="tab-content">
                    <div class="preview-box">
                        <iframe src="{{ route('contracts.public.preview', ['staff_id' => $contract['staff_id'], 'type' => $contract['contract_type']]) }}" title="Contract Preview"></iframe>
                    </div>
                    <div style="margin-top: 0.75rem; text-align: right;">
                        <a href="{{ route('contracts.public.preview', ['staff_id' => $contract['staff_id'], 'type' => $contract['contract_type']]) }}" target="_blank" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
                            Open in New Tab &nearr;
                        </a>
                    </div>
                </div>

                <div id="tab-info" class="tab-content" style="display: none;">
                    <div style="background: var(--bg); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border);">
                        <h3 style="margin-bottom: 1rem; color: var(--secondary);">Agreement Highlights</h3>
                        <ul style="padding-left: 1.25rem; line-height: 1.8; color: var(--text-muted); font-size: 0.95rem;">
                            <li><strong>Employer:</strong> {{ $contract['view_data']['employername'] ?? 'Strategic Business Partnership (SBP Africa)' }}</li>
                            <li><strong>Client / Location:</strong> {{ $contract['client'] }} ({{ $contract['view_data']['location'] ?? 'Ghana' }})</li>
                            <li><strong>Start Date:</strong> {{ $contract['view_data']['startdate'] ?? 'As stated in contract' }}</li>
                            <li><strong>End Date:</strong> {{ $contract['view_data']['enddate'] ?? 'As stated in contract' }}</li>
                            <li><strong>Official Issue Date:</strong> {{ $contract['view_data']['issuedate'] ?? date('F d, Y') }}</li>
                        </ul>
                    </div>
                </div>

                <!-- SIGNATURE SECTION -->
                <div class="sig-section" id="signing-area">
                    @if ($contract['is_signed'])
                        <div class="signed-seal">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
                            <h2 style="color: var(--secondary); margin-bottom: 0.25rem;">Contract Successfully Signed & Activated</h2>
                            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 500px; margin-bottom: 1rem;">
                                This contract was digitally signed by <strong>{{ $contract['employee_name'] }}</strong>
                                @if (!empty($contract['signature_date']))
                                    on {{ date('F d, Y \a\t h:i A', strtotime($contract['signature_date'])) }}.
                                @endif
                            </p>

                            @if (!empty($contract['signature']))
                                <img src="{{ url('images/signatures/' . $contract['signature']) }}" alt="Digital Signature" class="signed-sig-img" onerror="this.style.display='none'">
                            @endif

                            <div style="margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
                                <a href="{{ route('contracts.public.download', ['staff_id' => $contract['staff_id'], 'type' => $contract['contract_type']]) }}" class="btn btn-primary" download>
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Download Signed PDF Contract
                                </a>
                            </div>
                        </div>
                    @else
                        <div id="signature-form-container">
                            <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--secondary); margin-bottom: 0.5rem; text-align: center;">
                                Complete Your Digital Signature
                            </h2>
                            <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; margin-bottom: 1.5rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                                Please sign within the box below using your finger, stylus, or mouse. Your digital signature activates your employment agreement.
                            </p>

                            <div class="sig-canvas-wrapper" id="canvas-wrapper">
                                <span class="sig-hint" id="sig-hint">Draw your signature here</span>
                                <canvas id="signature-pad" class="sig-canvas"></canvas>
                            </div>

                            <div style="max-width: 600px; margin: 0 auto 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                                <button type="button" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;" onclick="clearSignature()">
                                    &circlearrowleft; Clear
                                </button>
                                <span style="font-size: 0.8rem; color: var(--text-muted);" id="sig-status">Waiting for signature...</span>
                            </div>

                            <div style="max-width: 600px; margin: 0 auto 1.5rem;">
                                <label style="display: flex; align-items: flex-start; gap: 0.65rem; font-size: 0.875rem; color: var(--secondary); cursor: pointer; user-select: none;">
                                    <input type="checkbox" id="agree-terms" style="margin-top: 0.2rem;" required>
                                    <span>
                                        I, <strong>{{ $contract['employee_name'] }}</strong>, have carefully reviewed all pages and clauses of this contract, and I accept the terms of employment with <strong>{{ $contract['client'] }} / SBP Africa</strong>.
                                    </span>
                                </label>
                            </div>

                            <div style="text-align: center;">
                                <button type="button" id="btn-submit-sig" class="btn btn-primary" style="padding: 0.85rem 2.5rem; font-size: 1.05rem;" onclick="submitSignature()">
                                    Accept & Sign Contract
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </main>

    <footer>
        &copy; {{ date('Y') }} Strategic Business Partnership (SBP Africa Limited). All Rights Reserved.
    </footer>

    <script>
        function switchTab(name) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            
            if (name === 'doc') {
                event.target.classList.add('active');
                document.getElementById('tab-doc').style.display = 'block';
            } else {
                event.target.classList.add('active');
                document.getElementById('tab-info').style.display = 'block';
            }
        }

        // Signature Canvas Logic
        const canvas = document.getElementById('signature-pad');
        let ctx = null;
        let isDrawing = false;
        let hasSigned = false;

        if (canvas) {
            ctx = canvas.getContext('2d');

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#0f172a';
            }

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDraw(e) {
                e.preventDefault();
                isDrawing = true;
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                document.getElementById('sig-hint').style.display = 'none';
            }

            function draw(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                hasSigned = true;
                document.getElementById('sig-status').textContent = 'Signature captured ✓';
                document.getElementById('sig-status').style.color = 'var(--success)';
            }

            function stopDraw(e) {
                if (isDrawing) {
                    ctx.closePath();
                    isDrawing = false;
                }
            }

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            window.addEventListener('touchend', stopDraw);
        }

        function clearSignature() {
            if (!canvas || !ctx) return;
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            ctx.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio);
            hasSigned = false;
            document.getElementById('sig-hint').style.display = 'block';
            document.getElementById('sig-status').textContent = 'Waiting for signature...';
            document.getElementById('sig-status').style.color = 'var(--text-muted)';
        }

        async function submitSignature() {
            const agreeCheckbox = document.getElementById('agree-terms');
            if (agreeCheckbox && !agreeCheckbox.checked) {
                alert('Please check the confirmation box to confirm you agree to the contract terms.');
                return;
            }

            if (!hasSigned) {
                alert('Please sign inside the signature box before submitting.');
                return;
            }

            const btn = document.getElementById('btn-submit-sig');
            btn.disabled = true;
            btn.innerHTML = '<span style="opacity: 0.8;">Saving & Activating Contract...</span>';

            const signatureData = canvas.toDataURL('image/png');
            const staffId = @json($contract['staff_id'] ?? '');
            const contractType = @json($contract['contract_type'] ?? 'contract');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            try {
                const response = await fetch(`/contract/sign/${encodeURIComponent(staffId)}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        signature_data: signatureData,
                        contract_type: contractType
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Update UI with signed seal
                    const area = document.getElementById('signing-area');
                    area.innerHTML = `
                        <div class="signed-seal">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
                            <h2 style="color: var(--secondary); margin-bottom: 0.25rem;">Contract Successfully Signed & Activated!</h2>
                            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 500px; margin-bottom: 1rem;">
                                Thank you, <strong>${data.contract ? data.contract.employee_name : 'Staff'}</strong>. Your contract is now official and stored securely in the company portal.
                            </p>
                            <img src="${signatureData}" alt="Digital Signature" class="signed-sig-img">
                            <div style="margin-top: 1.5rem;">
                                <a href="${data.download_url}" class="btn btn-primary" download>
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Download Signed PDF Contract
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    alert('Error: ' + (data.message || 'Failed to submit signature.'));
                    btn.disabled = false;
                    btn.textContent = 'Accept & Sign Contract';
                }
            } catch (err) {
                console.error(err);
                alert('A network error occurred. Please check your connection and try again.');
                btn.disabled = false;
                btn.textContent = 'Accept & Sign Contract';
            }
        }
    </script>
</body>
</html>
