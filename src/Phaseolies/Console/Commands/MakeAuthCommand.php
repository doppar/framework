<?php

namespace Phaseolies\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeAuthCommand extends Command
{
    protected static $defaultName = 'make:auth';

    protected function configure()
    {
        $this
            ->setName('make:auth')
            ->setDescription('Scaffolds authentication system (controllers, views, and routes).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Define paths
        $basePath = base_path();
        $controllersPath = "{$basePath}/app/Http/Controllers/Auth/";
        $homeControllersPath = "{$basePath}/app/Http/Controllers/";
        $viewsPath = "{$basePath}/resources/views/auth/";
        $layoutsPath = "{$basePath}/resources/views/layouts/";
        $homePath = "{$basePath}/resources/views/";
        $routesPath = "{$basePath}/routes/web.php";

        // Create necessary directories
        foreach ([$controllersPath, $viewsPath, $layoutsPath, $homePath] as $path) {
            if (!is_dir($path)) mkdir($path, 0755, true);
        }
        @unlink(base_path('resources/views/welcome.blade.php'));
        // Generate controllers
        $this->createFile($controllersPath . "LoginController.php", $this->getLoginController());
        $this->createFile($controllersPath . "RegisterController.php", $this->getRegisterController());
        $this->createFile($homeControllersPath . "HomeController.php", $this->getHomeController());

        // Generate views
        $this->createFile($viewsPath . "login.blade.php", $this->getLoginView());
        $this->createFile($viewsPath . "register.blade.php", $this->getRegisterView());
        $this->createFile($layoutsPath . "app.blade.php", $this->getAppLayout());
        $this->createFile($homePath . "home.blade.php", $this->getHomeView());
        $this->createFile($homePath . "welcome.blade.php", $this->getWelcomeView());

        // Append routes
        $this->appendRoutes($routesPath);

        $output->writeln('<info>Authentication scaffolding generated successfully.</info>');
        return Command::SUCCESS;
    }

    private function createFile(string $path, string $content)
    {
        if (!file_exists($path)) {
            file_put_contents($path, $content);
        }
    }

    private function appendRoutes(string $routesPath)
    {
        $authRoutes = <<<EOT

// Auth Routes
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

Route::get('home', [HomeController::class, 'home'])->name('dashboard')->middleware('auth');
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
Route::group(['middleware' => ['guest']], function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});

EOT;

        if (file_exists($routesPath)) {
            file_put_contents($routesPath, $authRoutes, FILE_APPEND);
        }
    }

    private function getAppLayout(): string
    {
        return <<<EOT
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>@yield('title')</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background: #f8f9fa;
            }

