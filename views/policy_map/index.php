<?php
/** @var string $svgAccess */
/** @var string $svgCascade */
/** @var int $httpAccessCount */
/** @var int $peerCount */
/** @var int $routingCount */
?>
<div class="page-header">
    <h2>Policy map</h2>
    <p class="pm-map-lead">
        Read-only graph from the panel database (what Apply writes to Squid).
        Flow is <strong>top → bottom</strong> (first match). Click an ACL node to highlight related edges.
    </p>
</div>

<div class="pm-map-legend" aria-hidden="true">
    <span class="pm-map-leg pm-map-leg-acl">ACL</span>
    <span class="pm-map-leg pm-map-leg-allow">allow</span>
    <span class="pm-map-leg pm-map-leg-deny">deny</span>
    <span class="pm-map-leg pm-map-leg-peer">peer</span>
    <span class="pm-map-leg pm-map-leg-direct">DIRECT</span>
</div>

<section class="card pm-map-layer" id="pm-layer-access">
    <div class="card-header">
        <h3>HTTP Access <span class="pm-map-hint"><?= (int)$httpAccessCount ?> rule(s) · ACL → decision</span></h3>
    </div>
    <div class="card-body pm-map-svg-wrap">
        <?= $svgAccess ?>
    </div>
</section>

<section class="card pm-map-layer" id="pm-layer-cascade">
    <div class="card-header">
        <h3>Cascade <span class="pm-map-hint"><?= (int)$routingCount ?> routing · <?= (int)$peerCount ?> peer(s)</span></h3>
    </div>
    <div class="card-body pm-map-svg-wrap">
        <?= $svgCascade ?>
    </div>
</section>

<script>
(function () {
    var root = document.querySelector('.content-area');
    if (!root) return;
    var selected = null;

    function clear() {
        root.querySelectorAll('.pm-svg-node.is-active, .pm-svg-node.is-related').forEach(function (el) {
            el.classList.remove('is-active', 'is-related');
        });
    }

    function selectAcl(name) {
        clear();
        if (!name) {
            selected = null;
            return;
        }
        if (selected === name) {
            selected = null;
            return;
        }
        selected = name;
        root.querySelectorAll('.pm-svg-node').forEach(function (g) {
            var acl = g.getAttribute('data-acl') || '';
            var acls = (g.getAttribute('data-acls') || '').split(/\s+/);
            if (acl === name) {
                g.classList.add('is-active');
            } else if (acls.indexOf(name) !== -1) {
                g.classList.add('is-related');
            }
        });
    }

    root.addEventListener('click', function (ev) {
        var g = ev.target.closest('.pm-svg-node[data-acl]');
        if (!g) return;
        ev.preventDefault();
        selectAcl(g.getAttribute('data-acl') || '');
    });
})();
</script>
