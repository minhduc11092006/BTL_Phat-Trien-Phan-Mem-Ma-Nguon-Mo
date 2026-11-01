<?php
// saved_queries.php - Quản lý và chạy lại các truy vấn mẫu đã lưu trên phiên bản lịch hiện tại
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gtfs_functions.php';

require_login('saved_queries.php');
$user = current_user();
$active_version = get_active_feed_version();
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : ($active_version['id'] ?? 0);

$msg = '';
$error = '';

// Xử lý Xóa truy vấn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $qid = (int)($_POST['query_id'] ?? 0);
    if (delete_saved_query($qid, $user['id'])) {
        $msg = 'Đã xóa truy vấn mẫu thành công.';
    } else {
        $error = 'Không thể xóa truy vấn này.';
    }
}

// Xử lý Chạy lại truy vấn
$run_qid = isset($_GET['run']) ? (int)$_GET['run'] : 0;
$query_to_run = null;
$run_results = null;

if ($run_qid > 0) {
    $query_to_run = get_saved_query($run_qid);
    if ($query_to_run && (int)$query_to_run['user_id'] === (int)$user['id']) {
        if ($active_version) {
            $p = $query_to_run['parameters'];
            if ($query_to_run['query_type'] === 'next_trip' && !empty($p['stop_id'])) {
                $stop = get_stop($p['stop_id'], $active_version['id']);
                $date = $p['date'] ?? date('Y-m-d');
                $time = $p['time'] ?? date('H:i');
                $run_results = [
                    'stop' => $stop,
                    'date' => $date,
                    'time' => $time,
                    'trips' => find_next_trips($p['stop_id'], $date, $time, $active_version['id'], 15)
                ];
            }
        }
    }
}

