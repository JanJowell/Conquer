<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Racetech</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        .shape:nth-child(1) { width: 80px; height: 80px; left: 10%; top: 20%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 120px; height: 120px; right: 15%; top: 60%; animation-delay: 2s; }
        .shape:nth-child(3) { width: 60px; height: 60px; left: 70%; bottom: 20%; animation-delay: 4s; }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        .input-group {
            position: relative;
        }
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            transform: translateY(-25px) scale(0.85);
            color: #667eea;
        }
        .input-group label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.3s ease;
            pointer-events: none;
            color: #9ca3af;
            background: white;
            padding: 0 4px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
            margin-top: 4px;
        }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <!-- Main Signup Container -->
    <div class="w-full max-w-md relative z-10">
        <!-- Logo and Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-xl mb-4">
                <i class="fas fa-running text-3xl text-purple-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Racetech</h1>
            <p class="text-purple-100">Event Management System</p>
        </div>

        <!-- Signup Card -->
        <div class="glass-effect rounded-2xl shadow-2xl p-8">
            <!-- Signup Header -->
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Create Account</h2>
                <p class="text-gray-600">Join our community and start managing events</p>
            </div>

            <!-- Session Status -->
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Signup Form -->
            <form method="POST" action="{{ route('register.store') }}" class="space-y-6">
                @csrf

                <!-- Full Name -->
                <div class="input-group">
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        value="{{ old('name') }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all duration-300"
                        placeholder=" "
                        required
                        autofocus
                        autocomplete="name"
                    >
                    <label for="name">Full Name</label>
                </div>

                <!-- Email Address -->
                <div class="input-group">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="{{ old('email') }}"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all duration-300"
                        placeholder=" "
                        required
                        autocomplete="email"
                    >
                    <label for="email">Email Address</label>
                </div>

                <!-- Password -->
                <div class="input-group">
                    <input 
                        type="password" 
                        id="password" 
                        name="password"
                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all duration-300"
                        placeholder=" "
                        required
                        autocomplete="new-password"
                        onkeyup="checkPasswordStrength(this.value)"
                    >
                    <label for="password">Password</label>
                    <button 
                        type="button" 
                        onclick="togglePassword('password')"
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                    >
                        <i class="fas fa-eye" id="password-toggle"></i>
                    </button>
                </div>
                <div class="password-strength" id="password-strength"></div>
                <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters</p>

                <!-- Confirm Password -->
                <div class="input-group">
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation"
                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition-all duration-300"
                        placeholder=" "
                        required
                        autocomplete="new-password"
                        onkeyup="checkPasswordMatch(this.value)"
                    >
                    <label for="password_confirmation">Confirm Password</label>
                    <button 
                        type="button" 
                        onclick="togglePassword('password_confirmation')"
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                    >
                        <i class="fas fa-eye" id="password_confirmation-toggle"></i>
                    </button>
                </div>
                <p class="text-xs text-red-500 mt-1 hidden" id="password-match-error">Passwords do not match</p>

                <!-- Terms and Conditions -->
                <div class="flex items-start">
                    <input type="checkbox" id="terms" name="terms" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500 mt-1" required>
                    <label for="terms" class="ml-2 text-sm text-gray-700">
                        I agree to the <a href="#" class="text-purple-600 hover:text-purple-700">Terms and Conditions</a> and <a href="#" class="text-purple-600 hover:text-purple-700">Privacy Policy</a>
                    </label>
                </div>

                <!-- Signup Button -->
                <button 
                    type="submit" 
                    class="btn-primary w-full py-3 px-4 text-white font-semibold rounded-lg shadow-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                    data-test="register-user-button"
                >
                    <i class="fas fa-user-plus mr-2"></i>
                    Create Account
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-6 text-center">
                <p class="text-gray-600">
                    Already have an account? 
                    <a href="{{ route('login') }}" class="font-semibold text-purple-600 hover:text-purple-700 transition-colors">
                        Sign in here
                    </a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-purple-100 text-sm">
                © 2026 Racetech. All rights reserved.
            </p>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(inputId + '-toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength';
            
            if (password.length === 0) {
                strengthBar.style.display = 'none';
            } else {
                strengthBar.style.display = 'block';
                if (strength <= 1) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength === 2) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            }
        }

        function checkPasswordMatch(confirmPassword) {
            const password = document.getElementById('password').value;
            const matchError = document.getElementById('password-match-error');
            
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                matchError.classList.remove('hidden');
                document.getElementById('password_confirmation').classList.add('border-red-500');
            } else {
                matchError.classList.add('hidden');
                document.getElementById('password_confirmation').classList.remove('border-red-500');
            }
        }

        // Add form validation feedback
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const passwordConfirmation = document.getElementById('password_confirmation');
            const terms = document.getElementById('terms');
            
            // Remove existing error states
            [name, email, password, passwordConfirmation].forEach(input => {
                input.classList.remove('border-red-500');
            });
            
            let hasError = false;
            
            // Basic validation
            if (!name.value || name.value.length < 2) {
                name.classList.add('border-red-500');
                hasError = true;
            }
            
            if (!email.value || !email.validity.valid) {
                email.classList.add('border-red-500');
                hasError = true;
            }
            
            if (!password.value || password.value.length < 8) {
                password.classList.add('border-red-500');
                hasError = true;
            }
            
            if (!passwordConfirmation.value || password.value !== passwordConfirmation.value) {
                passwordConfirmation.classList.add('border-red-500');
                hasError = true;
            }
            
            if (!terms.checked) {
                terms.classList.add('border-red-500');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
