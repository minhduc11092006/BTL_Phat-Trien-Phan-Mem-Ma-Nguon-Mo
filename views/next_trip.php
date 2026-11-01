<?php
// next_trip.php - Tìm chuyến tiếp theo theo ngày hoạt động, ngày ngoại lệ và giờ vượt 24h
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gtfs_functions.php';

$user = current_user();
$active_version = get_active_feed_version();
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : ($active_version['id'] ?? 0);

$stop_id = $_GET['stop_id'] ?? '';
$date = $_GET['date'] ?? date('Y-m-d');
$time = $_GET['time'] ?? date('H:i');
$save_success = '';
$save_error = '';

// Xử lý Lưu truy vấn mẫu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_query') {
    if (!$user) {
        $save_error = 'Vui lòng đăng nhập để lưu truy vấn mẫu này.';
    } else {
        $q_name = trim($_POST['query_name'] ?? '');
        if ($q_name === '') {
            $q_name = "Tra cứu trạm " . $stop_id . " lúc " . $time . " ngày " . $date;
        }
        $params = [
            'stop_id' => $stop_id,
            'date' => $date,
            'time' => $time
        ];
        if (save_query($user['id'], $q_name, 'next_trip', $params)) {
            $save_success = "Đã lưu truy vấn mẫu: \"<strong>" . htmlspecialchars($q_name) . "</strong>\" thành công!";
        } else {
            $save_error = "Không thể lưu truy vấn mẫu.";
        }
    }
}

// Lấy danh sách trạm dừng nếu có bản active
$stops = [];
$selected_stop = null;
$next_trips = [];
$searched = false;