            .navbar {
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top">
            <div class="container">
                <a class="navbar-brand" href="{{ route('home') }}">
                    <img src="{{ enqueue('logo.png') }}" alt="Logo" width="60"
                        height="60">
                </a>
                <div class="d-flex align-items-center gap-3">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-light-custom">Login</a>
                        <a href="{{ route('register') }}" class="btn btn-light-custom">Register</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn btn-light-custom">Dashboard</a>
                        <div class="dropdown">
                            <button class="btn btn-light-custom dropdown-toggle" type="button" id="dropdownMenuButton"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                {{ Phaseolies\Support\Facades\Auth::user()->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item">{{ __('Logout') }}</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endguest
                </div>
            </div>
        </nav>
        <div class="container mt-5 pt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    @if (session()->has('success'))
                        <div class="alert alert-success">
                            {{ session()->get('success') }}
                        </div>
                    @elseif(session()->has('error'))
                        <div class="alert alert-danger">
                            {{ session()->get('error') }}
                        </div>
                    @endif
                    @yield('content')
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
        </script>
    </body>
</html>
EOT;
    }

    private function getWelcomeView(): string
    {
        return <<<EOT
@extends('layouts.app')
@section('title') Welcome
@section('content')
    <div class="container d-flex justify-content-center align-items-center" style="height: 65vh;">
        <div class="content text-center">
            <p style="font-weight: 700; font-size:20px">{{ trans('messages.welcome', ['version' => \Phaseolies\Application::VERSION]) }}</p>
            <div class="buttons">
                <a href="https://github.com/techmahedy/doppar" class="btn btn-light" style="background: #D3D4D5">Github</a>
                <a href="https://github.com/techmahedy/doppar" class="btn btn-light"
                    style="background: #D3D4D5">Documentation</a>
            </div>
        </div>
    </div>
@endsection
EOT;
    }

    private function getHomeView(): string
    {
        return <<<EOT
@extends('layouts.app')
@section('title') Dashboard
@section('content')
    <div class="card shadow-lg mb-4">
        <div class="card-header bg-white text-black fw-bold">
            <h5 class="fw-bold fs-5">{{ __('Dashboard') }}</h5>
        </div>
        <div class="card-body">
            <p class="fw-bold fs-5">
                You are logged in as
                <span class="text-success fw-bold">{{ Phaseolies\Support\Facades\Auth::user()->email }}</span>
            </p>
        </div>
    </div>
@endsection
EOT;
    }

    private function getLoginController(): string
    {
        return <<<EOT
<?php

namespace App\Http\Controllers\Auth;

use Phaseolies\Http\Request;
use Phaseolies\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Controller;
use Phaseolies\Http\Response\RedirectResponse;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function login(Request \$request): RedirectResponse
    {
        \$request->sanitize([
            'email' => 'required|email|min:2|max:100',
            'password' => 'required|min:2|max:20'
        ]);

        \$user = User::query()->where('email', '=', \$request->email)->first();

        if (\$user) {
            if (Auth::try(\$request->passed())) {
                return redirect('/home')->with('success', 'You are logged in');
            }
            return back()->with('error', 'Email or password is incorrect');
        }

        return back()->with('error', 'User does not exist');
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->to('/login')
            ->with('success', 'You are successfully logged out');
    }
}
EOT;
    }

    private function getRegisterController(): string
    {
        return <<<EOT
<?php

namespace App\Http\Controllers\Auth;

use Phaseolies\Http\Request;
use Phaseolies\Support\Facades\Hash;
use Phaseolies\Http\Response\RedirectResponse;
use App\Models\User;
use App\Http\Controllers\Controller;

class RegisterController extends Controller
{
    public function index()
    {
        return view('auth.register');
    }

    public function register(Request \$request): RedirectResponse
    {
        \$request->sanitize([
            'name' => 'required|min:2|max:20',
            'email' => 'required|email|unique:users|min:2|max:100',
            'password' => 'required|min:2|max:20',
            'confirm_password' => 'required|same_as:password',
        ]);

        \$user = User::create([
            'name' => \$request->name,
            'email' => \$request->email,
            'password' => Hash::make(\$request->password)
        ]);

        if (\$user) {
            return redirect()->to('/login')
                ->with('success', 'User created successfully');
        }

        return redirect()->back();
    }
}
EOT;
    }

    private function getHomeController()
    {
        return <<<EOT
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function home()
    {
       return view('home');
    }
}
EOT;
    }

    private function getLoginView(): string
    {
        return <<<EOT
@extends('layouts.app')
@section('title') Login
@section('content')
    <div class="card-header bg-white border-bottom-0 text-center mt-5">
        <h5 class="fw-bold fs-5">Login</h5>
    </div>
    <div class="card mx-auto mt-5" style="max-width: 400px;">
        <div class="card-body">
            <form action="{{ route('login') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="email">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" name="password">
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember Me</label>
                </div>
                <button type="submit" class="btn btn-secondary w-100">{{ __('Sign in') }}</button>
            </form>
            <div class="text-center mt-3">
                Don't have an account? <a href="{{ route('register') }}" class="text-decoration-none">{{ __('Register') }}</a>
            </div>
        </div>
    </div>
@endsection
EOT;
    }

    private function getRegisterView(): string
    {
        return <<<EOT
@extends('layouts.app')
@section('title') Register
@section('content')
    <div class="card-header bg-white border-bottom-0 text-center mt-5">
        <h5 class="fw-bold fs-5">Register</h5>
    </div>
    <div class="card mx-auto mt-5" style="max-width: 400px;">
        <div class="card-body">
            <form action="{{ route('register') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" class="form-control" name="name">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="email">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" name="password">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password">
                </div>
                <button type="submit" class="btn btn-secondary w-100">{{ __('Register') }}</button>
            </form>
            <div class="text-center mt-3">
                Already have an account? <a href="{{ route('login') }}" class="text-decoration-none">{{ __('Sign in') }}</a>
            </div>
        </div>
    </div>
@endsection
EOT;
    }
}
