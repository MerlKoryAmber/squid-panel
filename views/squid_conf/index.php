<div class="page-header">
    <h2>Live config</h2>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= htmlspecialchars((string)$path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
        <span class="subtitle">
            Read-only.
            <?php if ((int)($bytes ?? 0) > 0): ?>
                <?= (int)$bytes ?> bytes<?php if (!empty($mtime)): ?>, mtime <?= htmlspecialchars((string)$mtime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if ($error !== '' && $body === ''): ?>
        <div class="empty-state">
            <h4><?= htmlspecialchars((string)$error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
        </div>
        <?php else: ?>
        <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="margin: 12px;"><?= htmlspecialchars((string)$error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
        <pre class="conf-readonly" tabindex="0"><?= htmlspecialchars((string)$body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
        <?php endif; ?>
    </div>
</div>
