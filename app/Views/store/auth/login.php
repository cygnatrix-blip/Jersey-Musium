<div class="auth">
    <div class="auth__card">
        <h1>Welcome back</h1>
        <p class="lead">Login to track orders, save your wishlist and checkout faster.</p>
        <form method="post" action="/login" novalidate>
            <?= csrf_field() ?>
            <?= partial('store/partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'attrs' => 'autocomplete="email" required autofocus']) ?>
            <?= partial('store/partials/field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="current-password" required']) ?>
            <div class="flex between center mb-2"><span></span><a href="/forgot-password" class="btn btn--ghost">Forgot password?</a></div>
            <button class="btn btn--block btn--lg" type="submit">Login</button>
        </form>
        <p class="auth__alt">New to <?= e(setting('store_name')) ?>? <a href="/register">Create an account</a></p>
    </div>
</div>
