<?php
/** @var array $menu @var int $cartCount @var ?array $user */
$brand = (string) setting('store_name');
[$w1, $w2] = array_pad(explode(' ', $brand, 2), 2, '');
?>
<header class="header" id="top">
    <div class="container header__inner">
        <button class="menu-btn" type="button" data-drawer-open="mnav" aria-label="Open menu"><?= icon('menu', 24) ?></button>

        <a href="/" class="logo" aria-label="<?= e($brand) ?> home">
            <img src="<?= e(asset('img/logo-mark.svg')) ?>" alt="" width="36" height="36">
            <span class="logo__word"><?= e($w1) ?> <span><?= e($w2) ?></span></span>
        </a>

        <nav class="nav" aria-label="Main">
            <?php foreach ($menu as $cat): ?>
                <div class="nav__item">
                    <a class="nav__link" href="/c/<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
                    <?php if ($cat['items']): ?>
                        <div class="mega">
                            <div class="mega__inner">
                                <div>
                                    <a class="mega__title" href="/c/<?= e($cat['slug']) ?>">All <?= e($cat['name']) ?></a>
                                    <?php foreach (array_slice($cat['items'], 0, 10) as $sub): ?>
                                        <a class="mega__sub" href="/c/<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                                <?php foreach (array_slice($cat['items'], 10) ? array_chunk(array_slice($cat['items'], 10), 10) : [] as $chunk): ?>
                                    <div><?php foreach ($chunk as $sub): ?><a class="mega__sub" href="/c/<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a><?php endforeach; ?></div>
                                <?php endforeach; ?>
                                <a class="mega__promo" href="/c/<?= e($cat['slug']) ?>?sort=new"
                                   <?php if ($cat['image']): ?>style="background-image:linear-gradient(transparent,rgba(0,0,0,.7)),url('<?= e(upload_url($cat['image'])) ?>')"<?php endif; ?>>
                                    <small>New season</small>
                                    <strong>Shop <?= e($cat['name']) ?></strong>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <form class="search" action="/search" method="get" role="search" data-suggest>
            <span class="search__icon"><?= icon('search', 18) ?></span>
            <label for="q" class="sr-only">Search jerseys</label>
            <input class="search__input" id="q" type="search" name="q" value="<?= e(request()->query('q', '')) ?>"
                   placeholder="Search for teams, players, leagues…" autocomplete="off" maxlength="80">
            <div class="suggest" role="listbox"></div>
        </form>

        <div class="actions">
            <button class="action action--search" type="button" data-toggle="msearch" aria-label="Search"><?= icon('search', 22) ?></button>
            <div class="dropdown">
                <a class="action" href="<?= $user ? '/account' : '/login' ?>"><?= icon('user', 22) ?><span class="action__label"><?= $user ? 'Profile' : 'Login' ?></span></a>
                <div class="dropdown__panel">
                    <?php if ($user): ?>
                        <strong>Hello, <?= e(explode(' ', $user['name'])[0]) ?></strong>
                        <p class="muted mb-0" style="font-size:12px"><?= e($user['email']) ?></p>
                        <hr>
                        <a href="/account/orders">Orders</a>
                        <a href="/wishlist">Wishlist</a>
                        <a href="/account/addresses">Saved addresses</a>
                        <a href="/account">Edit profile</a>
                        <hr>
                        <form method="post" action="/logout"><?= csrf_field() ?><button class="link" type="submit">Logout</button></form>
                    <?php else: ?>
                        <strong>Welcome</strong>
                        <p class="muted" style="font-size:12px">To access account and manage orders</p>
                        <a class="btn btn--outline btn--sm" href="/login" style="display:inline-flex;width:auto;padding:.4rem 1rem">Login / Signup</a>
                        <hr>
                        <a href="/track-order">Track order</a>
                    <?php endif; ?>
                </div>
            </div>
            <a class="action" href="/wishlist"><?= icon('heart', 22) ?><span class="action__label">Wishlist</span></a>
            <a class="action" href="/cart"><?= icon('bag', 22) ?><span class="action__label">Bag</span><span class="action__count" data-cart-count="<?= $cartCount ?>"><?= $cartCount ?: '' ?></span></a>
        </div>
    </div>
    <div class="container msearch" id="msearch">
        <form class="search" style="display:block;max-width:none" action="/search" method="get" role="search" data-suggest>
            <span class="search__icon"><?= icon('search', 18) ?></span>
            <input class="search__input" type="search" name="q" placeholder="Search teams, players, leagues…" autocomplete="off" maxlength="80" aria-label="Search">
            <div class="suggest" role="listbox"></div>
        </form>
    </div>
</header>

<div class="drawer" id="mnav" aria-hidden="true">
    <div class="drawer__scrim" data-drawer-close></div>
    <div class="drawer__panel" role="dialog" aria-label="Menu">
        <div class="drawer__head">
            <h2><?= $user ? 'Hi, ' . e(explode(' ', $user['name'])[0]) : 'Welcome' ?></h2>
            <button class="drawer__close" type="button" data-drawer-close aria-label="Close menu"><?= icon('close', 22) ?></button>
        </div>
        <nav class="mnav">
            <?php foreach ($menu as $cat): ?>
                <?php if ($cat['items']): ?>
                    <details>
                        <summary><?= e($cat['name']) ?></summary>
                        <a href="/c/<?= e($cat['slug']) ?>">All <?= e($cat['name']) ?></a>
                        <?php foreach ($cat['items'] as $sub): ?><a href="/c/<?= e($sub['slug']) ?>"><?= e($sub['name']) ?></a><?php endforeach; ?>
                    </details>
                <?php else: ?>
                    <a href="/c/<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="/shop?sort=new">New Arrivals</a>
            <a href="/track-order">Track Order</a>
            <?php if ($user): ?>
                <a href="/account/orders">My Orders</a>
                <a href="/account">My Account</a>
            <?php else: ?>
                <a href="/login">Login / Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</div>
