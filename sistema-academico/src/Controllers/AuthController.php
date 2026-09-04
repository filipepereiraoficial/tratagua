<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/login', ['title' => 'Entrar'], 'layouts/auth');
    }

    public function login(): void
    {
        $config = $GLOBALS['__config']['security'];
        $result = Auth::attempt(
            (string) $this->request->input('email', ''),
            (string) $this->request->input('password', ''),
            $this->request->ip(),
            (int) $config['max_login_attempts'],
            (int) $config['lockout_minutes']
        );

        if (!$result['ok']) {
            Flash::error($result['message']);
            Flash::keepInput(['email' => $this->request->input('email')]);
            $this->redirect('/login');
        }

        Flash::success($result['message']);
        $intended = Session::pull('_intended', '/');
        $this->redirect(is_string($intended) ? $intended : '/');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    public function showPassword(): void
    {
        $this->view('auth/password', [
            'title'    => 'Alterar senha',
            'obrigado' => (bool) Auth::user()['must_change_password'],
        ]);
    }

    public function updatePassword(): void
    {
        $user = Auth::user();

        $validator = Validator::make($this->request->post, [
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ], [
            'current_password' => 'senha atual',
            'password'         => 'nova senha',
        ]);

        if (!password_verify((string) $this->request->input('current_password'), $user['password_hash'])) {
            $validator->add('current_password', 'A senha atual está incorreta.');
        }
        if ($this->request->input('password') === $this->request->input('current_password')) {
            $validator->add('password', 'A nova senha deve ser diferente da atual.');
        }

        if ($validator->fails()) {
            $this->rejectWith($validator, '/senha');
        }

        User::updatePassword((int) $user['id'], (string) $this->request->input('password'), false);
        Flash::success('Senha alterada com sucesso.');
        $this->redirect('/');
    }
}
