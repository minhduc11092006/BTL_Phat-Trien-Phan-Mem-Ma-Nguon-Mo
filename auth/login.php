<?php
// login.php - Đăng nhập tài khoản & Phân luồng theo Role (Admin / User)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Nếu đã đăng nhập trước đó thì phân luồng trực tiếp
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ../admin/gtfs.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

$error = '';
$username = '';
$redirect_target = $_GET['redirect'] ?? '';

// Nhận các Flash message từ Session
$auth_warning = $_SESSION['auth_warning'] ?? '';
unset($_SESSION['auth_warning']);

$auth_success = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $redirect_target = trim($_POST['redirect'] ?? '');
    $remember        = isset($_POST['remember']);

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu.';
    } else {
        try {
            // 1. Truy vấn tài khoản theo username dùng Prepared Statement
            $stmt = $pdo->prepare('SELECT id, username, password, fullname, email, role FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // 2. Sử dụng password_verify() để đối chiếu mật khẩu người dùng nhập
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['role']     = $user['role']; // 'admin' hoặc 'user'

                // 3. Phân luồng điều hướng (Role-based Routing)
                if ($user['role'] === 'admin') {
                    header('Location: ../admin/gtfs.php');
                    exit;
                } else {
                    if (!empty($redirect_target) && filter_var($redirect_target, FILTER_VALIDATE_URL) === false && strpos($redirect_target, 'login.php') === false) {
                        if (strpos($redirect_target, '../') === 0) {
                            $dest = $redirect_target;
                        } elseif (strpos($redirect_target, 'admin/') === 0 || strpos($redirect_target, 'views/') === 0 || strpos($redirect_target, 'auth/') === 0) {
                            $dest = '../' . $redirect_target;
                        } elseif (preg_match('/^(saved_queries|next_trip|gtfs_browse|book_ticket|process_booking)\.php/', $redirect_target)) {
                            $dest = '../views/' . $redirect_target;
                        } elseif (preg_match('/^(index|export)\.php/', $redirect_target)) {
                            $dest = '../' . $redirect_target;
                        } else {
                            $dest = '../' . ltrim($redirect_target, '/');
                        }
                        header("Location: {$dest}");
                        exit;
                    }
                    header('Location: ../index.php');
                    exit;
                }
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ Thống Đặt Vé Công Cộng</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
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
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="min-h-full bg-slate-100/90 text-slate-800 font-sans flex items-center justify-center p-3 sm:p-6 lg:p-8 selection:bg-blue-600 selection:text-white">

    <!-- Card Wrapper Container -->
    <div class="w-full max-w-4xl bg-white rounded-[28px] shadow-2xl shadow-blue-900/10 border border-slate-200/80 overflow-hidden grid grid-cols-1 md:grid-cols-2 p-3 sm:p-4 gap-4">
        
        <!-- ============================================================= -->
        <!-- 1. LEFT BANNER CARD: HỆ THỐNG ĐẶT VÉ CÔNG CỘNG               -->
        <!-- ============================================================= -->
        <div class="relative rounded-[22px] bg-gradient-to-b from-[#4285f4] via-[#3b82f6] to-[#2563eb] text-white p-6 sm:p-8 flex flex-col justify-between overflow-hidden shadow-lg shadow-blue-500/20">
            
            <!-- Hiệu ứng dải mây / hạt sáng nền mờ -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute top-1/2 -left-12 w-40 h-40 bg-sky-300/15 rounded-full blur-2xl pointer-events-none"></div>

            <!-- Top Header Banner -->
            <div class="relative z-10 text-center pt-1">
                <!-- Bus Icon Container -->
                <div class="w-16 h-16 mx-auto mb-3.5 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center shadow-inner">
                    <svg class="w-9 h-9 text-white fill-current" viewBox="0 0 24 24">
                        <path d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z"/>
                    </svg>
                </div>
                <h2 class="text-2xl sm:text-[26px] font-extrabold tracking-tight text-white leading-tight">
                    Hệ Thống Đặt Vé<br>Công Cộng
                </h2>
                <p class="text-xs sm:text-sm text-blue-100/90 mt-2 font-medium max-w-[240px] mx-auto leading-relaxed">
                    Vui lòng đăng nhập để tiếp tục<br>sử dụng hệ thống
                </p>
            </div>

            <!-- Center Illustration: Xe Bus & Thành phố hiện đại (SVG Vector sắc nét) -->
            <div class="relative z-10 my-4 py-2 flex items-center justify-center">
                <svg class="w-full max-w-[280px] h-auto drop-shadow-md" viewBox="0 0 320 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Bầu trời & Mây trắng trôi -->
                    <path d="M40 55 C40 48, 52 48, 56 52 C60 46, 75 46, 78 52 C84 52, 88 56, 88 60 H40 Z" fill="#ffffff" fill-opacity="0.35"/>
                    <path d="M220 40 C220 34, 230 34, 234 38 C238 32, 252 32, 255 38 C260 38, 264 42, 264 46 H220 Z" fill="#ffffff" fill-opacity="0.4"/>

                    <!-- Dãy nhà cao ốc thành phố phía sau (City Skyline) -->
                    <rect x="50" y="65" width="22" height="70" rx="2" fill="#93c5fd" fill-opacity="0.45"/>
                    <rect x="76" y="45" width="30" height="90" rx="3" fill="#60a5fa" fill-opacity="0.5"/>
                    <polygon points="91,32 87,45 95,45" fill="#60a5fa" fill-opacity="0.6"/>
                    <rect x="110" y="70" width="24" height="65" rx="2" fill="#93c5fd" fill-opacity="0.4"/>
                    <rect x="140" y="38" width="36" height="97" rx="3" fill="#60a5fa" fill-opacity="0.65"/>
                    <polygon points="158,25 154,38 162,38" fill="#60a5fa" fill-opacity="0.75"/>
                    <rect x="180" y="60" width="26" height="75" rx="2" fill="#93c5fd" fill-opacity="0.45"/>
                    <rect x="210" y="48" width="32" height="87" rx="3" fill="#60a5fa" fill-opacity="0.5"/>
                    <rect x="246" y="72" width="24" height="63" rx="2" fill="#93c5fd" fill-opacity="0.4"/>

                    <!-- Các ô cửa sổ cao ốc phát sáng nhẹ -->
                    <rect x="82" y="55" width="4" height="5" rx="0.5" fill="#ffffff" fill-opacity="0.6"/>
                    <rect x="94" y="55" width="4" height="5" rx="0.5" fill="#ffffff" fill-opacity="0.6"/>
                    <rect x="82" y="66" width="4" height="5" rx="0.5" fill="#ffffff" fill-opacity="0.6"/>
                    <rect x="94" y="66" width="4" height="5" rx="0.5" fill="#ffffff" fill-opacity="0.6"/>
                    <rect x="148" y="48" width="5" height="6" rx="0.5" fill="#ffffff" fill-opacity="0.7"/>
                    <rect x="160" y="48" width="5" height="6" rx="0.5" fill="#ffffff" fill-opacity="0.7"/>
                    <rect x="148" y="60" width="5" height="6" rx="0.5" fill="#ffffff" fill-opacity="0.7"/>
                    <rect x="160" y="60" width="5" height="6" rx="0.5" fill="#ffffff" fill-opacity="0.7"/>
                    <rect x="148" y="72" width="5" height="6" rx="0.5" fill="#ffffff" fill-opacity="0.7"/>
                    <rect x="160" y="72" width="5" height="6" rx="0.5" fill="#ffffff" fill-opacity="0.7"/>

                    <!-- Cây xanh bên đường -->
                    <circle cx="28" cy="120" r="14" fill="#34d399" fill-opacity="0.75"/>
                    <circle cx="40" cy="126" r="11" fill="#10b981" fill-opacity="0.8"/>
                    <circle cx="282" cy="120" r="15" fill="#34d399" fill-opacity="0.75"/>
                    <circle cx="296" cy="125" r="12" fill="#10b981" fill-opacity="0.8"/>

                    <!-- Mặt đường nhựa đổ bóng & vạch sơn trắng dẫn hướng -->
                    <path d="M0 135 L320 135 L320 180 L0 180 Z" fill="#2563eb" fill-opacity="0.45"/>
                    <path d="M0 148 L320 162" stroke="#ffffff" stroke-width="2.5" stroke-dasharray="16 12" stroke-opacity="0.65"/>

                    <!-- ============================================== -->
                    <!-- CHI TIẾT XE BUÝT ĐÔ THỊ HIỆN ĐẠI (CHÍNH GIỮA) -->
                    <!-- ============================================== -->
                    <!-- Bóng xe buýt trên mặt đường -->
                    <ellipse cx="140" cy="162" rx="100" ry="8" fill="#1e3a8a" fill-opacity="0.35"/>

                    <!-- Khung thân xe buýt: Trắng sang trọng phối sọc xanh dương -->
                    <rect x="45" y="90" width="185" height="60" rx="9" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.2"/>
                    <!-- Dải sọc màu xanh đô thị ở thân dưới xe -->
                    <path d="M45 125 H230 V142 C230 147 226 150 221 150 H45 V125 Z" fill="#2563eb"/>

                    <!-- Nóc xe vát bo cong -->
                    <path d="M46 95 Q 130 87 225 93" stroke="#e2e8f0" stroke-width="2" fill="none"/>

                    <!-- Cụm kính cửa sổ trời tối màu chống lóa -->
                    <rect x="52" y="96" width="28" height="24" rx="3" fill="#1e293b"/>
                    <rect x="84" y="96" width="30" height="24" rx="3" fill="#1e293b"/>
                    <rect x="118" y="96" width="30" height="24" rx="3" fill="#1e293b"/>
                    <rect x="152" y="96" width="30" height="24" rx="3" fill="#1e293b"/>
                    <!-- Cửa ra vào hành khách ở giữa -->
                    <rect x="186" y="96" width="14" height="48" rx="2" fill="#0f172a" stroke="#cbd5e1" stroke-width="0.8"/>

                    <!-- Kính chắn gió lớn phía trước đầu xe (Góc nhìn chéo 3D) -->
                    <path d="M204 96 H224 C228 96 230 99 230 103 V125 H204 V96 Z" fill="#0f172a"/>
                    <!-- Biển LED hiển thị tuyến: "BUS 01" -->
                    <rect x="206" y="98" width="20" height="6" rx="1" fill="#0284c7"/>
                    <text x="208" y="103" fill="#ffffff" font-size="4.5" font-family="monospace" font-weight="bold">01 LIVE</text>

                    <!-- Đèn pha LED trước phát sáng -->
                    <circle cx="228" cy="132" r="3" fill="#fef08a"/>
                    <!-- Chùm ánh sáng đèn pha chiếu xuống mặt đường -->
                    <polygon points="230,132 280,122 295,148 230,136" fill="#fef08a" fill-opacity="0.25"/>

                    <!-- Đèn hậu xe buýt màu đỏ -->
                    <rect x="45" y="128" width="3" height="7" rx="1" fill="#ef4444"/>

                    <!-- Bánh xe trước & sau (Viền mâm đúc sắc sảo) -->
                    <g>
                        <circle cx="85" cy="150" r="12" fill="#0f172a" stroke="#64748b" stroke-width="2"/>
                        <circle cx="85" cy="150" r="5" fill="#cbd5e1"/>
                        <circle cx="188" cy="150" r="12" fill="#0f172a" stroke="#64748b" stroke-width="2"/>
                        <circle cx="188" cy="150" r="5" fill="#cbd5e1"/>
                    </g>
                </svg>
            </div>

            <!-- Bottom Features Pill (3 Tiện ích: Nhanh chóng, An toàn, Tiện lợi) -->
            <div class="relative z-10 pt-3 border-t border-white/20 grid grid-cols-3 gap-2 text-center">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center mb-1 text-white">
                        <i class="bi bi-lightning-charge-fill text-sm text-yellow-300"></i>
                    </div>
                    <span class="text-[11px] font-semibold text-blue-50">Nhanh chóng</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center mb-1 text-white">
                        <i class="bi bi-shield-fill-check text-sm text-emerald-300"></i>
                    </div>
                    <span class="text-[11px] font-semibold text-blue-50">An toàn</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center mb-1 text-white">
                        <i class="bi bi-heart-fill text-sm text-rose-300"></i>
                    </div>
                    <span class="text-[11px] font-semibold text-blue-50">Tiện lợi</span>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- 2. RIGHT FORM CARD: BIỂU MẪU ĐĂNG NHẬP                       -->
        <!-- ============================================================= -->
        <div class="p-6 sm:p-8 lg:p-9 flex flex-col justify-between">
            <div>
                <!-- Header Đăng Nhập -->
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-100/80 flex items-center justify-center text-blue-600 text-2xl shadow-sm shrink-0">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Đăng Nhập</h1>
                        <p class="text-xs sm:text-sm text-slate-500 font-medium">Chào mừng bạn quay trở lại!</p>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($auth_warning)): ?>
                    <div class="mt-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-base shrink-0"></i>
                        <span><?php echo htmlspecialchars($auth_warning, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($auth_success)): ?>
                    <div class="mt-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                        <i class="bi bi-check-circle-fill text-emerald-500 text-base shrink-0"></i>
                        <span><?php echo htmlspecialchars($auth_success, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                        <i class="bi bi-x-circle-fill text-rose-500 text-base shrink-0"></i>
                        <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Nhập Liệu -->
                <form action="login.php" method="POST" class="mt-6 space-y-4" novalidate>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Tên đăng nhập -->
                    <div>
                        <label for="username" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1.5">
                            Tên đăng nhập <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" id="username" name="username"
                                   placeholder="Nhập username"
                                   value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="w-full pl-10 pr-4 py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required autofocus>
                        </div>
                    </div>

                    <!-- Mật khẩu -->
                    <div>
                        <label for="password" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1.5">
                            Mật khẩu <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" id="password" name="password"
                                   placeholder="Nhập mật khẩu"
                                   class="w-full pl-10 pr-11 py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required>
                            <button type="button" onclick="togglePassword('password', this)"
                                    class="absolute right-3.5 text-slate-400 hover:text-slate-600 focus:outline-none p-1 transition"
                                    title="Hiện/Ẩn mật khẩu">
                                <i class="bi bi-eye-slash text-base"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tiện ích: Nhớ đăng nhập & Quên mật khẩu -->
                    <div class="flex items-center justify-between text-xs pt-1">
                        <label class="flex items-center gap-2 text-slate-600 cursor-pointer select-none">
                            <input type="checkbox" name="remember" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300">
                            <span>Nhớ đăng nhập</span>
                        </label>
                        <a href="reset_password.php" class="text-blue-600 hover:text-blue-700 font-semibold hover:underline">
                            Quên mật khẩu?
                        </a>
                    </div>

                    <!-- Nút Submit Đăng Nhập -->
                    <div class="pt-2">
                        <button type="submit" 
                                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-sm sm:text-base shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/35 active:scale-[0.99] transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="bi bi-box-arrow-in-right text-lg"></i>
                            <span>Đăng Nhập</span>
                        </button>
                    </div>

                    <!-- Footer Link Đăng ký -->
                    <p class="text-center text-xs text-slate-500 pt-3">
                        Chưa có tài khoản? 
                        <a href="register.php" class="text-blue-600 font-bold hover:underline">Đăng ký ngay</a>
                    </p>
                </form>
            </div>

            <!-- Box Gợi Ý Tài Khoản Thử Nghiệm Nhanh Cho Đồ Án / Kiểm Thử -->
            <div class="mt-6 pt-4 border-t border-slate-100">
                <div class="bg-slate-50/80 rounded-xl p-3 border border-slate-200/70 text-xs text-slate-600">
                    <div class="flex items-center justify-between mb-1.5 font-bold text-slate-700">
                        <span class="flex items-center gap-1.5">
                            <i class="bi bi-key-fill text-amber-500"></i> Tài khoản thử nghiệm:
                        </span>
                        <a href="reset_password.php" class="text-[11px] text-blue-600 hover:underline font-normal">Đặt lại pass mẫu</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="fillDemo('admin', 'admin123')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition font-mono text-[11px] flex items-center gap-1 shadow-2xs">
                            <i class="bi bi-shield-lock text-amber-600"></i> Admin (admin / admin123)
                        </button>
                        <button type="button" onclick="fillDemo('hanhkhach1', 'password123')" class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition font-mono text-[11px] flex items-center gap-1 shadow-2xs">
                            <i class="bi bi-person text-blue-600"></i> Khách (hanhkhach1 / password123)
                        </button>
                    </div>
                </div>

                <!-- Quay lại trang chủ -->
                <div class="text-center mt-3">
                    <a href="../index.php" class="text-xs text-slate-400 hover:text-slate-600 inline-flex items-center gap-1 transition">
                        <i class="bi bi-arrow-left"></i> Quay lại trang chủ tra cứu
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Script ẩn/hiện mật khẩu & tự động điền tài khoản mẫu -->
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }

        function fillDemo(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
