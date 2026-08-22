<div class="page-header">
    <h2>Creating a keytab file</h2>
    <a href="/instructions" class="btn btn-secondary">← Instructions</a>
</div>

<div class="card">
    <div class="card-header"><h3>Where and who</h3></div>
    <div class="card-body guide-body">
        <p>Keytab — файл с principal и ключами из пароля учётки. Squid использует его для Negotiate/Kerberos без интерактивного пароля.</p>
        <p>Создавать на контроллере домена или на Windows Server в домене, под учёткой с правами администратора домена. Утилита: <code>ktpass.exe</code>.</p>
        <p>Имя хоста Squid в <code>-princ</code> — в нижнем регистре (например <code>proxy.company.com</code>).</p>
        <p>В панели SPM готовый файл кладётся в <code>/etc/squid/*.keytab</code> (Authentication → Kerberos → Upload).</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Одна учётка AD на все узлы</h3></div>
    <div class="card-body guide-body">
        <ol>
            <li>В Active Directory Users and Computers создайте пользователя, например <code>squid-user</code>.</li>
            <li>Свойства учётки → Account → включите <strong>This account supports Kerberos AES 256 bit encryption</strong>.</li>
            <li>Создайте keytab и сразу сохраните соль с экрана (<code>+dumpsalt</code>) — она нужна, чтобы потом добавить другие SPN в тот же файл:</li>
        </ol>
        <div class="code-block">C:\Windows\system32\ktpass.exe -princ HTTP/&lt;fqdn-squid&gt;@&lt;REALM&gt; -mapuser squid-user@&lt;REALM&gt; -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * +dumpsalt -out C:\keytabs\filename1.keytab</div>
        <p>Утилита спросит пароль <code>squid-user</code>. На экране будет строка вида <code>Hashing password with salt "…"</code> — сохраните значение соли.</p>
        <p>Дополнительные узлы (кластер) — в тот же keytab через <code>-in</code> / <code>-out</code> и ту же соль. Подробнее: <a href="/instructions?g=keytab-merge">Merging two keytab files</a>.</p>
        <p>Пример для трёх узлов в реалме <code>TEST.LOCAL</code>:</p>
        <div class="code-block">C:\Windows\system32\ktpass.exe -princ HTTP/control-01.test.local@TEST.LOCAL -mapuser squid-user@TEST.LOCAL -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * +dumpsalt -out C:\keytabs\filename1.keytab

C:\Windows\system32\ktpass.exe -princ HTTP/secondary-01.test.local@TEST.LOCAL -mapuser squid-user@TEST.LOCAL -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -in C:\keytabs\filename1.keytab -out C:\keytabs\filename2.keytab -setupn -setpass -rawsalt "TEST.LOCALHTTPcontrol-01.test.local"

C:\Windows\system32\ktpass.exe -princ HTTP/secondary-02.test.local@TEST.LOCAL -mapuser squid-user@TEST.LOCAL -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -in C:\keytabs\filename2.keytab -out C:\keytabs\filename3.keytab -setupn -setpass -rawsalt "TEST.LOCALHTTPcontrol-01.test.local"</div>
        <p>Итог — <code>filename3.keytab</code> со всеми тремя SPN.</p>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Отдельная учётка на каждый узел</h3></div>
    <div class="card-body guide-body">
        <p>Для каждого узла — своя AD-учётка (<code>squid-user</code>, <code>squid-user2</code>, …) и тот же флажок AES 256. Соль через <code>+dumpsalt</code> не нужна: в <code>-in</code> подаёте предыдущий файл, в <code>-mapuser</code> — учётка этого узла.</p>
        <div class="code-block">C:\Windows\system32\ktpass.exe -princ HTTP/control-01.test.local@TEST.LOCAL -mapuser squid-user@TEST.LOCAL -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -out C:\keytabs\filename1.keytab

C:\Windows\system32\ktpass.exe -princ HTTP/secondary-01.test.local@TEST.LOCAL -mapuser squid-user2@TEST.LOCAL -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -in C:\keytabs\filename1.keytab -out C:\keytabs\filename2.keytab

C:\Windows\system32\ktpass.exe -princ HTTP/secondary-02.test.local@TEST.LOCAL -mapuser squid-user3@TEST.LOCAL -crypto AES256-SHA1 -ptype KRB5_NT_PRINCIPAL -pass * -in C:\keytabs\filename2.keytab -out C:\keytabs\filename3.keytab</div>
    </div>
</div>

<p class="text-muted" style="font-size:0.82rem;">Процедура по статье Kaspersky Web Traffic Security «Создание keytab-файла для сервиса Squid» (ID 166438), адаптировано для SPM.</p>
