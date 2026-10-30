<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yêu cầu kết nối cơ sở dữ liệu - NoteApp</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #eef2ff 0%, #f8fafc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: #1e293b;
        }
        .box {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 40px 32px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #fef2f2;
            color: #dc2626;
            font-size: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        h1 { font-size: 22px; color: #dc2626; margin: 0 0 12px; font-weight: 700; }
        p { color: #475569; line-height: 1.6; margin: 0 0 10px; font-size: 15px; }
        .code {
            display: inline-block;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 6px;
            font-family: Consolas, monospace;
            font-size: 14px;
            color: #0f172a;
        }
        .btn-retry {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 24px;
            background: #4a6cf7;
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
        }
        .btn-retry:hover { background: #3b5bdb; }
    </style>
</head>
<body>
    <main class="box" role="alert">
        <div class="icon" aria-hidden="true">&#9888;</div>
        <h1>Yêu cầu kết nối cơ sở dữ liệu</h1>
        <p>Ứng dụng hiện chưa thể kết nối tới máy chủ cơ sở dữ liệu MySQL.</p>
        <p>Vui lòng khởi động dịch vụ MySQL và kiểm tra lại thông số kết nối (<span class="code">DB_HOST</span>, <span class="code">DB_PORT</span>, <span class="code">DB_NAME</span>, <span class="code">DB_USER</span>, <span class="code">DB_PASS</span>) trong tệp <span class="code">.env</span>.</p>
        <a href="/" class="btn-retry">Thử kết nối lại</a>
    </main>
</body>
</html>