$saved_queries = get_saved_queries($user['id']);
?>
<!DOCTYPE html>
<html lang="vi" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Truy Vấn Mẫu Đã Lưu - GTFS</title>
    
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

        .m3-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.35rem 0.85rem;
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
    <!-- NAVBAR CHUẨN PARALLAX - NÚT "TRUY VẤN ĐÃ LƯU" ĐANG ACTIVE    -->
    <!-- ============================================================= -->
    <header class="sticky top-0 z-50 bg-slate-900/95 backdrop-blur-md border-b border-slate-800/80 transition-all overflow-hidden relative shadow-lg">
        
        <!-- LỚP PARALLAX CẢNH VÔ TẬN & XE BUS ĐÔ THỊ ĐANG CHẠY LIÊN TỤC -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none select-none z-0">
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

            <!-- Lớp Parallax 2: City Skyline -->
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

            <!-- Lớp Parallax 3: Cao tốc -->
            <div class="absolute bottom-0 inset-x-0 h-[3px] bg-slate-950/90 border-t border-slate-800/80 flex items-center overflow-hidden">
                <div class="w-full h-full animate-highway-track opacity-85"></div>
            </div>
            <div class="absolute bottom-1 inset-x-0 h-[1px] bg-gradient-to-r from-transparent via-cyan-500/25 to-transparent"></div>

            <!-- Xe Buýt Đang Chạy Liên Tục -->
            <div class="absolute bottom-[3px] left-3 sm:left-6 md:left-10 flex items-end z-10 animate-bus-bounce">
                <div class="relative flex items-center">
                    <div class="absolute -left-2 bottom-1 pointer-events-none">
                        <div class="w-2.5 h-2.5 rounded-full bg-slate-400/40 blur-[1px] animate-exhaust-puff-1"></div>
                        <div class="w-2 h-2 rounded-full bg-slate-300/35 blur-[1px] animate-exhaust-puff-2 -mt-1"></div>
                    </div>
                    <div class="w-12 h-3.5 bg-gradient-to-l from-emerald-500/30 via-cyan-500/15 to-transparent blur-[2px] -mr-1"></div>

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

                        <rect x="5" y="4" width="83" height="21" rx="5" fill="url(#navBusPaintGrad)" stroke="#0f172a" stroke-width="1.2" />
                        <path d="M6 7 Q 48 3 87 7" stroke="#6ee7b7" stroke-width="1.2" fill="none" />
                        <rect x="8" y="6" width="14" height="9" rx="1.5" fill="#0f172a" />
                        <rect x="24" y="6" width="15" height="9" rx="1.5" fill="#0f172a" />
                        <rect x="41" y="6" width="15" height="9" rx="1.5" fill="#0f172a" />
                        <rect x="58" y="6" width="15" height="9" rx="1.5" fill="#0f172a" />
                        <path d="M75 6 H83 C86 6 87 7.5 87 9.5 V15 H75 V6 Z" fill="#0f172a" />
                        
                        <text x="42" y="12.5" fill="#38bdf8" font-size="5" font-family="monospace" font-weight="bold" letter-spacing="0.5">BUS 01</text>
                        
                        <rect x="5" y="16" width="2" height="5" rx="0.8" fill="#f43f5e" class="animate-taillight-trail" />
                        <circle cx="86" cy="17.5" r="2" fill="#fef08a" class="animate-headlight-pulse" />
                        <polygon points="88,17.5 110,12 110,24" fill="url(#navBusBeamGrad)" class="animate-headlight-pulse opacity-85" />

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

        <!-- Links Navbar: TRUY VẤN ĐÃ LƯU ĐANG ACTIVE -->
        <div class="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-[70px] gap-2">
                
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

                <!-- Desktop Links: Nút Truy Vấn Đã Lưu Active -->
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

                    <a href="next_trip.php" class="h-9 px-3 text-xs xl:text-sm font-medium rounded-lg text-slate-200 hover:bg-slate-800/80 hover:text-white transition inline-flex items-center gap-1.5 whitespace-nowrap shrink-0">
                        <i class="bi bi-clock-history text-amber-400 text-sm"></i>
                        <span>Chuyến Kế Tiếp</span>
                    </a>
                    
                    <?php if ($user): ?>
                        <a href="saved_queries.php" class="h-9 px-3 text-xs xl:text-sm font-semibold rounded-lg bg-brand-600/50 text-white shadow-sm inline-flex items-center gap-1.5 transition hover:bg-brand-600/60 whitespace-nowrap shrink-0">
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
            <a href="next_trip.php" class="block px-3 py-2.5 rounded-xl text-base font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
                <i class="bi bi-clock-history text-amber-400 mr-2"></i> Chuyến Kế Tiếp
            </a>
            
            <?php if ($user): ?>
                <a href="saved_queries.php" class="block px-3 py-2.5 rounded-xl text-base font-semibold text-white bg-brand-600/50 transition">
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

        <!-- Banner Tiêu Đề & Nút Hành Động -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-50 border border-amber-200/80 text-amber-800 text-xs font-bold tracking-wide uppercase mb-2">
                    <i class="bi bi-star-fill text-amber-500"></i>
                    <span>Personal Bookmarks</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span>Truy Vấn Mẫu Đã Lưu</span>
                </h1>
                <p class="text-slate-500 text-xs sm:text-sm mt-1 max-w-2xl leading-relaxed">
                    Lưu trữ các lộ trình và bộ lọc thường xuyên tra cứu; dễ dàng chạy lại chỉ với 1 cú click khi hệ thống cập nhật phiên bản lịch trình mới.
                </p>
            </div>

            <!-- Nút Tra Cứu & Lưu Mới -->
            <div class="shrink-0">
                <a href="next_trip.php" class="px-5 py-3 rounded-2xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-500 hover:to-indigo-500 text-white font-bold text-xs sm:text-sm shadow-md shadow-brand-500/25 hover:shadow-lg transition-all active:scale-[0.98] inline-flex items-center gap-2">
                    <i class="bi bi-plus-circle text-base"></i>
                    <span>Tra Cứu &amp; Lưu Truy Vấn Mới</span>
                </a>
            </div>
        </div>

        <!-- Flash Alerts: Thông báo kết quả thao tác -->
        <?php if ($msg): ?>
            <div class="p-4 rounded-2xl bg-emerald-50/90 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="bi bi-check-circle-fill text-base"></i>
                    </div>
                    <div><?php echo htmlspecialchars($msg); ?></div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 p-1">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i class="bi bi-exclamation-triangle-fill text-base"></i>
                    </div>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 p-1">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- MODULE 1: KẾT QUẢ CHẠY LẠI TRUY VẤN (NẾU ĐƯỢC CHỌN RUN)      -->
        <!-- ============================================================= -->
        <?php if ($run_results): ?>
            <section class="relative bg-white rounded-3xl shadow-[0_12px_36px_-6px_rgba(15,23,42,0.07),0_2px_8px_rgba(15,23,42,0.04)] border border-emerald-200/90 p-6 sm:p-8 overflow-hidden transition-all duration-300">
                <div class="absolute top-0 inset-x-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-brand-500"></div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold mb-2">
                            <i class="bi bi-check-circle-fill text-emerald-600"></i>
                            <span>Kết quả chạy lại trên phiên bản: <strong><?php echo htmlspecialchars($active_version['version_name'] ?? 'Hiện hành'); ?></strong></span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
                            <?php echo htmlspecialchars($query_to_run['query_name']); ?>
                        </h2>
                        <div class="flex items-center gap-2 flex-wrap mt-2">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 text-xs font-bold border border-rose-200">
                                <i class="bi bi-geo-alt-fill"></i> Trạm: <?php echo htmlspecialchars($run_results['stop']['stop_name'] ?? 'N/A'); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700 text-xs font-semibold">
                                <i class="bi bi-calendar3"></i> Ngày <?php echo date('d/m/Y', strtotime($run_results['date'])); ?>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-amber-50 text-amber-800 text-xs font-semibold border border-amber-200">
                                <i class="bi bi-clock"></i> Khởi hành từ: <?php echo htmlspecialchars($run_results['time']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="shrink-0">
                        <a href="saved_queries.php" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition inline-flex items-center gap-1.5">
                            <i class="bi bi-x-lg"></i>
                            <span>Đóng Kết Quả</span>
                        </a>
                    </div>
                </div>

                <?php if (empty($run_results['trips'])): ?>
                    <div class="p-6 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-center text-sm font-medium">
                        <i class="bi bi-exclamation-triangle-fill text-xl text-amber-500 mb-1 d-block"></i>
                        Không có chuyến xe nào tiếp theo cho truy vấn này trên phiên bản lịch trình hiện tại.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-2xl border border-slate-100 shadow-2xs">
                        <table class="w-full text-left border-collapse text-xs sm:text-sm">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-600 font-bold border-b border-slate-200/80">
                                    <th class="py-3 px-4">Tuyến Xe</th>
                                    <th class="py-3 px-4">Mã Chuyến (trip_id)</th>
                                    <th class="py-3 px-4">Hướng Tuyến (Headsign)</th>
                                    <th class="py-3 px-4">Giờ Khởi Hành</th>
                                    <th class="py-3 px-4">Trạng Thái</th>
                                    <th class="py-3 px-4 text-right">Chi Tiết</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                                <?php foreach ($run_results['trips'] as $t): ?>
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-brand-50 text-brand-700 border border-brand-200 font-bold text-xs">
                                                Tuyến <?php echo htmlspecialchars($t['route_short_name']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono text-slate-500">
                                            <code><?php echo htmlspecialchars($t['trip_id']); ?></code>
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-900">
                                            <?php echo htmlspecialchars($t['trip_headsign'] ?: 'Theo lộ trình'); ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-extrabold text-brand-600 font-mono text-sm sm:text-base">
                                            <?php echo htmlspecialchars($t['clock_time']); ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if (!empty($t['is_overnight'])): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200 text-[11px] font-bold">
                                                    <i class="bi bi-moon-stars text-purple-600"></i> Chuyến Đêm (&gt;24h)
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                                                    <i class="bi bi-check-circle"></i> Đúng Lịch
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <a href="gtfs_browse.php?tab=trips&trip_id=<?php echo urlencode($t['trip_id']); ?>" 
                                               class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-bold text-xs transition inline-flex items-center gap-1">
                                                <span>Xem Lần Dừng</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- MODULE 2: BỘ LỌC & TÌM KIẾM NHANH TRUY VẤN (M3 FILTER BAR)     -->
        <!-- ============================================================= -->
        <section class="bg-white rounded-3xl shadow-[0_10px_35px_-5px_rgba(15,23,42,0.06),0_2px_8px_rgba(15,23,42,0.03)] border border-slate-200/80 p-5 sm:p-6 transition-all">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <!-- Thanh tìm kiếm nhanh (Live Search Input) -->
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="bi bi-search text-base"></i>
                    </span>
                    <input type="text" id="filterQueryInput" oninput="filterSavedQueries()" 
                           placeholder="Tìm kiếm theo tên truy vấn, mã trạm, hoặc thời gian..." 
                           class="w-full pl-10 pr-10 py-3 bg-slate-50/70 hover:bg-slate-100/60 focus:bg-white rounded-2xl border border-slate-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 text-slate-800 text-sm font-medium transition-all duration-200">
                    <button type="button" id="clearFilterBtn" onclick="clearFilter()" class="hidden absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>

                <!-- Chips phân loại nhanh -->
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <button type="button" onclick="setCategoryFilter('all', this)" class="m3-chip bg-brand-600 text-white shadow-sm filter-chip active-filter" data-type="all">
                        <span>Tất cả</span>
                        <span id="allCountBadge" class="bg-white/20 text-white px-1.5 py-0.2 rounded-full text-[10px]"><?php echo count($saved_queries); ?></span>
                    </button>
                    <button type="button" onclick="setCategoryFilter('next_trip', this)" class="m3-chip bg-slate-100 hover:bg-slate-200 text-slate-700 filter-chip" data-type="next_trip">
                        <i class="bi bi-clock-history text-amber-500"></i>
                        <span>Chuyến Kế Tiếp</span>
                    </button>
                </div>

            </div>
        </section>

        <!-- ============================================================= -->
        <!-- MODULE 3: DANH SÁCH CÁC TRUY VẤN MẪU ĐÃ LƯU                   -->
        <!-- ============================================================= -->
        <section class="bg-white rounded-3xl shadow-[0_12px_36px_-6px_rgba(15,23,42,0.07),0_2px_8px_rgba(15,23,42,0.04)] border border-slate-200/80 p-6 sm:p-8 overflow-hidden">
            
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-lg shadow-inner">
                        <i class="bi bi-bookmark-star-fill text-amber-500"></i>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                            Danh Sách Các Truy Vấn Đã Lưu Của Bạn
                        </h2>
                        <p class="text-xs text-slate-500">
                            Quản lý, chạy lại hoặc xóa các bộ lọc tra cứu cá nhân
                        </p>
                    </div>
                </div>

                <div class="text-xs text-slate-500 font-medium">
                    Hiển thị <span id="visibleCount" class="font-bold text-slate-900"><?php echo count($saved_queries); ?></span> / <?php echo count($saved_queries); ?> truy vấn
                </div>
            </div>

            <?php if (empty($saved_queries)): ?>
                <!-- Trạng thái trống: Material 3 Empty Card -->
                <div class="text-center py-12 px-4 max-w-md mx-auto space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-3xl bg-amber-50 text-amber-500 border border-amber-200 flex items-center justify-center text-3xl shadow-inner">
                        <i class="bi bi-bookmark-plus"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">
                        Bạn chưa lưu truy vấn mẫu nào!
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                        Khi tra cứu ở trang <strong>Tìm Chuyến Kế Tiếp</strong>, nhấn nút <em>"Lưu Bộ Lọc Này"</em> để lưu lại lộ trình và trạm dừng yêu thích.
                    </p>
                    <div>
                        <a href="next_trip.php" class="px-5 py-2.5 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs sm:text-sm shadow-md transition inline-flex items-center gap-1.5">
                            <i class="bi bi-search"></i>
                            <span>Đến trang Tìm Chuyến Kế Tiếp</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Bảng Hiển Thị Material 3 Phân Tầng Hiện Đại -->
                <div class="overflow-x-auto rounded-2xl border border-slate-100 shadow-2xs">
                    <table class="w-full text-left border-collapse text-xs sm:text-sm" id="queriesTable">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-600 font-bold border-b border-slate-200/80">
                                <th class="py-3 px-4">Tên Truy Vấn</th>
                                <th class="py-3 px-4">Loại Tra Cứu</th>
                                <th class="py-3 px-4">Tham Số Đã Lưu</th>
                                <th class="py-3 px-4">Ngày Tạo</th>
                                <th class="py-3 px-4 text-right">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 font-medium" id="queriesTableBody">
                            <?php foreach ($saved_queries as $q): 
                                $params = json_decode($q['parameters'], true);
                                $searchContent = strtolower($q['query_name'] . ' ' . ($params['stop_id'] ?? '') . ' ' . ($params['time'] ?? '') . ' ' . ($params['date'] ?? ''));
                            ?>
                                <tr class="query-row hover:bg-slate-50/80 transition-colors" data-type="<?php echo htmlspecialchars($q['query_type']); ?>" data-search="<?php echo htmlspecialchars($searchContent); ?>">
                                    
                                    <!-- Tên Truy Vấn -->
                                    <td class="py-4 px-4 font-bold text-slate-900">
                                        <div class="flex items-center gap-2">
                                            <i class="bi bi-bookmark-fill text-amber-500 text-sm"></i>
                                            <span class="query-title"><?php echo htmlspecialchars($q['query_name']); ?></span>
                                        </div>
                                    </td>

                                    <!-- Loại Truy Vấn -->
                                    <td class="py-4 px-4">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold uppercase">
                                            <i class="bi bi-clock-history"></i>
                                            <span><?php echo htmlspecialchars($q['query_type']); ?></span>
                                        </span>
                                    </td>

                                    <!-- Tham Số Tra Cứu (M3 Chips) -->
                                    <td class="py-4 px-4">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <?php if (!empty($params['stop_id'])): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 text-xs border border-rose-200 font-semibold">
                                                    <i class="bi bi-geo-alt-fill text-[11px]"></i>
                                                    <span>Trạm: <code><?php echo htmlspecialchars($params['stop_id']); ?></code></span>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($params['time'])): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 text-amber-800 text-xs border border-amber-200 font-semibold font-mono">
                                                    <i class="bi bi-clock text-[11px]"></i>
                                                    <span><?php echo htmlspecialchars($params['time']); ?></span>
                                                </span>
                                            <?php endif; ?>

                                            <?php if (!empty($params['date'])): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">
                                                    <i class="bi bi-calendar-event text-[11px]"></i>
                                                    <span><?php echo htmlspecialchars($params['date']); ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Thời Gian Lưu -->
                                    <td class="py-4 px-4 text-xs text-slate-500">
                                        <?php echo date('d/m/Y H:i', strtotime($q['created_at'])); ?>
                                    </td>

                                    <!-- Cột Thao Tác -->
                                    <td class="py-4 px-4 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Nút Chạy Lại -->
                                            <a href="saved_queries.php?run=<?php echo $q['id']; ?>" 
                                               class="px-3 py-1.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-xs transition active:scale-[0.98] inline-flex items-center gap-1"
                                               title="Chạy lại truy vấn này trên phiên bản lịch active hiện tại">
                                                <i class="bi bi-arrow-repeat"></i>
                                                <span>Chạy Lại</span>
                                            </a>

                                            <!-- Nút Mở trang next_trip -->
                                            <?php if ($q['query_type'] === 'next_trip' && !empty($params['stop_id'])): ?>
                                                <a href="next_trip.php?stop_id=<?php echo urlencode($params['stop_id']); ?>&date=<?php echo urlencode($params['date'] ?? date('Y-m-d')); ?>&time=<?php echo urlencode($params['time'] ?? date('H:i')); ?>" 
                                                   class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 inline-flex items-center justify-center transition"
                                                   title="Mở trong trang tìm kiếm">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Nút Xóa qua Modal M3 Xác Nhận -->
                                            <button type="button" 
                                                    onclick="openDeleteModal(<?php echo $q['id']; ?>, '<?php echo addslashes(htmlspecialchars($q['query_name'])); ?>')" 
                                                    class="w-8 h-8 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 inline-flex items-center justify-center transition" 
                                                    title="Xóa truy vấn">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Thông báo khi filter không ra kết quả -->
                <div id="noMatchAlert" class="hidden text-center py-8 text-slate-500 text-xs sm:text-sm">
                    <i class="bi bi-search text-2xl text-slate-300 d-block mb-2"></i>
                    Không tìm thấy truy vấn nào phù hợp với từ khóa của bạn.
                </div>
            <?php endif; ?>

        </section>

    </main>

    <!-- ============================================================= -->
    <!-- MODAL XÁC NHẬN XÓA: MATERIAL DESIGN 3 DIALOG                  -->
    <!-- ============================================================= -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content rounded-3xl border-0 shadow-2xl overflow-hidden p-6 text-center space-y-4">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center text-2xl shadow-inner">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Xóa Truy Vấn Này?</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Bạn có chắc chắn muốn xóa: <strong id="deleteTargetName" class="text-slate-800"></strong>?
                    </p>
                </div>
                <form method="POST" action="saved_queries.php" class="flex items-center justify-center gap-2 pt-2">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="query_id" id="deleteTargetId" value="">
                    
                    <button type="button" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition" data-bs-dismiss="modal">
                        Hủy Bỏ
                    </button>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-500/20 transition">
                        Xác Nhận Xóa
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SCRIPT XỬ LÝ LIVE FILTER & MODAL -->
    <script>
        let currentCategory = 'all';

        function filterSavedQueries() {
            const input = document.getElementById('filterQueryInput');
            const kw = input.value.trim().toLowerCase();
            const clearBtn = document.getElementById('clearFilterBtn');
            
            if (kw.length > 0) {
                clearBtn.classList.remove('hidden');
            } else {
                clearBtn.classList.add('hidden');
            }

            const rows = document.querySelectorAll('.query-row');
            let visible = 0;

            rows.forEach(row => {
                const rowType = row.getAttribute('data-type');
                const rowSearch = row.getAttribute('data-search');

                const matchesCategory = (currentCategory === 'all' || rowType === currentCategory);
                const matchesKeyword = (kw === '' || rowSearch.includes(kw));

                if (matchesCategory && matchesKeyword) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            const visibleCountEl = document.getElementById('visibleCount');
            if (visibleCountEl) visibleCountEl.textContent = visible;

            const noMatchAlert = document.getElementById('noMatchAlert');
            if (noMatchAlert) {
                if (visible === 0 && rows.length > 0) {
                    noMatchAlert.classList.remove('hidden');
                } else {
                    noMatchAlert.classList.add('hidden');
                }
            }
        }

        function clearFilter() {
            const input = document.getElementById('filterQueryInput');
            input.value = '';
            filterSavedQueries();
        }

        function setCategoryFilter(category, btn) {
            currentCategory = category;
            
            document.querySelectorAll('.filter-chip').forEach(b => {
                b.classList.remove('bg-brand-600', 'text-white', 'shadow-sm');
                b.classList.add('bg-slate-100', 'text-slate-700');
            });

            btn.classList.remove('bg-slate-100', 'text-slate-700');
            btn.classList.add('bg-brand-600', 'text-white', 'shadow-sm');

            filterSavedQueries();
        }

        function openDeleteModal(queryId, queryName) {
            document.getElementById('deleteTargetId').value = queryId;
            document.getElementById('deleteTargetName').textContent = queryName;
            const modalEl = document.getElementById('deleteConfirmModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
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
