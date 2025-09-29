<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap');
        * { 
            font-family: 'Vazirmatn', sans-serif; 
        }
        
        /* Smooth transitions */
        .sidebar-transition {
            transition: all 0.3s ease-in-out;
        }
        
        /* Hide scrollbar for sidebar */
        .sidebar-scrollbar::-webkit-scrollbar {
            display: none;
        }
        
        .sidebar-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        /* Active link styles */
        .nav-link.active {
            background: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <aside class="w-64 bg-gradient-to-b from-blue-900 to-blue-800 text-white min-h-screen flex flex-col fixed sidebar-transition shadow-2xl"
           id="sidebar" 
           aria-label="منوی اصلی">
        
        <!-- Header with Logo and Date -->
        <div class="p-6 border-b border-blue-700">
            <div class="flex items-center justify-between mb-6">
                <!-- Logo -->
                <div class="flex items-center space-x-3 space-x-reverse">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <i class="fas fa-gem text-blue-600 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">تولیدی الماس</h1>
                        <p class="text-blue-200 text-xs">سیستم مدیریت</p>
                    </div>
                </div>
                
                <!-- Mobile Toggle Button (Hidden on desktop) -->
                <button id="sidebarToggle" class="lg:hidden text-white hover:text-blue-200 transition">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
            
            <!-- Date Display -->
            <div class="bg-blue-800/50 rounded-lg p-3 text-center border border-blue-700">
                <div class="text-blue-200 text-sm mb-1">
                    <i class="fas fa-calendar ml-1"></i>
                    امروز
                </div>
                <div id="persian-date" class="text-white font-bold text-lg" aria-live="polite">
                    <!-- Date will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto sidebar-scrollbar py-4">
            <ul class="space-y-2 px-4">
                <li class="nav-item">
                    <a href="factor-products.php" 
                       class="nav-link flex items-center p-3 rounded-xl hover:bg-blue-700/50 transition-all duration-300 group relative sidebar-transition
                              <?= basename($_SERVER['PHP_SELF']) == 'factor-products.php' ? 'active bg-blue-700' : '' ?>">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-500 transition sidebar-transition">
                            <i class="fas fa-shopping-cart text-white text-lg" aria-hidden="true"></i>
                        </div>
                        <span class="nav-text mr-3 font-medium text-white">مدیریت خریدها</span>
                        <div class="absolute left-3 opacity-0 group-hover:opacity-100 transition sidebar-transition">
                            <i class="fas fa-chevron-left text-blue-200 text-sm"></i>
                        </div>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="payments.php" 
                       class="nav-link flex items-center p-3 rounded-xl hover:bg-blue-700/50 transition-all duration-300 group relative sidebar-transition
                              <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active bg-blue-700' : '' ?>">
                        <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center group-hover:bg-green-500 transition sidebar-transition">
                            <i class="fas fa-credit-card text-white text-lg" aria-hidden="true"></i>
                        </div>
                        <span class="nav-text mr-3 font-medium text-white">مدیریت پرداختی‌ها</span>
                        <div class="absolute left-3 opacity-0 group-hover:opacity-100 transition sidebar-transition">
                            <i class="fas fa-chevron-left text-blue-200 text-sm"></i>
                        </div>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="reports-update.php" 
                       class="nav-link flex items-center p-3 rounded-xl hover:bg-blue-700/50 transition-all duration-300 group relative sidebar-transition
                              <?= basename($_SERVER['PHP_SELF']) == 'reports-update.php' ? 'active bg-blue-700' : '' ?>">
                        <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center group-hover:bg-purple-500 transition sidebar-transition">
                            <i class="fas fa-chart-bar text-white text-lg" aria-hidden="true"></i>
                        </div>
                        <span class="nav-text mr-3 font-medium text-white">گزارش‌ها و آمار</span>
                        <div class="absolute left-3 opacity-0 group-hover:opacity-100 transition sidebar-transition">
                            <i class="fas fa-chevron-left text-blue-200 text-sm"></i>
                        </div>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="customers.php" 
                       class="nav-link flex items-center p-3 rounded-xl hover:bg-blue-700/50 transition-all duration-300 group relative sidebar-transition
                              <?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active bg-blue-700' : '' ?>">
                        <div class="w-10 h-10 bg-orange-600 rounded-lg flex items-center justify-center group-hover:bg-orange-500 transition sidebar-transition">
                            <i class="fas fa-users text-white text-lg" aria-hidden="true"></i>
                        </div>
                        <span class="nav-text mr-3 font-medium text-white">مدیریت مشتریان</span>
                        <div class="absolute left-3 opacity-0 group-hover:opacity-100 transition sidebar-transition">
                            <i class="fas fa-chevron-left text-blue-200 text-sm"></i>
                        </div>
                    </a>
                </li>
            </ul>
            
            
        </nav>

        <!-- Support Section -->
        <div class="p-4 border-t border-blue-700">
            <a href="support.php" 
               class="support-link flex items-center justify-center p-3 bg-blue-800/50 rounded-xl hover:bg-blue-700 transition-all duration-300 group border border-blue-700">
                <div class="w-8 h-8 bg-yellow-600 rounded-lg flex items-center justify-center group-hover:bg-yellow-500 transition sidebar-transition">
                    <i class="fas fa-headset text-white" aria-hidden="true"></i>
                </div>
                <span class="support-text mr-3 font-medium text-white">پشتیبانی و راهنمایی</span>
            </a>
            
            <!-- User Profile -->
            <div class="flex items-center mt-4 p-3 rounded-xl bg-blue-800/30 border border-blue-700">
                <div class="w-10 h-10 bg-gradient-to-r from-cyan-500 to-blue-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="mr-3 flex-1">
                    <div class="text-white font-medium text-sm">مدیر سیستم</div>
                    <div class="text-blue-300 text-xs">سطح دسترسی: کامل</div>
                </div>
                <button class="text-blue-300 hover:text-white transition">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

    <script>
        // Persian Date Display
        function updatePersianDate() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                weekday: 'long'
            };
            
            const persianDate = new Intl.DateTimeFormat('fa-IR', options).format(now);
            document.getElementById('persian-date').textContent = persianDate;
        }

        // Mobile Sidebar Toggle
        function initSidebarToggle() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('mobileOverlay');
            
            if (toggleBtn && overlay) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('-translate-x-full');
                    overlay.classList.toggle('hidden');
                });
                
                overlay.addEventListener('click', () => {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                });
            }
            
            // Close sidebar on link click (mobile)
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        sidebar.classList.add('-translate-x-full');
                        overlay.classList.add('hidden');
                    }
                });
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updatePersianDate();
            initSidebarToggle();
            
            // Update date every minute
            setInterval(updatePersianDate, 60000);
            
            // Add active state based on current page
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.add('active');
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.add('hidden');
            }
        });
    </script>
</body>
</html>