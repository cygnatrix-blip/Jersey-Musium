<div class="auth">
    <div class="auth__card">
        <h1>Forgot password</h1>
        <p class="lead">Enter your email and we'll send you a link to reset your password.</p>
        <form method="post" action="/forgot-password" novalidate>
            <?= csrf_field() ?>
            <?= partial('store/partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'attrs' => 'autocomplete="email" required autofocus']) ?>
            <button class="btn btn--block btn--lg" type="submit">Send reset link</button>
        </form>
        <p class="auth__alt"><a href="/login">Back to login</a></p>
    </div>
</div>
