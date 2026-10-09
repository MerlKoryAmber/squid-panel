<?php
/** @var string $svgAccess1 */
/** @var string $svgAccess2 */
/** @var string $svgCascade */
/** @var int $httpAccessCount */
/** @var int $peerCount */
/** @var int $routingCount */
/** @var array $accessMeta */
/** @var array $cascadeMeta */
?>
<div class="page-header">
    <h2>Policy map</h2>
    <p class="pm-map-lead">
        Compact graph from the panel database.
        HTTP Access: <strong>AND</strong> inside a rule, <strong>first match</strong> in reading order.
        Cascade: only ACLs that actually enter a peer / DIRECT.
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
        <h3>HTTP Access <span class="pm-map-hint"><?= (int)$httpAccessCount ?> rule(s) · AND → decision</span></h3>
    </div>
    <div class="card-body pm-map-svg-wrap">
        <div class="pm-svg-slot pm-svg-slot-narrow"><?= $svgAccess1 ?></div>
        <div class="pm-svg-slot pm-svg-slot-wide"><?= $svgAccess2 ?></div>
    </div>
</section>

<section class="card pm-map-layer" id="pm-layer-cascade">
    <div class="card-header">
        <h3>Cascade <span class="pm-map-hint">real paths only · <?= (int)$routingCount ?> routing · <?= (int)$peerCount ?> peer(s) in DB</span></h3>
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
