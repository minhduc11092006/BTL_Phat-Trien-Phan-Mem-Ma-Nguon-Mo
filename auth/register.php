<?php
// register.php - Đăng ký tài khoản hành khách
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Nếu người dùng đã đăng nhập rồi thì chuyển hướng về trang chủ
if (is_logged_in()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
$fullname = '';
$username = '';
$email    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname         = trim($_POST['fullname'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Kiểm tra các trường không được để trống
    if ($fullname === '') {
        $errors['fullname'] = 'Vui lòng nhập họ và tên.';
    }

    if ($username === '') {
        $errors['username'] = 'Vui lòng nhập tên đăng nhập.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'Tên đăng nhập từ 3-30 ký tự, chỉ gồm chữ, số và dấu gạch dưới (_).';
    }

    if ($email === '') {
        $errors['email'] = 'Vui lòng nhập địa chỉ email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Địa chỉ email không hợp lệ.';
    }

    if ($password === '') {
        $errors['password'] = 'Vui lòng nhập mật khẩu.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có độ dài tối thiểu từ 6 ký tự.';
    }

    // 2. Kiểm tra Mật khẩu và Nhập lại mật khẩu phải khớp nhau
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Mật khẩu xác nhận không khớp.';
    }

    // 3. Kiểm tra Tên đăng nhập hoặc Email đã tồn tại trong CSDL chưa
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            $existing_user = $stmt->fetch();

            if ($existing_user) {
                if (strtolower($existing_user['username']) === strtolower($username)) {
                    $errors['username'] = 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.';
                }
                if (strtolower($existing_user['email']) === strtolower($email)) {
                    $errors['email'] = 'Địa chỉ email này đã được đăng ký tài khoản.';
                }
            }
        } catch (PDOException $e) {
            $errors['system'] = 'Lỗi truy vấn cơ sở dữ liệu: ' . $e->getMessage();
        }
    }

    // 4. Băm mật khẩu và lưu vào CSDL nếu không có lỗi
    if (empty($errors)) {
        // Bắt buộc MÃ HÓA mật khẩu bằng password_hash (Bcrypt)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user'; // Mặc định tài khoản mới luôn là 'user'

        try {
            $insert_stmt = $pdo->prepare('INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, ?)');
            $insert_stmt->execute([$username, $hashed_password, $fullname, $email, $role]);

            // Thiết lập thông báo thành công vào Session và chuyển sang login.php
            $_SESSION['auth_success'] = 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.';
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            $errors['system'] = 'Không thể đăng ký tài khoản: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký Tài Khoản - Hệ Thống Đặt Vé Công Cộng</title>
    
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
    <div class="w-full max-w-4xl bg-white rounded-[28px] shadow-2xl shadow-blue-900/10 border border-slate-200/80 overflow-hidden grid grid-cols-1 md:grid-cols-2 p-3 sm:p-4 gap-4 my-auto">
        
        <!-- ============================================================= -->
        <!-- 1. LEFT BANNER CARD: HỆ THỐNG ĐẶT VÉ CÔNG CỘNG               -->
        <!-- ============================================================= -->
        <div class="relative rounded-[22px] bg-gradient-to-b from-[#4285f4] via-[#3b82f6] to-[#2563eb] text-white p-6 sm:p-8 flex flex-col justify-between overflow-hidden shadow-lg shadow-blue-500/20 min-h-[520px] md:min-h-[640px]">
            
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
                    Đăng ký tài khoản để trải nghiệm<br>đầy đủ tính năng
                </p>
            </div>

            <!-- Center Illustration: Xe Bus, Nhà Chờ & Điểm Dừng (SVG Vector sắc nét) -->
            <div class="relative z-10 my-auto py-4 flex items-center justify-center">
                <svg class="w-full max-w-[290px] h-auto drop-shadow-md" viewBox="0 0 320 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Bầu trời & Mây trắng -->
                    <path d="M30 45 C30 38, 42 38, 46 42 C50 36, 65 36, 68 42 C74 42, 78 46, 78 50 H30 Z" fill="#ffffff" fill-opacity="0.35"/>
                    <path d="M180 32 C180 26, 190 26, 194 30 C198 24, 212 24, 215 30 C220 30, 224 34, 224 38 H180 Z" fill="#ffffff" fill-opacity="0.4"/>

                    <!-- Đường chân trời thành phố (Skyline) -->
                    <rect x="25" y="60" width="18" height="70" rx="2" fill="#2563eb" fill-opacity="0.35"/>
                    <rect x="47" y="45" width="22" height="85" rx="2" fill="#1d4ed8" fill-opacity="0.45"/>
                    <polygon points="58,35 47,45 69,45" fill="#1d4ed8" fill-opacity="0.45"/>
                    <rect x="73" y="68" width="16" height="62" rx="2" fill="#2563eb" fill-opacity="0.3"/>
                    <rect x="93" y="52" width="24" height="78" rx="2" fill="#1e40af" fill-opacity="0.4"/>
                    <rect x="121" y="72" width="15" height="58" rx="2" fill="#2563eb" fill-opacity="0.25"/>
                    <rect x="140" y="58" width="20" height="72" rx="2" fill="#1d4ed8" fill-opacity="0.35"/>
                    <rect x="164" y="66" width="18" height="64" rx="2" fill="#2563eb" fill-opacity="0.3"/>

                    <!-- Cây xanh / Công viên bên đường -->
                    <circle cx="195" cy="118" r="14" fill="#34d399" fill-opacity="0.5"/>
                    <circle cx="210" cy="115" r="18" fill="#10b981" fill-opacity="0.6"/>
                    <circle cx="225" cy="120" r="12" fill="#059669" fill-opacity="0.5"/>

                    <!-- ============================================== -->
                    <!-- NHÀ CHỜ XE BUS & ĐIỂM DỪNG (BUS STOP SHELTER) -->
                    <!-- ============================================== -->
                    <g id="bus-shelter">
                        <!-- Mái vòm nhà chờ -->
                        <path d="M232 94 C245 88, 285 88, 298 94" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
                        <!-- Kính nền phía sau nhà chờ -->
                        <rect x="238" y="96" width="56" height="38" rx="3" fill="#ffffff" fill-opacity="0.25" stroke="#ffffff" stroke-width="1.2"/>
                        <line x1="266" y1="96" x2="266" y2="134" stroke="#ffffff" stroke-width="1" stroke-opacity="0.4"/>
                        <!-- Ghế ngồi chờ xe bus -->
                        <rect x="242" y="118" width="48" height="3.5" rx="1.5" fill="#ffffff" fill-opacity="0.8"/>
                        <line x1="250" y1="121.5" x2="250" y2="134" stroke="#ffffff" stroke-width="2"/>
                        <line x1="282" y1="121.5" x2="282" y2="134" stroke="#ffffff" stroke-width="2"/>
                        <!-- Cột đỡ hai bên nhà chờ -->
                        <line x1="238" y1="94" x2="238" y2="134" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                        <line x1="294" y1="94" x2="294" y2="134" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>

                        <!-- BIỂU TƯỢNG ĐIỂM DỪNG (LOCATION PIN) NỔI BẬT TRÊN NHÀ CHỜ -->
                        <g transform="translate(266, 74)">
                            <!-- Giọt nước Location Pin trắng -->
                            <path d="M0 0 C-6 -6, -9 -10, -9 -15 A9 9 0 1 1 9 -15 C9 -10, 6 -6, 0 0 Z" fill="#ffffff" filter="drop-shadow(0px 2px 3px rgba(0,0,0,0.2))"/>
                            <!-- Chấm tròn xanh bên trong -->
                            <circle cx="0" cy="-15" r="4.2" fill="#2563eb"/>
                        </g>
                    </g>

                    <!-- Lòng đường & Vỉa hè -->
                    <rect x="0" y="132" width="320" height="68" fill="#1e3a8a" fill-opacity="0.45"/>
                    <!-- Vạch phân làn đường -->
                    <line x1="0" y1="168" x2="320" y2="168" stroke="#ffffff" stroke-width="2.5" stroke-dasharray="14 10" stroke-opacity="0.8"/>
                    <line x1="0" y1="134" x2="320" y2="134" stroke="#93c5fd" stroke-width="2" stroke-opacity="0.6"/>

                    <!-- XE BUS ĐANG CHẠY TRÊN ĐƯỜNG -->
                    <g id="bus-vehicle">
                        <!-- Thân xe bus chính -->
                        <rect x="12" y="96" width="176" height="58" rx="10" fill="#ffffff"/>
                        <!-- Dải viền xanh thể thao đặc trưng hệ thống bus -->
                        <rect x="12" y="134" width="176" height="12" rx="2" fill="#2563eb"/>
                        
                        <!-- Cửa sổ kính xe bus -->
                        <rect x="22" y="103" width="22" height="24" rx="3" fill="#0284c7" fill-opacity="0.85"/>
                        <rect x="48" y="103" width="22" height="24" rx="3" fill="#0284c7" fill-opacity="0.85"/>
                        <rect x="74" y="103" width="22" height="24" rx="3" fill="#0284c7" fill-opacity="0.85"/>
                        <rect x="100" y="103" width="22" height="24" rx="3" fill="#0284c7" fill-opacity="0.85"/>
                        <rect x="126" y="103" width="22" height="24" rx="3" fill="#0284c7" fill-opacity="0.85"/>
                        <!-- Kính chắn gió trước lớn hơn -->
                        <path d="M152 103 H176 C181 103, 183 107, 183 114 V127 H152 V103 Z" fill="#0369a1" fill-opacity="0.9"/>
                        
                        <!-- Đèn trước & lưới tản nhiệt -->
                        <rect x="180" y="138" width="6" height="5" rx="1.5" fill="#fde047"/>
                        <rect x="150" y="140" width="24" height="3" rx="1.5" fill="#94a3b8"/>

                        <!-- Bánh xe trước & sau -->
                        <!-- Bánh trước -->
                        <circle cx="154" cy="154" r="14" fill="#0f172a"/>
                        <circle cx="154" cy="154" r="7" fill="#cbd5e1"/>
                        <circle cx="154" cy="154" r="3" fill="#0f172a"/>
                        <!-- Bánh sau -->
                        <circle cx="44" cy="154" r="14" fill="#0f172a"/>
                        <circle cx="44" cy="154" r="7" fill="#cbd5e1"/>
                        <circle cx="44" cy="154" r="3" fill="#0f172a"/>
                    </g>
                </svg>
            </div>

            <!-- Bottom 3 Feature Badges -->
            <div class="relative z-10 grid grid-cols-3 gap-2 pt-2 border-t border-white/20 text-center text-white">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center mb-1 text-sm shadow-sm backdrop-blur-xs">
                        <i class="bi bi-lightning-charge-fill"></i>
                    </div>
                    <span class="text-[11px] sm:text-xs font-semibold text-white/95">Nhanh chóng</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center mb-1 text-sm shadow-sm backdrop-blur-xs">
                        <i class="bi bi-shield-fill-check"></i>
                    </div>
                    <span class="text-[11px] sm:text-xs font-semibold text-white/95">An toàn</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center mb-1 text-sm shadow-sm backdrop-blur-xs">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <span class="text-[11px] sm:text-xs font-semibold text-white/95">Tiện lợi</span>
                </div>
            </div>

        </div>

        <!-- ============================================================= -->
        <!-- 2. RIGHT FORM CARD: FORM ĐĂNG KÝ TÀI KHOẢN                  -->
        <!-- ============================================================= -->
        <div class="p-4 sm:p-6 lg:p-7 flex flex-col justify-between">
            
            <div>
                <!-- Form Header: Icon + Title + Subtitle -->
                <div class="flex items-center gap-3.5 mb-1">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center text-2xl shadow-inner shrink-0">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl sm:text-[26px] font-extrabold text-slate-900 tracking-tight">
                            Đăng Ký Tài Khoản
                        </h2>
                    </div>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 font-medium ml-[62px] -mt-1 mb-4">
                    Tạo tài khoản mới chỉ trong vài phút
                </p>

                <!-- Alert Message Hệ thống nếu có lỗi -->
                <?php if (isset($errors['system'])): ?>
                    <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                        <i class="bi bi-exclamation-octagon-fill text-rose-500 text-base shrink-0"></i>
                        <span><?php echo htmlspecialchars($errors['system'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Nhập Liệu 5 Trường Theo Thiết Kế Chuẩn -->
                <form action="register.php" method="POST" class="space-y-3.5" novalidate>
                    
                    <!-- 1. Họ và tên -->
                    <div>
                        <label for="fullname" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">
                            Họ và tên <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" id="fullname" name="fullname"
                                   placeholder="Nhập họ và tên"
                                   value="<?php echo htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="w-full pl-10 pr-4 py-2.5 sm:py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border <?php echo isset($errors['fullname']) ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'; ?> text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required autofocus>
                        </div>
                        <?php if (isset($errors['fullname'])): ?>
                            <p class="text-rose-500 text-[11px] mt-1 flex items-center gap-1 font-medium">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errors['fullname'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Tên đăng nhập -->
                    <div>
                        <label for="username" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">
                            Tên đăng nhập <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" id="username" name="username"
                                   placeholder="Ví dụ: nguyenvana123"
                                   value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="w-full pl-10 pr-4 py-2.5 sm:py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border <?php echo isset($errors['username']) ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'; ?> text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required>
                        </div>
                        <?php if (isset($errors['username'])): ?>
                            <p class="text-rose-500 text-[11px] mt-1 flex items-center gap-1 font-medium">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Địa chỉ Email -->
                    <div>
                        <label for="email" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">
                            Địa chỉ Email <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" id="email" name="email"
                                   placeholder="name@example.com"
                                   value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="w-full pl-10 pr-4 py-2.5 sm:py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border <?php echo isset($errors['email']) ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'; ?> text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required>
                        </div>
                        <?php if (isset($errors['email'])): ?>
                            <p class="text-rose-500 text-[11px] mt-1 flex items-center gap-1 font-medium">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Mật khẩu -->
                    <div>
                        <label for="password" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">
                            Mật khẩu (Tối thiểu 6 ký tự) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" id="password" name="password"
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-11 py-2.5 sm:py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border <?php echo isset($errors['password']) ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'; ?> text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required>
                            <button type="button" onclick="togglePassword('password', this)"
                                    class="absolute right-3.5 text-slate-400 hover:text-slate-600 focus:outline-none p-1 transition"
                                    title="Hiện/Ẩn mật khẩu">
                                <i class="bi bi-eye-slash text-base"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <p class="text-rose-500 text-[11px] mt-1 flex items-center gap-1 font-medium">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- 5. Nhập lại mật khẩu -->
                    <div>
                        <label for="confirm_password" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1">
                            Nhập lại mật khẩu <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-slate-400 text-base pointer-events-none">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-11 py-2.5 sm:py-3 bg-slate-50/60 hover:bg-slate-50 focus:bg-white rounded-xl border <?php echo isset($errors['confirm_password']) ? 'border-rose-400 bg-rose-50/20 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-slate-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'; ?> text-slate-800 text-sm placeholder-slate-400 transition-all duration-200"
                                   required>
                            <button type="button" onclick="togglePassword('confirm_password', this)"
                                    class="absolute right-3.5 text-slate-400 hover:text-slate-600 focus:outline-none p-1 transition"
                                    title="Hiện/Ẩn mật khẩu">
                                <i class="bi bi-eye-slash text-base"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <p class="text-rose-500 text-[11px] mt-1 flex items-center gap-1 font-medium">
                                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Nút Submit Đăng Ký Tài Khoản -->
                    <div class="pt-2">
                        <button type="submit" 
                                class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-sm sm:text-base shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/35 active:scale-[0.99] transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="bi bi-person-plus text-lg"></i>
                            <span>Đăng Ký Tài Khoản</span>
                        </button>
                    </div>

                    <!-- Footer Link Đăng nhập -->
                    <p class="text-center text-xs text-slate-500 pt-2">
                        Đã có tài khoản? 
                        <a href="login.php" class="text-blue-600 font-bold hover:underline">Đăng nhập ngay</a>
                    </p>
                </form>
            </div>

            <!-- Quay lại trang chủ tra cứu -->
            <div class="mt-4 pt-3 border-t border-slate-100 text-center">
                <a href="../index.php" class="text-xs text-slate-400 hover:text-slate-600 inline-flex items-center gap-1 transition">
                    <i class="bi bi-arrow-left"></i> Quay lại trang chủ tra cứu
                </a>
            </div>
        </div>

    </div>

    <!-- Script ẩn/hiện mật khẩu cho cả 2 ô mật khẩu -->
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
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
    </script>
</body>
</html>
