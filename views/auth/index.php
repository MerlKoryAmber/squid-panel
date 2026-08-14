<div class="page-header">
    <h2>Authentication</h2>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
    <a href="/auth/basic" class="card" style="text-decoration:none; color:inherit;">
        <div class="card-body" style="text-align:center; padding: var(--space-xl);">
            <div style="font-size: 2rem; margin-bottom: var(--space-md);">🔐</div>
            <h3 style="margin-bottom: var(--space-sm);">Basic Authentication</h3>
            <p style="color: var(--ir-text-muted); font-size: 0.85rem;">HTPasswd / NCSA auth helper</p>
        </div>
    </a>
    <a href="/auth/kerberos" class="card" style="text-decoration:none; color:inherit;">
        <div class="card-body" style="text-align:center; padding: var(--space-xl);">
            <div style="font-size: 2rem; margin-bottom: var(--space-md);">🎫</div>
            <h3 style="margin-bottom: var(--space-sm);">Kerberos (Negotiate)</h3>
            <p style="color: var(--ir-text-muted); font-size: 0.85rem;">Active Directory SSO via keytab</p>
        </div>
    </a>
    <a href="/auth/ntlm" class="card" style="text-decoration:none; color:inherit;">
        <div class="card-body" style="text-align:center; padding: var(--space-xl);">
            <div style="font-size: 2rem; margin-bottom: var(--space-md);">🔑</div>
            <h3 style="margin-bottom: var(--space-sm);">NTLM (Winbind)</h3>
            <p style="color: var(--ir-text-muted); font-size: 0.85rem;">Windows domain authentication</p>
        </div>
    </a>
</div>