if ($active_version) {
    $stops = get_all_stops($active_version['id']);

    if ($stop_id !== '') {
        $selected_stop = get_stop($stop_id, $active_version['id']);
        if ($selected_stop) {
            $searched = true;
            $next_trips = find_next_trips($stop_id, $date, $time, $active_version['id'], 15);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm Chuyến Kế Tiếp - Tra Cứu Lịch Trình GTFS</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            950: '#1e1b4b',
                        }
                    },
                    boxShadow: {
                        'glow': '0 0 25px -5px rgba(79, 70, 229, 0.3)',
                        'm3-elevation-1': '0 1px 3px 1px rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.08)',
                        'm3-elevation-2': '0 4px 8px 3px rgba(0, 0, 0, 0.05), 0 1px 3px 0 rgba(0, 0, 0, 0.08)',
                        'm3-elevation-3': '0 8px 24px -4px rgba(15, 23, 42, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.04)',
                        'm3-elevation-4': '0 16px 32px -8px rgba(15, 23, 42, 0.12), 0 8px 16px -4px rgba(15, 23, 42, 0.06)',
                    }
                }
            }
        }
    </script>

    <!-- Bootstrap 5.3 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }

        /* Hiệu ứng Parallax Navbar & Bus */
        @keyframes infinite-pan-left {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-50%, 0, 0); }
        }
        .animate-infinite-parallax {
            display: flex;
            width: 200%;
            animation: infinite-pan-left var(--parallax-duration, 10s) linear infinite;
            will-change: transform;
        }
        @keyframes bus-wheel-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .animate-wheel-spin {
            transform-origin: center center;
            transform-box: fill-box;
            animation: bus-wheel-spin 0.65s linear infinite;
            will-change: transform;
        }
        @keyframes bus-bounce-suspension {
            0%, 100% { transform: translateY(0); }
            30% { transform: translateY(-2px); }
            70% { transform: translateY(1px); }
        }
        .animate-bus-bounce {
            animation: bus-bounce-suspension 0.85s ease-in-out infinite;
            will-change: transform;
        }
        @keyframes headlight-glow-pulse {
            0%, 100% { opacity: 0.75; transform: scaleX(1); }
            50% { opacity: 1; transform: scaleX(1.15); filter: drop-shadow(0 0 10px rgba(254, 240, 138, 0.95)); }
        }
        .animate-headlight-pulse {
            animation: headlight-glow-pulse 1.6s ease-in-out infinite;
        }
        @keyframes bus-exhaust-puff {
            0% { transform: translate(0, 0) scale(0.35); opacity: 0.8; }
            50% { opacity: 0.5; }
            100% { transform: translate(-35px, -10px) scale(1.8); opacity: 0; }
        }
        .animate-exhaust-puff-1 {
            animation: bus-exhaust-puff 1.2s cubic-bezier(0.15, 0.85, 0.35, 1) infinite;
        }
        .animate-exhaust-puff-2 {
            animation: bus-exhaust-puff 1.2s cubic-bezier(0.15, 0.85, 0.35, 1) 0.6s infinite;
        }
        @keyframes highway-track-pan {
            0% { background-position: 0 0; }
            100% { background-position: -36px 0; }
        }
        .animate-highway-track {
            background-image: repeating-linear-gradient(90deg, #38bdf8 0, #38bdf8 12px, transparent 12px, transparent 24px);
            background-size: 36px 2px;
            animation: highway-track-pan 0.65s linear infinite;
        }
        @keyframes bus-suspension {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            30% { transform: translateY(-1.5px) rotate(-1deg); }
            70% { transform: translateY(0.5px) rotate(0.5deg); }
        }
        .animate-bus-suspension {
            animation: bus-suspension 1.8s ease-in-out infinite;
        }
        @keyframes taillight-trail {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; filter: drop-shadow(0 0 6px rgba(244, 63, 94, 0.9)); }
        }
        .animate-taillight-trail {
            animation: taillight-trail 1s ease-in-out infinite;
        }

        /* Material 3 Custom Chip Styling */
        .m3-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.3rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }
        .m3-chip:hover {
            transform: translateY(-1px);
        }
        .m3-chip:active {
            transform: scale(0.97);
        }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-50 selection:bg-brand-600 selection:text-white">

    <!-- ============================================================= -->
    <!-- NAVBAR CHUẨN PARALLAX - NÚT "CHUYẾN KẾ TIẾP" ĐANG ACTIVE    -->
    <!-- ============================================================= -->
    <header class="sticky top-0 z-50 bg-slate-900/95 backdrop-blur-md border-b border-slate-800/80 transition-all overflow-hidden relative shadow-lg">
        
        <!-- LỚP PARALLAX CẢNH VÔ TẬN & XE BUS ĐÔ THỊ ĐANG CHẠY LIÊN TỤC -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none select-none z-0">
            <!-- Lớp Parallax 1: Bầu trời sao đêm & dải mây -->
            <div class="absolute inset-0 opacity-25 animate-infinite-parallax" style="--parallax-duration: 40s;">
                <div class="w-1/2 h-full flex items-center justify-around shrink-0">
                    <div class="w-24 h-4 bg-sky-300/30 rounded-full blur-sm"></div>
                    <div class="w-40 h-5 bg-indigo-300/20 rounded-full blur-md"></div>
                    <div class="w-32 h-4 bg-slate-300/25 rounded-full blur-sm"></div>
                </div>
                <div class="w-1/2 h-full flex items-center justify-around shrink-0">
                    <div class="w-24 h-4 bg-sky-300/30 rounded-full blur-sm"></div>
                    <div class="w-40 h-5 bg-indigo-300/20 rounded-full blur-md"></div>
                    <div class="w-32 h-4 bg-slate-300/25 rounded-full blur-sm"></div>
                </div>
            </div>

            <!-- Lớp Parallax 2: Dãy nhà cao ốc thành phố (City Skyline) cuộn ngang vô tận -->
            <div class="absolute bottom-[3px] inset-x-0 h-11 opacity-30 animate-infinite-parallax" style="--parallax-duration: 16s;">
                <div class="w-1/2 h-full flex items-end shrink-0">
                    <svg class="w-full h-full fill-indigo-950 stroke-indigo-800/50" viewBox="0 0 800 45" preserveAspectRatio="none">
                        <rect x="10" y="15" width="25" height="30" />
                        <rect x="45" y="8" width="30" height="37" />
                        <polygon points="60,2 57,8 63,8" />
                        <rect x="85" y="18" width="20" height="27" />
                        <rect x="115" y="10" width="35" height="35" />
                        <rect x="160" y="22" width="28" height="23" />
                        <rect x="200" y="6" width="32" height="39" />
                        <rect x="245" y="16" width="25" height="29" />
                        <rect x="280" y="12" width="40" height="33" />
                        <rect x="330" y="20" width="22" height="25" />
                        <rect x="365" y="5" width="35" height="40" />
                        <polygon points="382,0 379,5 385,5" />
                        <rect x="410" y="14" width="28" height="31" />
                        <rect x="450" y="22" width="24" height="23" />
                        <rect x="485" y="9" width="38" height="36" />
                        <rect x="535" y="18" width="25" height="27" />
                        <rect x="570" y="7" width="30" height="38" />
                        <rect x="610" y="15" width="35" height="30" />
                        <rect x="655" y="20" width="25" height="25" />
                        <rect x="690" y="10" width="40" height="35" />
                        <rect x="740" y="16" width="30" height="29" />
                        <rect x="780" y="8" width="20" height="37" />
                    </svg>
                </div>
                <div class="w-1/2 h-full flex items-end shrink-0">
                    <svg class="w-full h-full fill-indigo-950 stroke-indigo-800/50" viewBox="0 0 800 45" preserveAspectRatio="none">
                        <rect x="10" y="15" width="25" height="30" />
                        <rect x="45" y="8" width="30" height="37" />
                        <polygon points="60,2 57,8 63,8" />
                        <rect x="85" y="18" width="20" height="27" />
                        <rect x="115" y="10" width="35" height="35" />
                        <rect x="160" y="22" width="28" height="23" />
                        <rect x="200" y="6" width="32" height="39" />
                        <rect x="245" y="16" width="25" height="29" />
                        <rect x="280" y="12" width="40" height="33" />
                        <rect x="330" y="20" width="22" height="25" />
                        <rect x="365" y="5" width="35" height="40" />
                        <polygon points="382,0 379,5 385,5" />
                        <rect x="410" y="14" width="28" height="31" />
                        <rect x="450" y="22" width="24" height="23" />
                        <rect x="485" y="9" width="38" height="36" />
                        <rect x="535" y="18" width="25" height="27" />
                        <rect x="570" y="7" width="30" height="38" />
                        <rect x="610" y="15" width="35" height="30" />
                        <rect x="655" y="20" width="25" height="25" />
                        <rect x="690" y="10" width="40" height="35" />
                        <rect x="740" y="16" width="30" height="29" />
                        <rect x="780" y="8" width="20" height="37" />
                    </svg>
                </div>
            </div>

            <!-- Lớp Parallax 3: Làn đường cao tốc / Vạch sơn phản quang neon cuộn liên tục -->
            <div class="absolute bottom-0 inset-x-0 h-[3px] bg-slate-950/90 border-t border-slate-800/80 flex items-center overflow-hidden">
                <div class="w-full h-full animate-highway-track opacity-85"></div>
            </div>
            <div class="absolute bottom-1 inset-x-0 h-[1px] bg-gradient-to-r from-transparent via-cyan-500/25 to-transparent"></div>

            <!-- XE BUÝT ĐÔ THỊ ĐANG CHẠY LIÊN TỤC TRÊN NỀN PARALLAX VÔ TẬN -->
            <div class="absolute bottom-[3px] left-3 sm:left-6 md:left-10 flex items-end z-10 animate-bus-bounce">
                <div class="relative flex items-center">
                    <!-- Hiệu ứng khói xả phun ra từ đuôi xe buýt -->
                    <div class="absolute -left-2 bottom-1 pointer-events-none">
                        <div class="w-2.5 h-2.5 rounded-full bg-slate-400/40 blur-[1px] animate-exhaust-puff-1"></div>
                        <div class="w-2 h-2 rounded-full bg-slate-300/35 blur-[1px] animate-exhaust-puff-2 -mt-1"></div>
                    </div>

                    <!-- Vệt sáng neon phát phía sau đuôi xe -->
                    <div class="w-12 h-3.5 bg-gradient-to-l from-emerald-500/30 via-cyan-500/15 to-transparent blur-[2px] -mr-1"></div>

                    <!-- SVG Xe Bus Điện Đô Thị Siêu Nét -->
                    <svg class="w-28 sm:w-32 h-8 sm:h-9 filter drop-shadow-[0_2px_10px_rgba(16,185,129,0.7)]" viewBox="0 0 110 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="navBusPaintGrad" x1="5" y1="4" x2="5" y2="25" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#10b981" />
                                <stop offset="50%" stop-color="#059669" />
                                <stop offset="100%" stop-color="#047857" />
                            </linearGradient>
                            <linearGradient id="navBusBeamGrad" x1="88" y1="17.5" x2="110" y2="17.5" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#fef08a" stop-opacity="0.95" />
                                <stop offset="60%" stop-color="#fef08a" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#fef08a" stop-opacity="0" />
                            </linearGradient>
                        </defs>

                        <!-- Khung xe khí động học -->
                        <rect x="5" y="4" width="83" height="21" rx="5" fill="url(#navBusPaintGrad)" stroke="#0f172a" stroke-width="1.2" />
                        <path d="M6 7 Q 48 3 87 7" stroke="#6ee7b7" stroke-width="1.2" fill="none" />
                        
                        <!-- Dải kính xe -->
                        <rect x="8" y="6" width="14" height="9" rx="1.5" fill="#0f172a" />
                        <rect x="24" y="6" width="15" height="9" rx="1.5" fill="#0f172a" />
                        <rect x="41" y="6" width="15" height="9" rx="1.5" fill="#0f172a" />
                        <rect x="58" y="6" width="15" height="9" rx="1.5" fill="#0f172a" />
                        <path d="M75 6 H83 C86 6 87 7.5 87 9.5 V15 H75 V6 Z" fill="#0f172a" />
                        
                        <!-- Biển số tuyến LED: "BUS 01" -->
                        <text x="42" y="12.5" fill="#38bdf8" font-size="5" font-family="monospace" font-weight="bold" letter-spacing="0.5">BUS 01</text>
                        
                        <rect x="5" y="16" width="2" height="5" rx="0.8" fill="#f43f5e" class="animate-taillight-trail" />
                        <circle cx="86" cy="17.5" r="2" fill="#fef08a" class="animate-headlight-pulse" />
                        <polygon points="88,17.5 110,12 110,24" fill="url(#navBusBeamGrad)" class="animate-headlight-pulse opacity-85" />

                        <!-- Bánh xe quay tròn liên tục -->
                        <g class="animate-wheel-spin" style="transform-origin: 20px 24px;">
                            <circle cx="20" cy="24" r="5" fill="#0f172a" stroke="#cbd5e1" stroke-width="1.5" />
                            <circle cx="20" cy="24" r="2" fill="#38bdf8" />
                            <line x1="20" y1="19" x2="20" y2="29" stroke="#cbd5e1" stroke-width="1.2" />
                            <line x1="15" y1="24" x2="25" y2="24" stroke="#cbd5e1" stroke-width="1.2" />
                        </g>

                        <g class="animate-wheel-spin" style="transform-origin: 73px 24px;">
                            <circle cx="73" cy="24" r="5" fill="#0f172a" stroke="#cbd5e1" stroke-width="1.5" />
                            <circle cx="73" cy="24" r="2" fill="#38bdf8" />
                            <line x1="73" y1="19" x2="73" y2="29" stroke="#cbd5e1" stroke-width="1.2" />
                            <line x1="68" y1="24" x2="78" y2="24" stroke="#cbd5e1" stroke-width="1.2" />
                        </g>
                    </svg>

                    <span class="text-[9px] font-bold text-emerald-300 bg-emerald-950/90 px-1.5 py-0.5 rounded border border-emerald-500/40 -ml-2 -mb-2 shadow-sm whitespace-nowrap hidden sm:inline-block">
                        01 Live
                    </span>
                </div>
            </div>
        </div>

        <!-- NỘI DUNG NAVBAR & LINKS -->
        <div class="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-[70px] gap-2">
                
                <!-- Logo -->
                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="../index.php" class="group flex items-center gap-2.5 focus:outline-none">
                        <div class="relative w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 via-indigo-500 to-sky-400 p-0.5 shadow-glow flex items-center justify-center transform group-hover:scale-105 transition duration-300 shrink-0">
                            <div class="w-full h-full bg-slate-900 rounded-[10px] flex items-center justify-center relative overflow-hidden">
                                <i class="bi bi-bus-front-fill text-base text-sky-400 -mr-1 group-hover:text-amber-400 transition animate-bus-suspension"></i>
                                <i class="bi bi-train-front-fill text-xs text-indigo-400 -ml-1 opacity-80"></i>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm sm:text-base font-extrabold tracking-tight text-white flex items-center gap-1.5 whitespace-nowrap">
                                Smart Transit GTFS
                                <span class="hidden md:inline-block text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-full bg-brand-500/20 text-brand-300 border border-brand-500/30">Bus &amp; Metro</span>
                            </span>
                            <span class="text-[10px] text-slate-400 -mt-0.5 font-medium hidden 2xl:block whitespace-nowrap">Tra Cứu Lịch Trình Giao Thông Công Cộng</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Links: CHUYẾN KẾ TIẾP ĐANG ACTIVE -->
                <nav class="hidden lg:flex items-center gap-1 bg-slate-900/90 backdrop-blur-md p-1 rounded-xl shadow-md shrink-0">
                    <a href="../index.php" class="h-9 px-3 text-xs xl:text-sm font-medium rounded-lg text-slate-200 hover:bg-slate-800/80 hover:text-white transition inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                        <i class="bi bi-house-door text-brand-400 text-sm"></i>
                        <span>Trang chủ</span>
                    </a>
                    
                    <a href="gtfs_browse.php?tab=routes&version_id=<?php echo htmlspecialchars($version_id ?? ''); ?>" class="h-9 px-3 text-xs xl:text-sm font-medium rounded-lg text-slate-200 hover:bg-slate-800/80 hover:text-white transition inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                        <i class="bi bi-compass-fill text-sky-400 text-sm"></i>
                        <span>Tuyến & Trạm</span>
                    </a>
                    
                    <a href="map.php?version_id=<?php echo htmlspecialchars($version_id ?? ''); ?>" class="h-9 px-3 text-xs xl:text-sm font-medium rounded-lg text-slate-200 hover:bg-slate-800/80 hover:text-white transition inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                        <i class="bi bi-map-fill text-emerald-400 text-sm"></i>
                        <span>Bản Đồ</span>
                    </a>

                    <!-- Nút Chuyến Kế Tiếp (Active Highlighting) -->
                    <a href="next_trip.php" class="h-9 px-3 text-xs xl:text-sm font-semibold rounded-lg bg-brand-600/50 text-white shadow-sm inline-flex items-center gap-1.5 transition whitespace-nowrap shrink-0">
                        <i class="bi bi-clock-history text-amber-400 text-sm"></i>
                        <span>Chuyến Kế Tiếp</span>
                    </a>
                    
                    <?php if ($user): ?>
                        <a href="saved_queries.php" class="h-9 px-3 text-xs xl:text-sm font-medium rounded-lg text-slate-200 hover:bg-slate-800/80 hover:text-white transition inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                            <i class="bi bi-bookmark-star-fill text-amber-400 text-sm"></i>
                            <span>Truy Vấn Đã Lưu</span>
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- Trạng Thái User / Nút Đăng nhập -->
                <div class="flex items-center gap-2 xl:gap-2.5 shrink-0">
                    <div class="hidden 2xl:flex items-center gap-2 px-2.5 py-1.5 h-9 rounded-xl bg-slate-900/85 shadow-inner backdrop-blur-md shrink-0">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-bold text-slate-200 tracking-tight whitespace-nowrap flex items-center gap-1">
                            <i class="bi bi-broadcast text-emerald-400"></i>
                            <span>Radar</span>
                            <span class="text-[9px] font-bold text-emerald-300 bg-emerald-500/20 px-1 py-0.2 rounded border border-emerald-500/30">LIVE</span>
                        </span>
                    </div>

                    <?php if ($user): ?>
                        <div class="hidden sm:flex items-center gap-1.5 xl:gap-2 pl-2 border-l border-slate-800 shrink-0">
                            <div class="flex items-center gap-2 px-2.5 h-9 rounded-xl bg-slate-900/85 backdrop-blur-md shrink-0">
                                <div class="w-6 h-6 rounded-full bg-brand-600/40 text-brand-300 flex items-center justify-center font-bold text-xs shrink-0">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <span class="text-xs font-semibold text-slate-200 truncate max-w-[110px]"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                    <span class="text-[10px] text-amber-300 bg-amber-500/10 px-1.5 py-0.2 rounded font-medium capitalize"><?php echo htmlspecialchars($user['role'] ?? 'Member'); ?></span>
                                </div>
                            </div>

                            <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                                <a href="../admin/gtfs.php" class="h-9 inline-flex items-center gap-1.5 px-3 text-xs font-bold rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 hover:from-amber-400 hover:to-amber-500 shadow-sm transition whitespace-nowrap shrink-0">
                                    <i class="bi bi-speedometer2 text-sm"></i>
                                    <span>Quản Trị GTFS</span>
                                </a>
                            <?php endif; ?>

                            <a href="../auth/logout.php" title="Đăng xuất" class="h-9 w-9 inline-flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-slate-800/80 rounded-xl transition shrink-0">
                                <i class="bi bi-box-arrow-right text-base"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="../auth/login.php" class="h-9 px-4 inline-flex items-center justify-center text-xs font-semibold rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-200 transition shrink-0 ml-2">
                            Đăng nhập
                        </a>
                    <?php endif; ?>

                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-btn" type="button" class="sm:hidden h-9 w-9 inline-flex items-center justify-center rounded-xl text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 focus:outline-none">
                        <i id="menu-icon-bars" class="bi bi-list text-xl"></i>
                        <i id="menu-icon-close" class="bi bi-x-lg text-lg hidden"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer Menu -->
        <div id="mobile-menu" class="hidden sm:hidden relative z-30 bg-slate-900/98 border-t border-slate-800/80 px-4 pt-3 pb-6 space-y-2 backdrop-blur-xl">
            <a href="../index.php" class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
                <i class="bi bi-house-door text-brand-400 mr-2"></i> Trang chủ
            </a>
            <a href="gtfs_browse.php?tab=routes&version_id=<?php echo htmlspecialchars($version_id ?? ''); ?>" class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
                <i class="bi bi-compass-fill text-sky-400 mr-2"></i> Tuyến & Trạm
            </a>
            <a href="map.php?version_id=<?php echo htmlspecialchars($version_id ?? ''); ?>" class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
                <i class="bi bi-map-fill text-emerald-400 mr-2"></i> Bản Đồ
            </a>
            <a href="next_trip.php" class="block px-3 py-2.5 rounded-xl text-base font-semibold text-white bg-brand-600/50 transition">
                <i class="bi bi-clock-history text-amber-400 mr-2"></i> Chuyến Kế Tiếp
            </a>
            
            <?php if ($user): ?>
                <a href="saved_queries.php" class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <i class="bi bi-bookmark-star-fill text-amber-400 mr-2"></i> Truy Vấn Đã Lưu
                </a>
                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <a href="../admin/gtfs.php" class="block px-3 py-2.5 rounded-xl text-base font-bold text-amber-400 hover:bg-slate-800 transition">
                        <i class="bi bi-speedometer2 mr-2"></i> Quản Trị GTFS
                    </a>
                <?php endif; ?>
                <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-200">
                        <i class="bi bi-person-circle text-brand-400 mr-1.5"></i> <?php echo htmlspecialchars($user['fullname']); ?>
                    </span>
                    <a href="../auth/logout.php" class="text-sm font-medium text-rose-400 hover:text-rose-300">Đăng xuất</a>
                </div>
            <?php else: ?>
                <a href="../auth/login.php" class="block px-3 py-2.5 mt-4 text-center rounded-xl text-base font-bold text-white bg-brand-600 hover:bg-brand-500 transition">
                    Đăng nhập hệ thống
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- ============================================================= -->
    <!-- MAIN CONTENT CONTAINER                                        -->
    <!-- ============================================================= -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">

        <!-- Banner Tiêu Đề & Trạng Thái Phiên Bản Lịch Trình -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-50 border border-brand-200/80 text-brand-700 text-xs font-bold tracking-wide uppercase mb-2">
                    <i class="bi bi-lightning-charge-fill text-brand-500"></i>
                    <span>Real-time Schedule Lookup</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span>Tìm Chuyến Kế Tiếp Tại Trạm</span>
                </h1>
                <p class="text-slate-500 text-xs sm:text-sm mt-1 max-w-2xl leading-relaxed">
                    Xử lý tự động theo <strong>ngày hoạt động</strong>, <strong>lịch ngoại lệ (thêm / hủy dịch vụ)</strong> và <strong>giờ chạy xuyên đêm (> 24:00:00)</strong> chuẩn GTFS.
                </p>
            </div>

            <!-- Badge Phiên Bản Lịch Đang Kích Hoạt -->
            <div class="shrink-0">
                <?php if ($active_version): ?>
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-white border border-emerald-200/80 shadow-xs text-xs font-semibold text-emerald-800">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span>Phiên bản active: <strong><?php echo htmlspecialchars($active_version['version_name']); ?></strong></span>
                    </div>
                <?php else: ?>
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-800">
                        <i class="bi bi-exclamation-triangle-fill text-amber-500"></i>
                        <span>Chưa kích hoạt phiên bản GTFS nào</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Flash Alerts: Thành công / Thất bại -->
        <?php if ($save_success): ?>
            <div class="p-4 rounded-2xl bg-emerald-50/90 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="bi bi-check-circle-fill text-base"></i>
                    </div>
                    <div>
                        <?php echo $save_success; ?>
                        <a href="saved_queries.php" class="font-bold underline ml-1 hover:text-emerald-950">Xem danh sách truy vấn đã lưu &rarr;</a>
                    </div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 p-1">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($save_error): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="bi bi-exclamation-triangle-fill text-base"></i>
                    </div>
                    <div><?php echo $save_error; ?></div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 p-1">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- MODULE / FORM TRA CỨU: MATERIAL DESIGN 3 / MODERN CLEAN       -->
        <!-- ============================================================= -->
        <section class="relative bg-white rounded-3xl shadow-[0_12px_36px_-6px_rgba(15,23,42,0.07),0_2px_8px_rgba(15,23,42,0.04)] border border-slate-200/80 p-6 sm:p-8 overflow-hidden transition-all duration-300">
            
            <!-- Dải màu gradient trang trí trên đầu Card (M3 Accent Bar) -->
            <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-brand-600 via-sky-500 to-indigo-600"></div>
            
            <!-- Hiệu ứng quầng sáng nền mờ nhẹ phía góc -->
            <div class="absolute -top-16 -right-16 w-56 h-56 bg-brand-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex items-center justify-between mb-6 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-brand-50 text-brand-600 border border-brand-100/80 flex items-center justify-center text-lg shadow-inner">
                        <i class="bi bi-search"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                            Bộ Lọc Tra Cứu Chuyến Kế Tiếp
                        </h2>
                        <p class="text-xs text-slate-500">
                            Điền thông tin trạm và mốc thời gian để tìm các chuyến xe buýt &amp; metro gần nhất
                        </p>
                    </div>
                </div>

                <!-- Thống kê nhanh số trạm -->
                <div class="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-100 text-slate-600 text-xs font-medium">
                    <i class="bi bi-geo-alt text-brand-600"></i>
                    <span><strong><?php echo count($stops); ?></strong> trạm khả dụng</span>
                </div>
            </div>

            <!-- Form Nhập Liệu Chính -->
            <form method="GET" action="next_trip.php" id="nextTripForm" class="space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    
                    <!-- 1. CHỌN TRẠM DỪNG (MD3 Elevated Select) -->
                    <div class="md:col-span-5 space-y-2">
                        <label for="stop_id" class="block text-xs sm:text-sm font-bold text-slate-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="bi bi-geo-alt-fill text-rose-500"></i>
                                <span>Trạm Dừng Đón Xe</span>
                                <span class="text-rose-500">*</span>
                            </span>
                            <span class="text-[11px] font-semibold text-slate-400">Mã trạm (stop_id)</span>
                        </label>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="bi bi-pin-map text-base"></i>
                            </div>
                            <select id="stop_id" name="stop_id" required
                                    class="w-full pl-10 pr-10 py-3 bg-slate-50/70 hover:bg-slate-100/60 focus:bg-white rounded-2xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 text-slate-800 text-sm font-medium transition-all duration-200 cursor-pointer">
                                <option value="" disabled <?php echo ($stop_id === '') ? 'selected' : ''; ?>>
                                    -- Chọn trạm dừng xe buýt / metro --
                                </option>
                                <?php foreach ($stops as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['stop_id']); ?>" <?php echo ($stop_id === $s['stop_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['stop_name']); ?> [Mã: <?php echo htmlspecialchars($s['stop_id']); ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="text-[11px] text-slate-500 flex items-center gap-1">
                            <i class="bi bi-info-circle text-brand-500"></i>
                            <span>Dữ liệu lấy trực tiếp từ bảng <code>gtfs_stops</code> của phiên bản lịch hiện hành</span>
                        </p>
                    </div>

                    <!-- 2. NGÀY TRA CỨU (MD3 Date Input + Quick Chips) -->
                    <div class="md:col-span-3 space-y-2">
                        <label for="date" class="block text-xs sm:text-sm font-bold text-slate-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="bi bi-calendar2-event-fill text-brand-600"></i>
                                <span>Ngày Khởi Hành</span>
                                <span class="text-rose-500">*</span>
                            </span>
                        </label>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="bi bi-calendar3 text-base"></i>
                            </div>
                            <input type="date" id="date" name="date" 
                                   value="<?php echo htmlspecialchars($date); ?>" required
                                   class="w-full pl-10 pr-4 py-3 bg-slate-50/70 hover:bg-slate-100/60 focus:bg-white rounded-2xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 text-slate-800 text-sm font-medium transition-all duration-200">
                        </div>

                        <!-- Material 3 Quick Date Preset Chips -->
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                            <button type="button" onclick="setQuickDate(0)" class="m3-chip bg-slate-100 hover:bg-brand-50 text-slate-700 hover:text-brand-700 border border-slate-200/80">
                                <i class="bi bi-calendar-check text-[10px]"></i> Hôm nay
                            </button>
                            <button type="button" onclick="setQuickDate(1)" class="m3-chip bg-slate-100 hover:bg-brand-50 text-slate-700 hover:text-brand-700 border border-slate-200/80">
                                <i class="bi bi-arrow-right text-[10px]"></i> Ngày mai
                            </button>
                        </div>
                    </div>

                    <!-- 3. GIỜ BẮT ĐẦU (MD3 Time Input + Quick Time Chips) -->
                    <div class="md:col-span-4 space-y-2">
                        <label for="time" class="block text-xs sm:text-sm font-bold text-slate-700 flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <i class="bi bi-clock-fill text-amber-500"></i>
                                <span>Giờ Bắt Đầu</span>
                                <span class="text-rose-500">*</span>
                            </span>
                            <span class="text-[11px] font-semibold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200">
                                Hỗ trợ &gt; 24:00
                            </span>
                        </label>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="bi bi-stopwatch text-base"></i>
                            </div>
                            <input type="text" id="time" name="time" 
                                   placeholder="HH:MM (ví dụ: 07:30 hoặc 24:15)"
                                   value="<?php echo htmlspecialchars($time); ?>" required
                                   class="w-full pl-10 pr-4 py-3 bg-slate-50/70 hover:bg-slate-100/60 focus:bg-white rounded-2xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 text-slate-800 text-sm font-medium font-mono transition-all duration-200">
                        </div>

                        <!-- Material 3 Quick Time Preset Chips -->
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                            <button type="button" onclick="setQuickTimeNow()" class="m3-chip bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200">
                                <i class="bi bi-clock text-[10px]"></i> Bây giờ
                            </button>
                            <button type="button" onclick="setQuickTime('06:00')" class="m3-chip bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80">
                                06:00
                            </button>
                            <button type="button" onclick="setQuickTime('07:30')" class="m3-chip bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80">
                                07:30
                            </button>
                            <button type="button" onclick="setQuickTime('17:30')" class="m3-chip bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200/80">
                                17:30
                            </button>
                            <button type="button" onclick="setQuickTime('24:15')" class="m3-chip bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200" title="Chuyến đêm chạy sau 24h">
                                <i class="bi bi-moon-stars text-[10px]"></i> 24:15
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Action Button Area: Material 3 Primary Filled Button -->
                <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100">
                    <div class="text-xs text-slate-500 flex items-center gap-1.5">
                        <i class="bi bi-shield-check text-emerald-600"></i>
                        <span>Thuật toán GTFS tự động tính lịch ngày trong tuần &amp; lịch nghỉ ngoại lệ</span>
                    </div>

                    <div class="flex items-center gap-2.5 w-full sm:w-auto">
                        <!-- Nút Reset -->
                        <button type="button" onclick="resetFormToNow()" class="w-full sm:w-auto px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition active:scale-[0.98] inline-flex items-center justify-center gap-1.5">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Đặt Lại</span>
                        </button>

                        <!-- Nút Submit Chính -->
                        <button type="submit" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-gradient-to-r from-brand-600 via-indigo-600 to-brand-700 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-sm sm:text-base shadow-md shadow-brand-500/25 hover:shadow-lg hover:shadow-brand-500/35 active:scale-[0.98] transition-all duration-200 inline-flex items-center justify-center gap-2">
                            <i class="bi bi-search text-base"></i>
                            <span>Tìm Chuyến Kế Tiếp</span>
                        </button>
                    </div>
                </div>

            </form>

        </section>

        <!-- ============================================================= -->
        <!-- KẾT QUẢ TÌM KIẾM CHUYẾN KẾ TIẾP                              -->
        <!-- ============================================================= -->
        <?php if ($searched && $selected_stop): ?>

            <section class="space-y-4">
                
                <!-- Thanh Thông Tin Kết Quả & Nút Lưu Truy Vấn -->
                <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 text-xs font-bold">
                                <i class="bi bi-geo-alt-fill"></i> Trạm: <?php echo htmlspecialchars($selected_stop['stop_id']); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700 text-xs font-semibold">
                                <i class="bi bi-calendar3"></i> Ngày <?php echo date('d/m/Y', strtotime($date)); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-semibold">
                                <i class="bi bi-clock-history"></i> Từ <?php echo htmlspecialchars($time); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-brand-50 text-brand-700 border border-brand-200 text-xs font-bold">
                                <?php echo count($next_trips); ?> chuyến sắp đến
                            </span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-2">
                            <?php echo htmlspecialchars($selected_stop['stop_name']); ?>
                        </h2>
                    </div>

                    <!-- Nút Lưu Truy Vấn Mẫu (Material 3 Elevated Button) -->
                    <?php if ($user): ?>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#saveQueryModal" 
                                class="shrink-0 px-4 py-2.5 rounded-2xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold text-xs sm:text-sm shadow-xs transition active:scale-[0.98] inline-flex items-center gap-2">
                            <i class="bi bi-bookmark-star-fill text-amber-500 text-base"></i>
                            <span>Lưu Bộ Lọc Này</span>
                        </button>
                    <?php else: ?>
                        <a href="../auth/login.php" class="shrink-0 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition inline-flex items-center gap-1.5">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Đăng nhập để lưu truy vấn</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- NẾU KHÔNG CÓ CHUYẾN NÀO: Material 3 Empty Card -->
                <?php if (empty($next_trips)): ?>
                    <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200/80 shadow-md text-center max-w-2xl mx-auto space-y-4">
                        <div class="w-16 h-16 mx-auto rounded-3xl bg-amber-50 text-amber-500 border border-amber-200 flex items-center justify-center text-3xl shadow-inner">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900">
                            Không Có Chuyến Nào Tiếp Theo!
                        </h3>
                        <p class="text-sm text-slate-500 leading-relaxed">
                            Không tìm thấy chuyến xe nào khởi hành tại trạm <strong><?php echo htmlspecialchars($selected_stop['stop_name']); ?></strong> 
                            sau thời điểm <strong><?php echo htmlspecialchars($time); ?></strong> ngày <strong><?php echo date('d/m/Y', strtotime($date)); ?></strong>.
                        </p>
                        
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 text-left text-xs text-slate-600 space-y-1.5">
                            <div class="font-bold text-slate-700">Nguyên nhân có thể là:</div>
                            <div class="flex items-center gap-2"><i class="bi bi-dot text-lg text-slate-400"></i> Các chuyến trong ngày đã kết thúc hoặc giờ tra cứu quá muộn.</div>
                            <div class="flex items-center gap-2"><i class="bi bi-dot text-lg text-slate-400"></i> Tuyến không chạy vào thứ này theo lịch chung (<code>calendar.txt</code>).</div>
                            <div class="flex items-center gap-2"><i class="bi bi-dot text-lg text-slate-400"></i> Ngày tra cứu thuộc danh sách hủy dịch vụ ngoại lệ (<code>calendar_dates.txt</code>).</div>
                        </div>

                        <div class="pt-2">
                            <a href="next_trip.php?stop_id=<?php echo urlencode($stop_id); ?>&date=<?php echo $date; ?>&time=06:00" 
                               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm shadow-md transition">
                                <i class="bi bi-arrow-clockwise"></i>
                                <span>Thử tra cứu từ 06:00 sáng</span>
                            </a>
                        </div>
                    </div>

                <!-- NẾU CÓ CHUYẾN: Material 3 Elevated Trip Cards Grid -->
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($next_trips as $t): 
                            $is_overnight = !empty($t['is_overnight']);
                        ?>
                            <div class="rounded-3xl bg-white border <?php echo $is_overnight ? 'border-purple-200/90 shadow-purple-500/5' : 'border-slate-200/80'; ?> shadow-[0_8px_25px_-5px_rgba(15,23,42,0.06)] hover:shadow-xl hover:-translate-y-1 transition-all duration-300 p-5 flex flex-col justify-between relative overflow-hidden group">
                                
                                <!-- Thẻ trạng thái & Dải tuyến -->
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-brand-50 text-brand-700 border border-brand-200 text-xs font-bold">
                                            <i class="bi <?php echo route_type_icon($t['route_type']); ?>"></i>
                                            <span>Tuyến <?php echo htmlspecialchars($t['route_short_name']); ?></span>
                                        </span>

                                        <?php if ($is_overnight): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-purple-100 text-purple-800 border border-purple-200 text-xs font-bold shadow-2xs">
                                                <i class="bi bi-moon-stars-fill text-purple-600"></i>
                                                <span>Chuyến Đêm (&gt;24h)</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
                                                <i class="bi bi-check-circle-fill text-emerald-500"></i>
                                                <span>Đúng Lịch</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Tên Tuyến & Hướng Đi -->
                                    <h3 class="text-base font-extrabold text-slate-900 group-hover:text-brand-600 transition-colors line-clamp-1 mb-1">
                                        <?php echo htmlspecialchars($t['route_long_name']); ?>
                                    </h3>

                                    <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-4">
                                        <i class="bi bi-compass text-slate-400"></i>
                                        <span class="truncate">Hướng: <strong><?php echo htmlspecialchars($t['trip_headsign'] ?: 'Theo lộ trình'); ?></strong></span>
                                        <span class="font-mono text-[10px] text-slate-400 shrink-0">#<?php echo htmlspecialchars($t['trip_id']); ?></span>
                                    </div>

                                    <!-- KHỐI THỜI GIAN KHỞI HÀNH (M3 Elevated Time Box) -->
                                    <div class="p-3.5 rounded-2xl bg-slate-50/90 border border-slate-100 mb-4 space-y-1.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-slate-500 font-medium">Giờ đón tại trạm:</span>
                                            <span class="text-xl font-extrabold text-brand-600 font-mono tracking-tight flex items-center gap-1">
                                                <i class="bi bi-clock-fill text-sm text-brand-500"></i>
                                                <?php echo htmlspecialchars($t['clock_time']); ?>
                                            </span>
                                        </div>

                                        <?php if ($is_overnight): ?>
                                            <div class="flex items-center justify-between pt-1 border-t border-slate-200/60 text-xs">
                                                <span class="text-slate-500 font-medium">Mã giờ GTFS:</span>
                                                <code class="text-purple-700 font-bold bg-purple-50 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($t['departure_time']); ?></code>
                                            </div>
                                            <div class="text-[11px] text-purple-700 flex items-center gap-1 pt-0.5">
                                                <i class="bi bi-info-circle-fill"></i>
                                                <span>Lịch ngày <?php echo $t['service_date'] ?? $t['actual_date']; ?>, chạy rạng sáng <?php echo $t['actual_date']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Nút Xem Lần Dừng Chi Tiết -->
                                <div class="pt-2 border-t border-slate-100">
                                    <a href="gtfs_browse.php?tab=trips&trip_id=<?php echo urlencode($t['trip_id']); ?>" 
                                       class="w-full py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 text-xs font-bold transition flex items-center justify-center gap-1.5">
                                        <i class="bi bi-list-ol"></i>
                                        <span>Xem Lộ Trình Lần Dừng</span>
                                    </a>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </section>

        <?php elseif ($searched && !$selected_stop): ?>
            <div class="p-6 rounded-3xl bg-rose-50 border border-rose-200 text-center text-rose-800">
                <i class="bi bi-x-circle-fill text-2xl text-rose-500 mb-2"></i>
                <p class="font-bold">Mã trạm không hợp lệ hoặc không tồn tại trong phiên bản lịch GTFS này.</p>
            </div>
        <?php else: ?>
            <!-- Trạng thái ban đầu chưa chọn trạm: Hero Guide Card -->
            <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200/80 shadow-md text-center max-w-xl mx-auto space-y-3">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-brand-50 text-brand-600 border border-brand-100 flex items-center justify-center text-3xl shadow-inner">
                    <i class="bi bi-bus-front"></i>
                </div>
                <h3 class="text-lg sm:text-xl font-bold text-slate-900">
                    Chọn một trạm dừng để bắt đầu tra cứu
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                    Hệ thống sẽ đối chiếu ngày trong tuần, lịch nghỉ ngoại lệ và các chuyến xuyên đêm để hiển thị danh sách các chuyến kế tiếp chuẩn xác nhất.
                </p>
            </div>
        <?php endif; ?>

    </main>

    <!-- ============================================================= -->
    <!-- MODAL LƯU TRUY VẤN MẪU: MATERIAL DESIGN 3 DIALOG              -->
    <!-- ============================================================= -->
    <?php if ($user && $searched && $selected_stop): ?>
        <div class="modal fade" id="saveQueryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-3xl border-0 shadow-2xl overflow-hidden">
                    <form method="POST" action="next_trip.php?stop_id=<?php echo urlencode($stop_id); ?>&date=<?php echo urlencode($date); ?>&time=<?php echo urlencode($time); ?>">
                        <input type="hidden" name="action" value="save_query">
                        
                        <!-- Header Modal -->
                        <div class="p-6 bg-gradient-to-r from-brand-600 to-indigo-600 text-white flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-xl">
                                    <i class="bi bi-bookmark-star-fill text-amber-300"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-extrabold tracking-tight">Lưu Truy Vấn Mẫu</h4>
                                    <p class="text-xs text-blue-100">Dễ dàng chạy lại khi đổi sang phiên bản lịch mới</p>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <!-- Body Modal -->
                        <div class="p-6 space-y-4">
                            <div>
                                <label for="query_name" class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Đặt tên gợi nhớ cho truy vấn:
                                </label>
                                <input type="text" class="w-full px-4 py-3 bg-slate-50 rounded-2xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 text-slate-800 text-sm font-medium transition" 
                                       id="query_name" name="query_name" 
                                       value="Chuyến tiếp theo tại <?php echo htmlspecialchars($selected_stop['stop_name']); ?> (<?php echo htmlspecialchars($time); ?>)" required>
                            </div>

                            <!-- Thẻ tóm tắt thông số -->
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5 text-xs text-slate-600">
                                <div class="font-bold text-slate-700 mb-1">Thông số truy vấn đã ghi nhận:</div>
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-geo-alt-fill text-rose-500"></i>
                                    <span>Trạm: <strong><?php echo htmlspecialchars($selected_stop['stop_name']); ?></strong> (<code><?php echo htmlspecialchars($selected_stop['stop_id']); ?></code>)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-calendar3 text-brand-600"></i>
                                    <span>Ngày: <strong><?php echo htmlspecialchars($date); ?></strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="bi bi-clock-fill text-amber-500"></i>
                                    <span>Giờ bắt đầu: <strong><?php echo htmlspecialchars($time); ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Modal -->
                        <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2.5">
                            <button type="button" class="px-4 py-2.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold transition" data-bs-dismiss="modal">
                                Hủy bỏ
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md shadow-brand-500/20 transition flex items-center gap-1.5">
                                <i class="bi bi-check2"></i>
                                <span>Xác Nhận Lưu</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Scripts: Xử lý Quick Chips & Tương tác giao diện -->
    <script>
        // Preset ngày nhanh: 0 = Hôm nay, 1 = Ngày mai
        function setQuickDate(offsetDays) {
            const d = new Date();
            d.setDate(d.getDate() + offsetDays);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            document.getElementById('date').value = `${yyyy}-${mm}-${dd}`;
        }

        // Preset giờ nhanh
        function setQuickTime(timeStr) {
            document.getElementById('time').value = timeStr;
        }

        function setQuickTimeNow() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('time').value = `${hh}:${mm}`;
        }

        function resetFormToNow() {
            setQuickDate(0);
            setQuickTimeNow();
        }

        // Mobile drawer menu toggle
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const iconBars = document.getElementById('menu-icon-bars');
        const iconClose = document.getElementById('menu-icon-close');

        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', function() {
                const isOpen = !mobileMenu.classList.contains('hidden');
                if (isOpen) {
                    mobileMenu.classList.add('hidden');
                    iconBars.classList.remove('hidden');
                    iconClose.classList.add('hidden');
                } else {
                    mobileMenu.classList.remove('hidden');
                    iconBars.classList.add('hidden');
                    iconClose.classList.remove('hidden');
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
