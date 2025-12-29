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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="icon">🚀</span>
        <h1>Connected!</h1>
        <p>You are now connected to high-speed internet. Enjoy your browsing!</p>
        <a href="https://www.google.com" class="btn">Start Browsing</a>
    </div>
</body>
</html>
