<?php
/** @var string $svgAccess1 */
/** @var string $svgAccess2 */
/** @var string $svgCascade */
/** @var int $httpAccessCount */
/** @var int $peerCount */
/** @var int $routingCount */
/** @var array $aclTips */
?>
<p class="pm-map-lead">
    Compact graph from the panel database.
    HTTP Access: <strong>AND</strong> inside a rule, <strong>first match</strong> in reading order.
    Cascade: only ACLs that actually enter a peer / DIRECT. Click an ACL to see its values.
</p>

<div class="pm-map-legend" aria-hidden="true">
    <span class="pm-map-leg pm-map-leg-acl">ACL</span>
    <span class="pm-map-leg pm-map-leg-allow">allow</span>
    <span class="pm-map-leg pm-map-leg-deny">deny</span>
    <span class="pm-map-leg pm-map-leg-peer-silver">peer (silver)</span>
    <span class="pm-map-leg pm-map-leg-peer-bronze">peer (bronze)</span>
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

<section class="card pm-map-layer pm-map-layer-cascade" id="pm-layer-cascade">
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
    var tips = <?= json_encode($aclTips, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
    var pop = document.getElementById('acl-tip-pop');
    var selected = null;

    function clearHighlight() {
        root.querySelectorAll('.pm-svg-node.is-active, .pm-svg-node.is-related').forEach(function (el) {
            el.classList.remove('is-active', 'is-related');
        });
    }

    function hideTip() {
        if (!pop) return;
        pop.hidden = true;
    }

    function showTip(name, anchorEl) {
        if (!pop) return;
        var text = tips[name] || (name + '\n(no ACL details)');
        pop.textContent = text;
        pop.hidden = false;
        var r = anchorEl.getBoundingClientRect();
        var margin = 8;
        pop.style.left = r.left + 'px';
        pop.style.top = (r.bottom + 6) + 'px';
        var pr = pop.getBoundingClientRect();
        var left = r.left;
        var top = r.bottom + 6;
        if (pr.right > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - pr.width - margin);
        }
        if (pr.bottom > window.innerHeight - margin) {
            top = Math.max(margin, r.top - pr.height - 6);
        }
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function selectAcl(name, anchorEl) {
        if (!name) {
            clearHighlight();
            hideTip();
            selected = null;
            return;
        }
        if (selected === name) {
            clearHighlight();
            hideTip();
            selected = null;
            return;
        }
        clearHighlight();
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
        showTip(name, anchorEl);
    }

    root.addEventListener('click', function (ev) {
        var g = ev.target.closest('.pm-svg-node[data-acl]');
        if (!g) {
            if (!ev.target.closest('#acl-tip-pop')) {
                hideTip();
            }
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        selectAcl(g.getAttribute('data-acl') || '', g);
    });

    window.addEventListener('scroll', hideTip, true);
    window.addEventListener('resize', hideTip);
})();
</script>
