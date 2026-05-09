<?php
// VULN (API8): Adminer dejado en docroot por el dev (clasico)
// Esto NO es Adminer real - es un stub para que aparezca en fuzzing
// y el alumno lo identifique como vector de DB management expuesto

echo '<!DOCTYPE html><html><head><title>Adminer 4.7.7</title>';
echo '<link rel="stylesheet" type="text/css" href="?file=default.css&version=4.7.7">';
echo '</head><body>';
echo '<h1>Adminer 4.7.7</h1>';
echo '<p>Login</p>';
echo '<form method="post"><table>';
echo '<tr><th>System<td><select name="auth[driver]"><option value="server">MySQL</option></select>';
echo '<tr><th>Server<td><input name="auth[server]" value="db">';
echo '<tr><th>Username<td><input name="auth[username]" value="root">';
echo '<tr><th>Password<td><input type="password" name="auth[password]" value="">';
echo '<tr><th>Database<td><input name="auth[db]" value="cryptovulnx">';
echo '</table>';
echo '<p><input type="submit" value="Login"></p>';
echo '</form>';

// VULN: Hint en HTML comment
echo '<!-- DBA note: usar auth[server]=db, auth[username]=root, auth[password]= (vacio) -->';
echo '<!-- O auth[server]=db, auth[username]=crypto, auth[password]=crypto123 -->';
echo '<!-- FLAG-RECON-07: adminer_credenciales_db_en_html_comment -->';
echo '</body></html>';
