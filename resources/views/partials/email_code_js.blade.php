<script>
/* 改邮箱验证码：往新邮箱发码，60 秒倒计时，防止连点 */
(function () {
    var btn = document.getElementById('send-email-code');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        var email = (document.getElementById('profile-email') || {}).value || '';
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            window.alert('请先填写正确的邮箱'); return;
        }
        if (btn.disabled) { return; }
        var tokenEl = document.querySelector('meta[name="csrf-token"]');
        var old = btn.textContent;
        btn.disabled = true; btn.textContent = '发送中…';
        fetch('{{ url("settings/email-code") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': tokenEl ? tokenEl.content : '' },
            body: 'email=' + encodeURIComponent(email) + '&_token=' + encodeURIComponent(tokenEl ? tokenEl.content : '')
        }).then(function (r) { return r.json(); }).then(function (d) {
            window.alert(d.message || (d.status ? '已发送' : '发送失败'));
            if (d.status) {
                var left = 60;
                btn.textContent = left + ' 秒后重发';
                var t = setInterval(function () {
                    left--;
                    if (left <= 0) { clearInterval(t); btn.disabled = false; btn.textContent = '重新获取'; }
                    else { btn.textContent = left + ' 秒后重发'; }
                }, 1000);
            } else {
                btn.disabled = false; btn.textContent = old;
            }
        }).catch(function () {
            window.alert('发送失败，请稍后再试');
            btn.disabled = false; btn.textContent = old;
        });
    });
})();
</script>
