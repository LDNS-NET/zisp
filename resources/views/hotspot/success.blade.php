<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Successful</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: white;
            text-align: center;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 90%;
            width: 400px;
            animation: fadeIn 0.8s ease-out;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }
        h1 {
            margin: 10px 0;
            font-weight: 700;
            font-size: 28px;
        }
        p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background: #fff;
            color: #764ba2;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .spinner {
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="card">
        @if(request()->has('u') && request()->has('p'))
            <span class="icon">🔐</span>
            <h1>Logging you in...</h1>
            <div class="spinner"></div>
            <p>Please wait while we connect you to the internet.</p>
            
            <!-- Hidden form for auto-login -->
            <form id="loginForm" method="post" action="{{ request()->get('link-login') ?? '/login' }}" style="display: none;">
                <input type="hidden" name="username" value="{{ request()->get('u') }}">
                <input type="hidden" name="password" value="{{ request()->get('p') }}">
                <input type="hidden" name="dst" value="{{ request()->get('link-orig') ?? '' }}">
                <input type="hidden" name="popup" value="true">
            </form>

            <script>
                // Auto-submit the login form after a brief delay
                setTimeout(function() {
                    document.getElementById('loginForm').submit();
                }, 1500);

                // Fallback: If auto-login fails, show credentials after 5 seconds
                setTimeout(function() {
                    document.querySelector('.card').innerHTML = `
                        <span class="icon">🚀</span>
                        <h1>Almost There!</h1>
                        <p>If you weren't automatically connected, use these credentials:</p>
                        <div style="margin-top: 25px; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); text-align: left;">
                            <h2 style="margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8;">Your Credentials</h2>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="font-size: 12px; opacity: 0.7;">Username:</span>
                                <span style="font-family: monospace; font-weight: 700; font-size: 16px;">{{ request()->get('u') }}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="font-size: 12px; opacity: 0.7;">Password:</span>
                                <span style="font-family: monospace; font-weight: 700; font-size: 16px;">{{ request()->get('p') }}</span>
                            </div>
                        </div>
                        <a href="https://www.google.com" class="btn">Start Browsing</a>
                    `;
                }, 5000);
            </script>
        @else
            <span class="icon">🚀</span>
            <h1>Connected!</h1>
            <p>You are now connected to high-speed internet. Enjoy your browsing!</p>
            <a href="https://www.google.com" class="btn">Start Browsing</a>
        @endif
    </div>
</body>
</html>
