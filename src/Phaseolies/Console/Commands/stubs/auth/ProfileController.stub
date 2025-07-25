<?php

namespace App\Http\Controllers;

use Phaseolies\Support\Facades\Hash;
use Phaseolies\Support\Facades\Auth;
use Phaseolies\Utilities\Attributes\Middleware;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use App\Http\Middleware\Authenticate;
use App\Http\Controllers\Controller;

#[Middleware([Authenticate::class])]
class ProfileController extends Controller
{
    /**
     * Display the profile page view
     *
     * @return Response
     */
    public function index(): Response
    {
        return view('profile');
    }

    /**
     * Update the user profile
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $request->sanitize([
            'name' => 'required|min:2|max:30',
            'email' => 'required|email|min:5|max:50'
        ]);

        $data = $request
            ->pipeInputs([
                'email' => fn($v) => strtolower(trim($v))
            ])
            ->only('name', 'email');

        Auth::user()->update($data);

        return back()->withSuccess('Profile updated successfully');
    }

    /**
     * Update the user password
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->sanitize([
            'old_password' => 'required|min:2|max:20',
            'new_password' => 'required|min:2|max:20',
            'confirm_password' => 'required|same_as:new_password',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->old_password, $user->password)) {
            return back()->withError('Previous password not matched.');
        }

        if (Hash::check($request->new_password, $user->password)) {
            return back()->withError('New password cannot be the previous password.');
        }

        $password = $request->pipe('new_password', fn($v) => bcrypt(trim($v)));

        Auth::user()->update([
            'password' => $password
        ]);

        return back()->withSuccess('Password updated successfully.');
    }
}
