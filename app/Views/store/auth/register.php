<div class="auth">
    <div class="auth__card">
        <h1>Join the club</h1>
        <p class="lead">Create your account in seconds.</p>
        <form method="post" action="/register" novalidate>
            <?= csrf_field() ?>
            <?= partial('store/partials/field', ['name' => 'name', 'label' => 'Full name', 'attrs' => 'autocomplete="name" required']) ?>
            <?= partial('store/partials/field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'attrs' => 'autocomplete="email" required']) ?>
            <?= partial('store/partials/field', ['name' => 'phone', 'label' => 'Mobile number', 'type' => 'tel', 'attrs' => 'inputmode="numeric" maxlength="10" autocomplete="tel-national" required']) ?>
            <?= partial('store/partials/field', ['name' => 'password', 'label' => 'Password (min 8, letters + numbers)', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password" minlength="8" required']) ?>
            <?= partial('store/partials/field', ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'value' => '', 'attrs' => 'autocomplete="new-password" required']) ?>
            <button class="btn btn--block btn--lg" type="submit">Create account</button>
            <p class="muted mt-1" style="font-size:12px">By continuing, you agree to our <a href="/page/terms-and-conditions">Terms of Use</a> and <a href="/page/privacy-policy">Privacy Policy</a>.</p>
        </form>
        <p class="auth__alt">Already have an account? <a href="/login">Login</a></p>
    </div>
</div>
