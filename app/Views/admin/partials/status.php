<?php
use App\Services\OrderService;

/** @var string $s order status */
?><span class="badge badge--<?= OrderService::STATUS_BADGE[$s] ?? 'muted' ?>"><?= e(label($s)) ?></span>
