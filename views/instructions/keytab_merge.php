<div class="page-header">
    <h2>Merging two keytab files</h2>
    <a href="/instructions" class="btn btn-secondary">← Instructions</a>
</div>

<div class="card">
    <div class="card-header"><h3>Добавить SPN в уже существующий keytab (Windows, ktpass)</h3></div>
    <div class="card-body guide-body">
        <p>Так в KWTS наращивают один файл: предыдущий keytab в <code>-in</code>, новый путь в <code>-out</code>. Не перезаписывайте исходник — пишите следующий файл.</p>
        <p><strong>Одна учётка на все узлы.</strong> Нужна та же соль, что вывели при первом <code>+dumpsalt</code>, и тот же пароль <code>squid-user</code>:</p>
        <div class="code-block">C:\Windows\system32\ktpass.exe -princ HTTP/&lt;fqdn-нового-узла&gt;@&lt;REALM&gt; -mapuser squid-user@&lt;REALM&gt; -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -in C:\keytabs\old.keytab -out C:\keytabs\new.keytab -setupn -setpass -rawsalt "&lt;соль с первого ktpass&gt;"</div>
        <p><strong>Разная учётка на узел.</strong> Соль не указывают; <code>-mapuser</code> — учётка нового узла:</p>
        <div class="code-block">C:\Windows\system32\ktpass.exe -princ HTTP/&lt;fqdn-нового-узла&gt;@&lt;REALM&gt; -mapuser squid-user2@&lt;REALM&gt; -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -in C:\keytabs\old.keytab -out C:\keytabs\new.keytab</div>
        <p>FQDN в <code>-princ</code> — нижний регистр. Создание первого файла: <a href="/instructions?g=keytab">Creating a keytab file</a>.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Склеить два готовых MIT keytab (Linux, ktutil)</h3></div>
    <div class="card-body guide-body">
        <p>Если уже есть два независимых <code>.keytab</code> (два <code>ktpass -out</code> без <code>-in</code>), на Linux их объединяют так:</p>
        <div class="code-block">ktutil
rkt /path/a.keytab
rkt /path/b.keytab
wkt /path/merged.keytab
quit</div>
        <p>Проверка:</p>
        <div class="code-block">klist -k -t /path/merged.keytab</div>
        <p>В SPM залейте итог как <code>/etc/squid/&lt;name&gt;.keytab</code> (Authentication → Kerberos → Upload). Панель принимает только пути под <code>/etc/squid/*.keytab</code>.</p>
    </div>
</div>

<p class="text-muted" style="font-size:0.82rem;">Шаги ktpass — по статье KWTS ID 166438. Склейка через ktutil — штатный MIT Kerberos, не интерфейс KWTS.</p>
