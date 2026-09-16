</div>
    </div>
  </div>
  <script>
    (function () {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebar-backdrop');
      const toggle = document.getElementById('nav-toggle');
      if (!sidebar || !toggle) return;
      function close() {
        sidebar.classList.remove('open');
        if (backdrop) { backdrop.hidden = true; backdrop.classList.remove('show'); }
      }
      function open() {
        sidebar.classList.add('open');
        if (backdrop) { backdrop.hidden = false; backdrop.classList.add('show'); }
      }
      toggle.addEventListener('click', () => {
        if (sidebar.classList.contains('open')) close(); else open();
      });
      if (backdrop) backdrop.addEventListener('click', close);
    })();
  </script>
</body>
</html>
