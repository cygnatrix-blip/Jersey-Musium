<nav class="acc-nav" aria-label="Account">
    <a href="/account" class="<?= request()->path() === '/account' ? 'is-active' : '' ?>">Overview</a>
    <a href="/account/orders" class="<?= is_path('/account/orders') ? 'is-active' : '' ?>">Orders &amp; returns</a>
    <a href="/wishlist" class="<?= is_path('/wishlist') ? 'is-active' : '' ?>">Wishlist</a>
    <a href="/account/addresses" class="<?= is_path('/account/addresses') ? 'is-active' : '' ?>">Addresses</a>
    <form method="post" action="/logout"><?= csrf_field() ?><button type="submit"><?= icon('logout', 14) ?> Logout</button></form>
</nav>
