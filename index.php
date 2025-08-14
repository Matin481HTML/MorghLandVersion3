<?php
// تنظیمات سرور ماینکرفت شما
$server_ip = "MorghLandVersion3.aternos.me";
$server_port = 39995;

// متغیرهای وضعیت پیش‌فرض
$online = false;
$players_online = 0;
$max_players = 0;
$motd = "درحال بررسی وضعیت سرور...";

// تابع برای ارسال و دریافت داده‌های فشرده (VarInt)
// این تابع برای ارتباط صحیح با پروتکل ماینکرفت ضروری است.
function write_varint($socket, $value) {
    do {
        $byte = $value & 0x7F;
        $value >>= 7;
        if ($value != 0) {
            $byte |= 0x80;
        }
        fwrite($socket, chr($byte));
    } while ($value != 0);
}

// تلاش برای اتصال مستقیم به سرور
$socket = @fsockopen($server_ip, $server_port, $errno, $errstr, 2);

if ($socket) {
    // سرور آنلاین است، اطلاعات را از آن دریافت می‌کنیم
    $online = true;

    // 1. بسته‌ی Handshake را برای شروع ارتباط ارسال می‌کنیم
    $protocol_version = 758; // آخرین نسخه پروتکل
    $packet_id = 0x00;
    $next_state = 1; // وضعیت درخواست (Status)

    $handshake_data = pack('C', $packet_id);
    write_varint($socket, $protocol_version);
    write_varint($socket, strlen($server_ip));
    $handshake_data .= $server_ip;
    $handshake_data .= pack('n', $server_port);
    write_varint($socket, $next_state);

    write_varint($socket, strlen($handshake_data));
    fwrite($socket, $handshake_data);

    // 2. درخواست وضعیت (Status Request Packet) را می‌فرستیم
    $status_request_packet = pack('C', 0x00);
    write_varint($socket, strlen($status_request_packet));
    fwrite($socket, $status_request_packet);

    // 3. پاسخ سرور را دریافت و پردازش می‌کنیم
    // این بخش اطلاعات بازیکنان و MOTD (پیام خوش آمدگویی) را از سرور می‌گیرد
    $response_length_raw = fread($socket, 1);
    write_varint($socket, ord($response_length_raw));
    $response_id = fread($socket, 1);
    $response_data_length_raw = fread($socket, 1);
    $response_data_length = unpack('N', "\0\0\0" . $response_data_length_raw);
    $response_data_length = $response_data_length[1];

    $response_data = "";
    while (strlen($response_data) < $response_data_length) {
        $response_data .= fread($socket, $response_data_length - strlen($response_data));
    }

    $json_data = json_decode($response_data, true);

    if ($json_data && isset($json_data['players'])) {
        $players_online = $json_data['players']['online'];
        $max_players = $json_data['players']['max'];
        $motd = $json_data['description']['text'] ?? 'سرور ماینکرفت';
    }
    
    // قطع ارتباط با سرور
    fclose($socket);
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وضعیت سرور MorghLand</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* تنظیمات عمومی و فونت */
        body {
            font-family: 'Vazirmatn', sans-serif;
            direction: rtl;
            margin: 0;
            padding: 0;
            /* این آدرس را با تصویر پس‌زمینه ماینکرفتی مورد علاقه خود جایگزین کنید */
            background: url('https://placehold.co/1920x1080/4f4e4c/ffffff?text=Minecraft+Background') no-repeat center center fixed;
            background-size: cover;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.7);
        }
        .container {
            text-align: center;
            background-color: rgba(15, 15, 15, 0.8);
            padding: 50px 60px;
            border-radius: 20px;
            border: 3px solid #8B4513; /* رنگ چوب ماینکرفتی */
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.7);
            max-width: 90%;
            transition: transform 0.3s ease-in-out;
        }
        .container:hover {
            transform: translateY(-5px);
        }
        .title {
            font-size: 3em;
            margin-bottom: 25px;
            color: #FFD700; /* رنگ طلایی */
            letter-spacing: 2px;
        }
        .status-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .card-header h2 {
            font-size: 1.8em;
            margin-top: 0;
            color: #fff;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }
        .card-body {
            margin-top: 20px;
        }
        .status {
            font-size: 2em;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .status-icon {
            height: 25px;
            width: 25px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 15px;
            box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.5);
            animation: pulse 1.5s infinite ease-in-out;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .online .status-icon {
            background-color: #00ff00; /* سبز */
        }
        .online .status-text {
            color: #00ff00;
        }
        .offline .status-icon {
            background-color: #ff0000; /* قرمز */
        }
        .offline .status-text {
            color: #ff0000;
        }
        .players-info {
            font-size: 1.5em;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }
        .players-icon {
            font-size: 1.5em;
            margin-left: 10px;
            animation: bounce 1s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        .players-count {
            font-weight: bold;
            color: #00ffff;
        }
        .join-info {
            margin-top: 40px;
            background: rgba(0, 0, 0, 0.4);
            padding: 15px 25px;
            border-radius: 10px;
            border: 1px dashed rgba(255, 255, 255, 0.2);
        }
        .join-info h3 {
            margin: 0;
            font-size: 1.5em;
            color: #fff;
        }
        .server-address {
            font-family: monospace;
            font-size: 1.4em;
            margin-top: 10px;
            color: #aaffaa;
            word-break: break-all;
        }
        /* برای نمایش بهتر در موبایل */
        @media (max-width: 600px) {
            .container {
                padding: 30px;
            }
            .title {
                font-size: 2em;
            }
            .status {
                font-size: 1.5em;
            }
            .players-info {
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">وضعیت سرور ماینکرفت MorghLand</h1>
        <div class="status-card">
            <div class="card-header">
                <h2><?php echo htmlspecialchars($motd); ?></h2>
            </div>
            <div class="card-body">
                <?php if ($online): ?>
                    <div class="status online">
                        <span class="status-icon"></span>
                        <span class="status-text">سرور آنلاین است!</span>
                    </div>
                    <div class="players-info">
                        <span class="players-icon">⛏️</span>
                        <p>تعداد پلیرهای آنلاین: <span class="players-count"><?php echo $players_online; ?></span> از <span class="players-count"><?php echo $max_players; ?></span></p>
                    </div>
                <?php else: ?>
                    <div class="status offline">
                        <span class="status-icon"></span>
                        <span class="status-text">سرور آفلاین است.</span>
                    </div>
                    <div class="players-info">
                        <p>اطلاعاتی در دسترس نیست.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="join-info">
            <h3>آدرس سرور:</h3>
            <p class="server-address"><?php echo $server_ip . ':' . $server_port; ?></p>
        </div>
    </div>
</body>
</html>
