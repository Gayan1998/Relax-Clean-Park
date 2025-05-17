<?php
session_start();
// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ./admin/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Login</title>
    <link rel="icon" href="../assets/images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="../assets/images/logo.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            overflow: hidden;
        }
        
        .bg-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            height: 100vh;
            width: 100vw;
            position: fixed;
            top: 0;
            left: 0;
            z-index: -2;
        }
        
        .bg-shapes {
            position: absolute;
            height: 100%;
            width: 100%;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            background-color: rgba(59, 130, 246, 0.08);
            border-radius: 50%;
        }
        
        .card {
            background-color: #1e293b;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .pop-in {
            animation: pop-in 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transform: translateY(20px);
            opacity: 0;
        }
        
        @keyframes pop-in {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .input-icon {
            color: #64748b;
            transition: color 0.2s;
        }
        
        input:focus + .input-icon {
            color: #3b82f6;
        }
        
        /* Simple glow effect for icons */
        @keyframes glow-float {
            0% { opacity: 0.3; filter: blur(1px); transform: translateY(0); }
            50% { opacity: 0.6; filter: blur(0); transform: translateY(-5px); }
            100% { opacity: 0.3; filter: blur(1px); transform: translateY(0); }
        }
        
        .icon-container {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .pos-icon {
            position: absolute;
            opacity: 0.3;
            animation: glow-float 8s infinite ease-in-out;
            color: #3b82f6;
            filter: blur(1px);
        }
        
        /* Custom form styles for dark theme */
        .dark-input {
            background-color: rgba(15, 23, 42, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
        }
        
        .dark-input:focus {
            background-color: rgba(15, 23, 42, 0.9);
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }
    </style>
</head>
<body>
    <!-- Background elements -->
    <div class="bg-gradient"></div>
    <div class="bg-shapes" id="bg-shapes"></div>
    
    <!-- Animated POS icons in background -->
    <div class="icon-container" id="icon-container"></div>
    
    <!-- Main content -->
    <div class="min-h-screen flex flex-col justify-center items-center p-4">
        <div class="card w-full max-w-md p-8 pop-in" style="animation-delay: 0.1s">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-900 bg-opacity-30 mb-4">
                    <i class="fas fa-cash-register text-2xl text-blue-400"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-100">POS System</h1>
                <p class="text-gray-400 text-sm mt-1">Sign in to your account</p>
            </div>
            
            <form action="./admin/login_process.php" method="POST">
                <div class="space-y-5">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required
                                class="dark-input pl-10 block w-full rounded-lg py-3 text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none border transition duration-200"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                class="dark-input pl-10 block w-full rounded-lg py-3 text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none border transition duration-200"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock input-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-2">
                        <button 
                            type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition duration-200 transform hover:translate-y-[-2px] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                        >
                            Sign In
                        </button>
                    </div>
                </div>
            </form>
            
            <?php
            // Display error message if login fails
            if (isset($_SESSION['login_error'])) {
                echo '<div class="mt-5 bg-red-900 bg-opacity-30 text-red-300 p-3 rounded-lg text-sm flex items-center border border-red-800">';
                echo '<i class="fas fa-exclamation-circle mr-2"></i>';
                echo $_SESSION['login_error'];
                echo '</div>';
                unset($_SESSION['login_error']);
            }
            ?>
            
            <div class="text-center mt-6">
                <a href="#" class="text-sm text-blue-400 hover:text-blue-300 transition duration-200">Forgot password?</a>
            </div>
        </div>
        
        <p class="text-center text-gray-500 text-xs mt-8 pop-in" style="animation-delay: 0.3s">
            © 2025 POS System | <a href="#" class="hover:text-blue-400 transition duration-200">Help</a> | <a href="#" class="hover:text-blue-400 transition duration-200">Privacy</a>
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create background shapes
            createBackgroundShapes();
            
            // Create floating POS icons
            createPosIcons();
        });
        
        function createBackgroundShapes() {
            const container = document.getElementById('bg-shapes');
            const colors = [
                'rgba(59, 130, 246, 0.08)', // blue
                'rgba(16, 185, 129, 0.06)', // green
                'rgba(139, 92, 246, 0.07)'  // purple
            ];
            
            for (let i = 0; i < 6; i++) {
                const shape = document.createElement('div');
                shape.classList.add('shape');
                
                // Random properties
                const size = Math.random() * 400 + 100;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                shape.style.width = `${size}px`;
                shape.style.height = `${size}px`;
                shape.style.left = `${posX}%`;
                shape.style.top = `${posY}%`;
                shape.style.backgroundColor = color;
                shape.style.opacity = Math.random() * 0.5 + 0.1;
                shape.style.transform = `translate(-50%, -50%)`;
                
                container.appendChild(shape);
            }
        }
        
        function createPosIcons() {
            const container = document.getElementById('icon-container');
            const icons = [
                'fa-cash-register',
                'fa-credit-card',
                'fa-receipt',
                'fa-barcode',
                'fa-shopping-cart',
                'fa-calculator'
            ];
            
            // Colors for the icons with good dark mode visibility
            const colors = [
                '#3b82f6', // blue
                '#10b981', // green
                '#8b5cf6', // purple
                '#f59e0b'  // amber
            ];
            
            for (let i = 0; i < 12; i++) {
                const icon = document.createElement('i');
                const iconClass = icons[Math.floor(Math.random() * icons.length)];
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                icon.classList.add('fas', iconClass, 'pos-icon');
                
                // Random properties
                const size = Math.random() * 20 + 20;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const delay = Math.random() * 10;
                const duration = Math.random() * 5 + 5;
                
                icon.style.fontSize = `${size}px`;
                icon.style.left = `${posX}%`;
                icon.style.top = `${posY}%`;
                icon.style.animationDelay = `${delay}s`;
                icon.style.animationDuration = `${duration}s`;
                icon.style.color = color;
                
                container.appendChild(icon);
            }
        }
    </script>
</body>
</html>